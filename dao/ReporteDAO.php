<?php
/**
 * DAO de Reportes y Dashboard.
 * Encapsula todas las consultas SQL y operaciones PDO para estadísticas y consolidados.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../entities/Estudiante.php';
require_once __DIR__ . '/../entities/Materia.php';

class ReporteDAO {

    /**
     * Lista las materias activas visibles para el usuario.
     *
     * @return Materia[]
     */
    public static function listarMateriasActivas(int $idUsuario, string $rol): array {
        $pdo = Conexion::conectar();

        if ($rol === 'DOCENTE') {
            $sql = "SELECT m.id, m.codigo, m.nombre, m.periodo, m.id_docente, m.activa,
                           COALESCE(u.nombre_completo, 'Sin docente') AS docente
                    FROM materias m
                    LEFT JOIN usuarios u ON m.id_docente = u.id
                    WHERE m.activa = true AND m.id_docente = :id_docente
                    ORDER BY m.codigo ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_docente' => $idUsuario]);
        } else {
            $sql = "SELECT m.id, m.codigo, m.nombre, m.periodo, m.id_docente, m.activa,
                           COALESCE(u.nombre_completo, 'Sin docente') AS docente
                    FROM materias m
                    LEFT JOIN usuarios u ON m.id_docente = u.id
                    WHERE m.activa = true
                    ORDER BY m.codigo ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

        return array_map(
            static fn(array $fila): Materia => Materia::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Obtiene los 3 contadores principales del dashboard.
     */
    public static function obtenerMetricasDashboard(int $idUsuario, string $rol, string $fechaHoy): array {
        $pdo = Conexion::conectar();

        if ($rol === 'DOCENTE') {
            $stmt1 = $pdo->prepare(
                "SELECT COUNT(DISTINCT mat.id_estudiante)
                 FROM matriculas mat
                 INNER JOIN materias m ON m.id = mat.id_materia
                 WHERE m.activa = true AND m.id_docente = :id_docente"
            );
            $stmt1->execute([':id_docente' => $idUsuario]);
            $totalMatriculados = (int)$stmt1->fetchColumn();

            $stmt2 = $pdo->prepare(
                "SELECT COUNT(*) FROM materias WHERE activa = true AND id_docente = :id_docente"
            );
            $stmt2->execute([':id_docente' => $idUsuario]);
            $totalMaterias = (int)$stmt2->fetchColumn();

            $stmt3 = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM asistencias a
                 INNER JOIN materias m ON m.id = a.id_materia
                 WHERE a.fecha = :hoy AND m.activa = true AND m.id_docente = :id_docente"
            );
            $stmt3->execute([':hoy' => $fechaHoy, ':id_docente' => $idUsuario]);
            $asistenciasHoy = (int)$stmt3->fetchColumn();
        } else {
            $totalMatriculados = (int)$pdo->query(
                "SELECT COUNT(DISTINCT id_estudiante) FROM matriculas"
            )->fetchColumn();

            $totalMaterias = (int)$pdo->query(
                "SELECT COUNT(*) FROM materias WHERE activa = true"
            )->fetchColumn();

            $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE fecha = :hoy");
            $stmt3->execute([':hoy' => $fechaHoy]);
            $asistenciasHoy = (int)$stmt3->fetchColumn();
        }

        return [
            'fecha_hoy'         => $fechaHoy,
            'total_estudiantes' => $totalMatriculados,
            'total_materias'    => $totalMaterias,
            'asistencias_hoy'   => $asistenciasHoy
        ];
    }

    /**
     * Obtiene los datos de una materia junto con el nombre de su docente.
     */
    public static function obtenerMateriaConDocente(int $idMateria): ?Materia {
        $pdo = Conexion::conectar();

        $sql = "SELECT m.id, m.codigo, m.nombre, m.periodo, m.id_docente, m.activa,
                       COALESCE(u.nombre_completo, 'Sin asignar') AS docente
                FROM materias m
                LEFT JOIN usuarios u ON m.id_docente = u.id
                WHERE m.id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idMateria]);
        $fila = $stmt->fetch();

        return $fila ? Materia::fromArray($fila) : null;
    }

    /**
     * Obtiene las fechas distintas en las que se impartió clase.
     */
    public static function obtenerFechasImpartidas(int $idMateria, string $parcial): array {
        $pdo = Conexion::conectar();

        if ($parcial === 'Reporte Semestre') {
            $sql = "SELECT DISTINCT TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, parcial
                    FROM asistencias
                    WHERE id_materia = :id_materia
                    ORDER BY parcial ASC, fecha ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_materia' => $idMateria]);
        } else {
            $sql = "SELECT DISTINCT TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, :parcial_val AS parcial
                    FROM asistencias
                    WHERE id_materia = :id_materia AND parcial = :parcial
                    ORDER BY fecha ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_materia'  => $idMateria,
                ':parcial'     => $parcial,
                ':parcial_val' => $parcial
            ]);
        }

        return $stmt->fetchAll();
    }

    /**
     * Obtiene los estudiantes matriculados en la asignatura.
     *
     * @return Estudiante[]
     */
    public static function obtenerEstudiantesMatriculados(int $idMateria): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT e.id, e.uid_rfid, e.cedula, e.nombres, e.apellidos, COALESCE(e.carrera, 'Software') AS carrera
                FROM matriculas mat
                INNER JOIN estudiantes e ON e.id = mat.id_estudiante
                WHERE mat.id_materia = :id_materia
                ORDER BY e.apellidos ASC, e.nombres ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_materia' => $idMateria]);

        return array_map(
            static fn(array $fila): Estudiante => Estudiante::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Obtiene todos los registros de asistencia para el consolidado.
     */
    public static function obtenerRegistrosAsistencia(int $idMateria, string $parcial): array {
        $pdo = Conexion::conectar();

        if ($parcial === 'Reporte Semestre') {
            $sql = "SELECT id_estudiante, TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, estado
                    FROM asistencias
                    WHERE id_materia = :id_materia";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_materia' => $idMateria]);
        } else {
            $sql = "SELECT id_estudiante, TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, estado
                    FROM asistencias
                    WHERE id_materia = :id_materia AND parcial = :parcial";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_materia' => $idMateria,
                ':parcial'    => $parcial
            ]);
        }

        return $stmt->fetchAll();
    }
}
