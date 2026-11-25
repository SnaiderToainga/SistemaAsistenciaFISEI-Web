<?php

date_default_timezone_set('America/Guayaquil');

require_once __DIR__ . '/../config/database.php';

class AsistenciaModel {

    public const ESTADOS   = ['PRESENTE', 'ATRASO', 'FALTA', 'JUSTIFICADO'];
    public const PARCIALES = ['Parcial 1', 'Parcial 2'];

    private const SQL_CAMPOS_ASISTENCIA = "
        e.id AS id_estudiante,
        e.uid_rfid,
        e.cedula,
        e.nombres,
        e.apellidos,
        COALESCE(e.carrera, 'Software') AS carrera,
        a.id AS id_asistencia,
        a.parcial,
        a.fecha,
        TO_CHAR(a.hora, 'HH24:MI:SS') AS hora,
        a.estado,
        COALESCE(a.observacion, '') AS observacion";

    public static function listarMateriasDocente(int $idUsuario, string $rol): array {
        $pdo = Conexion::conectar();
        $sql = "SELECT id, codigo, nombre, periodo, id_docente FROM materias WHERE activa = true";

        if ($rol === 'DOCENTE') {
            if ($idUsuario <= 0) {
                return [];
            }
            $stmt = $pdo->prepare($sql . " AND id_docente = :id_docente ORDER BY codigo ASC, nombre ASC");
            $stmt->execute([':id_docente' => $idUsuario]);
        } elseif ($rol === 'ADMIN') {
            $stmt = $pdo->prepare($sql . " ORDER BY codigo ASC, nombre ASC");
            $stmt->execute();
        } else {
            throw new Exception("Rol de usuario no autorizado.");
        }

        return array_map(static function (array $m): array {
            $m['id']         = (int)$m['id'];
            $m['id_docente'] = $m['id_docente'] !== null ? (int)$m['id_docente'] : null;
            return $m;
        }, $stmt->fetchAll());
    }

    public static function listarFechasRegistradas(int $idMateria, string $parcial, int $idUsuario, string $rol): array {
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        $parcial = self::validarParcial($parcial);
        $pdo = Conexion::conectar();
        self::verificarAccesoMateria($pdo, $idMateria, $idUsuario, $rol);

        $sql = "SELECT
                    TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha,
                    COUNT(*) AS total
                FROM asistencias
                WHERE id_materia = :id_materia
                  AND parcial = :parcial
                GROUP BY fecha
                ORDER BY fecha DESC
                LIMIT 60";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_materia' => $idMateria, ':parcial' => $parcial]);

        return array_map(static function (array $fila): array {
            return [
                'fecha' => (string)$fila['fecha'],
                'total' => (int)$fila['total']
            ];
        }, $stmt->fetchAll());
    }

    public static function listarAsistencias(int $idMateria, string $fecha, string $parcial, int $idUsuario, string $rol): array {
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        $fecha   = self::validarFecha($fecha);
        $parcial = self::validarParcial($parcial);
        $pdo = Conexion::conectar();
        self::verificarAccesoMateria($pdo, $idMateria, $idUsuario, $rol);

        $sql = "SELECT " . self::SQL_CAMPOS_ASISTENCIA . "
                FROM matriculas mat
                INNER JOIN estudiantes e ON mat.id_estudiante = e.id
                LEFT JOIN asistencias a
                    ON a.id_estudiante = e.id
                   AND a.id_materia = mat.id_materia
                   AND a.fecha = :fecha
                   AND a.parcial = :parcial
                WHERE mat.id_materia = :id_materia
                ORDER BY (a.id IS NULL) ASC, a.hora DESC NULLS LAST, e.apellidos ASC, e.nombres ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':fecha'      => $fecha,
            ':parcial'    => $parcial,
            ':id_materia' => $idMateria
        ]);

        return array_map(
            static fn(array $fila): array => self::formatearFila($fila, $fecha),
            $stmt->fetchAll()
        );
    }

    public static function registrarAsistenciaRfid(
        string $uidRfid,
        int $idMateria,
        string $fecha,
        string $parcial,
        int $idUsuario,
        string $rol
    ): array {
        $uidRfid = trim($uidRfid);
        if ($uidRfid === '') {
            throw new Exception("Ingrese o escanee un código RFID.");
        }
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        $fecha   = self::validarFecha($fecha);
        $parcial = self::validarParcial($parcial);

        $pdo = Conexion::conectar();
        self::verificarAccesoMateria($pdo, $idMateria, $idUsuario, $rol);

        $stmtEst = $pdo->prepare(
            "SELECT id, uid_rfid, cedula, nombres, apellidos, COALESCE(carrera, 'Software') AS carrera
             FROM estudiantes
             WHERE uid_rfid = :uid_rfid
             LIMIT 1"
        );
        $stmtEst->execute([':uid_rfid' => $uidRfid]);
        $est = $stmtEst->fetch();

        if (!$est) {
            return [
                'resultado' => 'not_found',
                'mensaje'   => "El código RFID {$uidRfid} no está registrado.",
                'data'      => null
            ];
        }

        $idEstudiante = (int)$est['id'];

        $stmtMat = $pdo->prepare(
            "SELECT 1 FROM matriculas WHERE id_estudiante = :id_estudiante AND id_materia = :id_materia LIMIT 1"
        );
        $stmtMat->execute([':id_estudiante' => $idEstudiante, ':id_materia' => $idMateria]);

        if (!$stmtMat->fetchColumn()) {
            return [
                'resultado' => 'no_matriculado',
                'mensaje'   => "{$est['nombres']} {$est['apellidos']} no está matriculado en esta asignatura.",
                'data'      => [
                    'id_estudiante' => $idEstudiante,
                    'uid_rfid'      => $est['uid_rfid'],
                    'cedula'        => $est['cedula'],
                    'nombres'       => $est['nombres'],
                    'apellidos'     => $est['apellidos']
                ]
            ];
        }

        $existente = self::obtenerFilaEstudiante($pdo, $idEstudiante, $idMateria, $fecha);
        if ($existente && $existente['id_asistencia'] !== null) {
            $mismoParcial = $existente['parcial'] === $parcial;
            return [
                'resultado' => 'duplicado',
                'mensaje'   => $mismoParcial
                    ? "{$est['nombres']} {$est['apellidos']} ya registró asistencia ({$existente['estado']}) el {$fecha}."
                    : "{$est['nombres']} {$est['apellidos']} ya registró asistencia el {$fecha} en {$existente['parcial']} ({$existente['estado']}).",
                'data'      => $mismoParcial ? $existente : null
            ];
        }

        try {
            $sql = "INSERT INTO asistencias (id_estudiante, id_materia, parcial, fecha, hora, estado, observacion)
                    VALUES (:id_estudiante, :id_materia, :parcial, :fecha, :hora, 'PRESENTE', NULL)
                    RETURNING id, parcial, fecha, TO_CHAR(hora, 'HH24:MI:SS') AS hora, estado, COALESCE(observacion, '') AS observacion";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_estudiante' => $idEstudiante,
                ':id_materia'    => $idMateria,
                ':parcial'       => $parcial,
                ':fecha'         => $fecha,
                ':hora'          => date('H:i:s')
            ]);
            $asistencia = $stmt->fetch();

            return [
                'resultado' => 'ok',
                'mensaje'   => "Presente registrado: {$est['nombres']} {$est['apellidos']}.",
                'data'      => self::formatearFila(array_merge($est, [
                    'id_estudiante' => $idEstudiante,
                    'id_asistencia' => $asistencia['id'],
                    'parcial'       => $asistencia['parcial'],
                    'fecha'         => $asistencia['fecha'],
                    'hora'          => $asistencia['hora'],
                    'estado'        => $asistencia['estado'],
                    'observacion'   => $asistencia['observacion']
                ]), $fecha)
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $dup = self::obtenerFilaEstudiante($pdo, $idEstudiante, $idMateria, $fecha);
                $mismoParcial = $dup && $dup['parcial'] === $parcial;
                return [
                    'resultado' => 'duplicado',
                    'mensaje'   => ($dup && !$mismoParcial)
                        ? "{$est['nombres']} {$est['apellidos']} ya registró asistencia el {$fecha} en {$dup['parcial']} ({$dup['estado']})."
                        : "{$est['nombres']} {$est['apellidos']} ya registró asistencia el {$fecha}.",
                    'data'      => $mismoParcial ? $dup : null
                ];
            }
            throw new Exception("No se pudo registrar la asistencia.");
        }
    }

    public static function actualizarEstadoManual(
        int $idEstudiante,
        int $idMateria,
        string $fecha,
        string $parcial,
        string $estado,
        ?string $observacion,
        int $idUsuario,
        string $rol
    ): array {
        if ($idEstudiante <= 0 || $idMateria <= 0) {
            throw new Exception("Datos de estudiante o asignatura inválidos.");
        }

        $fecha   = self::validarFecha($fecha);
        $parcial = self::validarParcial($parcial);
        $estado  = strtoupper(trim($estado));

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new Exception("Estado de asistencia no válido.");
        }

        $obsLimpia = trim((string)($observacion ?? ''));
        if (mb_strlen($obsLimpia, 'UTF-8') > 500) {
            throw new Exception("La observación no puede superar los 500 caracteres.");
        }

        $pdo = Conexion::conectar();
        self::verificarAccesoMateria($pdo, $idMateria, $idUsuario, $rol);

        $stmtMat = $pdo->prepare(
            "SELECT e.id AS id_estudiante, e.uid_rfid, e.cedula, e.nombres, e.apellidos, COALESCE(e.carrera, 'Software') AS carrera
             FROM matriculas mat
             INNER JOIN estudiantes e ON mat.id_estudiante = e.id
             WHERE mat.id_estudiante = :id_estudiante AND mat.id_materia = :id_materia
             LIMIT 1"
        );
        $stmtMat->execute([':id_estudiante' => $idEstudiante, ':id_materia' => $idMateria]);
        $est = $stmtMat->fetch();

        if (!$est) {
            throw new Exception("El estudiante no está matriculado en esta asignatura.");
        }

        $sql = "INSERT INTO asistencias (id_estudiante, id_materia, parcial, fecha, hora, estado, observacion)
                VALUES (:id_estudiante, :id_materia, :parcial, :fecha, :hora, :estado, :observacion)
                ON CONFLICT (id_estudiante, id_materia, fecha)
                DO UPDATE SET
                    estado = EXCLUDED.estado,
                    observacion = EXCLUDED.observacion,
                    parcial = EXCLUDED.parcial
                RETURNING id, parcial, fecha, TO_CHAR(hora, 'HH24:MI:SS') AS hora, estado, COALESCE(observacion, '') AS observacion";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_estudiante' => $idEstudiante,
            ':id_materia'    => $idMateria,
            ':parcial'       => $parcial,
            ':fecha'         => $fecha,
            ':hora'          => date('H:i:s'),
            ':estado'        => $estado,
            ':observacion'   => $obsLimpia !== '' ? $obsLimpia : null
        ]);
        $asistencia = $stmt->fetch();

        return self::formatearFila(array_merge($est, [
            'id_asistencia' => $asistencia['id'],
            'parcial'       => $asistencia['parcial'],
            'fecha'         => $asistencia['fecha'],
            'hora'          => $asistencia['hora'],
            'estado'        => $asistencia['estado'],
            'observacion'   => $asistencia['observacion']
        ]), $fecha);
    }

    public static function marcarFaltasPendientes(
        int $idMateria,
        string $fecha,
        string $parcial,
        int $idUsuario,
        string $rol
    ): int {
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        $fecha   = self::validarFecha($fecha);
        $parcial = self::validarParcial($parcial);

        $pdo = Conexion::conectar();
        self::verificarAccesoMateria($pdo, $idMateria, $idUsuario, $rol);

        $sql = "INSERT INTO asistencias (id_estudiante, id_materia, parcial, fecha, hora, estado, observacion)
                SELECT mat.id_estudiante, mat.id_materia, :parcial, :fecha, :hora, 'FALTA', NULL
                FROM matriculas mat
                LEFT JOIN asistencias a
                    ON a.id_estudiante = mat.id_estudiante
                   AND a.id_materia = mat.id_materia
                   AND a.fecha = :fecha_join
                WHERE mat.id_materia = :id_materia
                  AND a.id IS NULL
                ON CONFLICT (id_estudiante, id_materia, fecha) DO NOTHING";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':parcial'    => $parcial,
            ':fecha'      => $fecha,
            ':hora'       => date('H:i:s'),
            ':fecha_join' => $fecha,
            ':id_materia' => $idMateria
        ]);

        return $stmt->rowCount();
    }

    private static function verificarAccesoMateria(PDO $pdo, int $idMateria, int $idUsuario, string $rol): void {
        $stmt = $pdo->prepare("SELECT id_docente, activa FROM materias WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $idMateria]);
        $materia = $stmt->fetch();

        if (!$materia || !$materia['activa']) {
            throw new Exception("La asignatura no existe o está inactiva.");
        }

        if ($rol !== 'ADMIN' && ($rol !== 'DOCENTE' || (int)$materia['id_docente'] !== $idUsuario)) {
            throw new Exception("No tiene permisos sobre esta asignatura.");
        }
    }

    private static function obtenerFilaEstudiante(PDO $pdo, int $idEstudiante, int $idMateria, string $fecha): ?array {
        $sql = "SELECT " . self::SQL_CAMPOS_ASISTENCIA . "
                FROM estudiantes e
                LEFT JOIN asistencias a
                    ON a.id_estudiante = e.id
                   AND a.id_materia = :id_materia
                   AND a.fecha = :fecha
                WHERE e.id = :id_estudiante
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_materia' => $idMateria, ':fecha' => $fecha, ':id_estudiante' => $idEstudiante]);
        $fila = $stmt->fetch();

        return $fila ? self::formatearFila($fila, $fecha) : null;
    }

    private static function formatearFila(array $fila, string $fechaDefecto): array {
        return [
            'id_estudiante' => (int)$fila['id_estudiante'],
            'uid_rfid'      => (string)$fila['uid_rfid'],
            'cedula'        => (string)$fila['cedula'],
            'nombres'       => (string)$fila['nombres'],
            'apellidos'     => (string)$fila['apellidos'],
            'carrera'       => (string)$fila['carrera'],
            'id_asistencia' => $fila['id_asistencia'] !== null ? (int)$fila['id_asistencia'] : null,
            'parcial'       => $fila['parcial'] !== null ? (string)$fila['parcial'] : null,
            'fecha'         => $fila['fecha'] !== null ? (string)$fila['fecha'] : $fechaDefecto,
            'hora'          => $fila['hora'] !== null ? (string)$fila['hora'] : null,
            'estado'        => $fila['estado'] !== null ? (string)$fila['estado'] : null,
            'observacion'   => (string)($fila['observacion'] ?? '')
        ];
    }

    private static function validarFecha(string $fecha): string {
        $fecha = trim($fecha);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Exception("Formato de fecha inválido.");
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        $errores = DateTimeImmutable::getLastErrors();
        if (!$dt || ($errores && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) || $dt->format('Y-m-d') !== $fecha) {
            throw new Exception("La fecha seleccionada no es válida.");
        }

        return $fecha;
    }

    private static function validarParcial(string $parcial): string {
        $parcial = trim($parcial);
        if (!in_array($parcial, self::PARCIALES, true)) {
            throw new Exception("Parcial académico no válido.");
        }
        return $parcial;
    }
}
