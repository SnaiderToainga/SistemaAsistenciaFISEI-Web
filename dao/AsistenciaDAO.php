<?php
/**
 * DAO de Asistencias.
 * Encapsula todas las consultas SQL y operaciones PDO para el pase de asistencia.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../entities/Asistencia.php';
require_once __DIR__ . '/../entities/Estudiante.php';
require_once __DIR__ . '/../entities/Materia.php';

class AsistenciaDAO {

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

    /**
     * Lista materias activas asignadas al docente o todas si es ADMIN.
     *
     * @return Materia[]
     */
    public static function listarMateriasPorUsuario(int $idUsuario, string $rol): array {
        $pdo = Conexion::conectar();
        $sql = "SELECT id, codigo, nombre, periodo, id_docente, activa FROM materias WHERE activa = true";

        if ($rol === 'DOCENTE') {
            $stmt = $pdo->prepare($sql . " AND id_docente = :id_docente ORDER BY codigo ASC, nombre ASC");
            $stmt->execute([':id_docente' => $idUsuario]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY codigo ASC, nombre ASC");
            $stmt->execute();
        }

        return array_map(
            static fn(array $fila): Materia => Materia::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Obtiene el dueño y estado activo de una asignatura para validar permisos.
     */
    public static function obtenerPermisoMateria(int $idMateria): ?array {
        $pdo = Conexion::conectar();

        $stmt = $pdo->prepare("SELECT id_docente, activa FROM materias WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $idMateria]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Lista las fechas con asistencias registradas para una materia y parcial.
     */
    public static function listarFechasRegistradas(int $idMateria, string $parcial): array {
        $pdo = Conexion::conectar();

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

    /**
     * Lista todos los estudiantes matriculados en la materia junto con su asistencia en la fecha/parcial.
     */
    public static function listarPorMateriaFechaParcial(int $idMateria, string $fecha, string $parcial): array {
        $pdo = Conexion::conectar();

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

        return $stmt->fetchAll();
    }

    /**
     * Busca un estudiante por su UID RFID.
     */
    public static function buscarEstudiantePorRfid(string $uidRfid): ?Estudiante {
        $pdo = Conexion::conectar();

        $stmt = $pdo->prepare(
            "SELECT id, uid_rfid, cedula, nombres, apellidos, COALESCE(carrera, 'Software') AS carrera
             FROM estudiantes
             WHERE uid_rfid = :uid_rfid
             LIMIT 1"
        );
        $stmt->execute([':uid_rfid' => $uidRfid]);
        $fila = $stmt->fetch();

        return $fila ? Estudiante::fromArray($fila) : null;
    }

    /**
     * Verifica si el estudiante está matriculado en la materia.
     */
    public static function estaMatriculado(int $idEstudiante, int $idMateria): bool {
        $pdo = Conexion::conectar();

        $stmt = $pdo->prepare(
            "SELECT 1 FROM matriculas WHERE id_estudiante = :id_estudiante AND id_materia = :id_materia LIMIT 1"
        );
        $stmt->execute([':id_estudiante' => $idEstudiante, ':id_materia' => $idMateria]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Obtiene los datos del estudiante si está matriculado en la materia.
     */
    public static function obtenerEstudianteMatriculado(int $idEstudiante, int $idMateria): ?Estudiante {
        $pdo = Conexion::conectar();

        $stmt = $pdo->prepare(
            "SELECT e.id, e.uid_rfid, e.cedula, e.nombres, e.apellidos, COALESCE(e.carrera, 'Software') AS carrera
             FROM matriculas mat
             INNER JOIN estudiantes e ON mat.id_estudiante = e.id
             WHERE mat.id_estudiante = :id_estudiante AND mat.id_materia = :id_materia
             LIMIT 1"
        );
        $stmt->execute([':id_estudiante' => $idEstudiante, ':id_materia' => $idMateria]);
        $fila = $stmt->fetch();

        return $fila ? Estudiante::fromArray($fila) : null;
    }

    /**
     * Obtiene la fila de asistencia de un estudiante para una fecha específica.
     */
    public static function obtenerRegistroDiario(int $idEstudiante, int $idMateria, string $fecha): ?array {
        $pdo = Conexion::conectar();

        $sql = "SELECT " . self::SQL_CAMPOS_ASISTENCIA . "
                FROM estudiantes e
                LEFT JOIN asistencias a
                    ON a.id_estudiante = e.id
                   AND a.id_materia = :id_materia
                   AND a.fecha = :fecha
                WHERE e.id = :id_estudiante
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_materia'    => $idMateria,
            ':fecha'         => $fecha,
            ':id_estudiante' => $idEstudiante
        ]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Inserta la marcación RFID como PRESENTE y retorna la entidad Asistencia.
     */
    public static function registrarMarcacionRfid(
        int $idEstudiante,
        int $idMateria,
        string $parcial,
        string $fecha,
        string $hora
    ): Asistencia {
        $pdo = Conexion::conectar();

        $sql = "INSERT INTO asistencias (id_estudiante, id_materia, parcial, fecha, hora, estado, observacion)
                VALUES (:id_estudiante, :id_materia, :parcial, :fecha, :hora, 'PRESENTE', NULL)
                RETURNING id, id_estudiante, id_materia, parcial, fecha, TO_CHAR(hora, 'HH24:MI:SS') AS hora, estado, COALESCE(observacion, '') AS observacion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_estudiante' => $idEstudiante,
            ':id_materia'    => $idMateria,
            ':parcial'       => $parcial,
            ':fecha'         => $fecha,
            ':hora'          => $hora
        ]);

        return Asistencia::fromArray($stmt->fetch());
    }

    /**
     * Inserta o actualiza manualmente el estado de asistencia (UPSERT).
     */
    public static function guardarOActualizarManual(Asistencia $asistencia): Asistencia {
        $pdo = Conexion::conectar();

        $sql = "INSERT INTO asistencias (id_estudiante, id_materia, parcial, fecha, hora, estado, observacion)
                VALUES (:id_estudiante, :id_materia, :parcial, :fecha, :hora, :estado, :observacion)
                ON CONFLICT (id_estudiante, id_materia, fecha)
                DO UPDATE SET
                    estado = EXCLUDED.estado,
                    observacion = EXCLUDED.observacion,
                    parcial = EXCLUDED.parcial
                RETURNING id, id_estudiante, id_materia, parcial, fecha, TO_CHAR(hora, 'HH24:MI:SS') AS hora, estado, COALESCE(observacion, '') AS observacion";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_estudiante' => $asistencia->getIdEstudiante(),
            ':id_materia'    => $asistencia->getIdMateria(),
            ':parcial'       => $asistencia->getParcial(),
            ':fecha'         => $asistencia->getFecha(),
            ':hora'          => $asistencia->getHora(),
            ':estado'        => $asistencia->getEstado(),
            ':observacion'   => $asistencia->getObservacion()
        ]);

        return Asistencia::fromArray($stmt->fetch());
    }

    /**
     * Marca como FALTA a todos los estudiantes matriculados sin registro en la fecha.
     */
    public static function marcarFaltasMasivas(int $idMateria, string $parcial, string $fecha, string $hora): int {
        $pdo = Conexion::conectar();

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
            ':hora'       => $hora,
            ':fecha_join' => $fecha,
            ':id_materia' => $idMateria
        ]);

        return $stmt->rowCount();
    }
}
