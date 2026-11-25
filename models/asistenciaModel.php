<?php
/**
 * Modelo de Asistencia RFID en Vivo, Fechas y Justificaciones.
 * Contiene las reglas de negocio y delega SQL/PDO a AsistenciaDAO.
 */

date_default_timezone_set('America/Guayaquil');

require_once __DIR__ . '/../dao/AsistenciaDAO.php';

class AsistenciaModel {

    public const ESTADOS   = ['PRESENTE', 'ATRASO', 'FALTA', 'JUSTIFICADO'];
    public const PARCIALES = ['Parcial 1', 'Parcial 2'];

    public static function listarMateriasDocente(int $idUsuario, string $rol): array {
        if ($rol === 'DOCENTE') {
            if ($idUsuario <= 0) {
                return [];
            }
        } elseif ($rol !== 'ADMIN') {
            throw new Exception("Rol de usuario no autorizado.");
        }

        $materias = AsistenciaDAO::listarMateriasPorUsuario($idUsuario, $rol);

        return array_map(static function (Materia $m): array {
            return [
                'id'         => $m->getId(),
                'codigo'     => $m->getCodigo(),
                'nombre'     => $m->getNombre(),
                'periodo'    => $m->getPeriodo(),
                'id_docente' => $m->getIdDocente()
            ];
        }, $materias);
    }

    public static function listarFechasRegistradas(int $idMateria, string $parcial, int $idUsuario, string $rol): array {
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        $parcial = self::validarParcial($parcial);
        self::verificarAccesoMateria($idMateria, $idUsuario, $rol);

        return AsistenciaDAO::listarFechasRegistradas($idMateria, $parcial);
    }

    public static function listarAsistencias(int $idMateria, string $fecha, string $parcial, int $idUsuario, string $rol): array {
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        $fecha   = self::validarFecha($fecha);
        $parcial = self::validarParcial($parcial);
        self::verificarAccesoMateria($idMateria, $idUsuario, $rol);

        $filas = AsistenciaDAO::listarPorMateriaFechaParcial($idMateria, $fecha, $parcial);

        return array_map(
            static fn(array $fila): array => self::formatearFila($fila, $fecha),
            $filas
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
        self::verificarAccesoMateria($idMateria, $idUsuario, $rol);

        $est = AsistenciaDAO::buscarEstudiantePorRfid($uidRfid);
        if (!$est) {
            return [
                'resultado' => 'not_found',
                'mensaje'   => "El código RFID {$uidRfid} no está registrado.",
                'data'      => null
            ];
        }

        $idEstudiante = (int)$est->getId();

        if (!AsistenciaDAO::estaMatriculado($idEstudiante, $idMateria)) {
            return [
                'resultado' => 'no_matriculado',
                'mensaje'   => "{$est->getNombres()} {$est->getApellidos()} no está matriculado en esta asignatura.",
                'data'      => [
                    'id_estudiante' => $idEstudiante,
                    'uid_rfid'      => $est->getUidRfid(),
                    'cedula'        => $est->getCedula(),
                    'nombres'       => $est->getNombres(),
                    'apellidos'     => $est->getApellidos()
                ]
            ];
        }

        $filaExistente = AsistenciaDAO::obtenerRegistroDiario($idEstudiante, $idMateria, $fecha);
        $existente = $filaExistente ? self::formatearFila($filaExistente, $fecha) : null;

        if ($existente && $existente['id_asistencia'] !== null) {
            $mismoParcial = $existente['parcial'] === $parcial;
            return [
                'resultado' => 'duplicado',
                'mensaje'   => $mismoParcial
                    ? "{$est->getNombres()} {$est->getApellidos()} ya registró asistencia ({$existente['estado']}) el {$fecha}."
                    : "{$est->getNombres()} {$est->getApellidos()} ya registró asistencia el {$fecha} en {$existente['parcial']} ({$existente['estado']}).",
                'data'      => $mismoParcial ? $existente : null
            ];
        }

        try {
            $asistencia = AsistenciaDAO::registrarMarcacionRfid(
                $idEstudiante,
                $idMateria,
                $parcial,
                $fecha,
                date('H:i:s')
            );

            return [
                'resultado' => 'ok',
                'mensaje'   => "Presente registrado: {$est->getNombres()} {$est->getApellidos()}.",
                'data'      => self::combinarEstudianteAsistencia($est, $asistencia, $fecha)
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $filaDup = AsistenciaDAO::obtenerRegistroDiario($idEstudiante, $idMateria, $fecha);
                $dup = $filaDup ? self::formatearFila($filaDup, $fecha) : null;
                $mismoParcial = $dup && $dup['parcial'] === $parcial;

                return [
                    'resultado' => 'duplicado',
                    'mensaje'   => ($dup && !$mismoParcial)
                        ? "{$est->getNombres()} {$est->getApellidos()} ya registró asistencia el {$fecha} en {$dup['parcial']} ({$dup['estado']})."
                        : "{$est->getNombres()} {$est->getApellidos()} ya registró asistencia el {$fecha}.",
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

        self::verificarAccesoMateria($idMateria, $idUsuario, $rol);

        $est = AsistenciaDAO::obtenerEstudianteMatriculado($idEstudiante, $idMateria);
        if (!$est) {
            throw new Exception("El estudiante no está matriculado en esta asignatura.");
        }

        $entidad = new Asistencia(
            null,
            $idEstudiante,
            $idMateria,
            $parcial,
            $fecha,
            date('H:i:s'),
            $estado,
            $obsLimpia !== '' ? $obsLimpia : null
        );

        $asistencia = AsistenciaDAO::guardarOActualizarManual($entidad);

        return self::combinarEstudianteAsistencia($est, $asistencia, $fecha);
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
        self::verificarAccesoMateria($idMateria, $idUsuario, $rol);

        return AsistenciaDAO::marcarFaltasMasivas($idMateria, $parcial, $fecha, date('H:i:s'));
    }

    private static function verificarAccesoMateria(int $idMateria, int $idUsuario, string $rol): void {
        $materia = AsistenciaDAO::obtenerPermisoMateria($idMateria);

        if (!$materia || !$materia['activa']) {
            throw new Exception("La asignatura no existe o está inactiva.");
        }

        if ($rol !== 'ADMIN' && ($rol !== 'DOCENTE' || (int)$materia['id_docente'] !== $idUsuario)) {
            throw new Exception("No tiene permisos sobre esta asignatura.");
        }
    }

    private static function combinarEstudianteAsistencia(Estudiante $est, Asistencia $asistencia, string $fechaDefecto): array {
        return [
            'id_estudiante' => (int)$est->getId(),
            'uid_rfid'      => $est->getUidRfid(),
            'cedula'        => $est->getCedula(),
            'nombres'       => $est->getNombres(),
            'apellidos'     => $est->getApellidos(),
            'carrera'       => $est->getCarrera(),
            'id_asistencia' => $asistencia->getId(),
            'parcial'       => $asistencia->getParcial(),
            'fecha'         => $asistencia->getFecha() !== '' ? $asistencia->getFecha() : $fechaDefecto,
            'hora'          => $asistencia->getHora(),
            'estado'        => $asistencia->getEstado(),
            'observacion'   => $asistencia->getObservacion() ?? ''
        ];
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
