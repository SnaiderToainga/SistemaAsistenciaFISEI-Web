<?php
/**
 * Modelo de Reportes y Dashboard FISEI
 * Regla de cálculo:
 * - PRESENTE = 1.0 | JUSTIFICADO = 1.0 | ATRASO = 0.5 | FALTA = 0.0
 * - >= 80%: APROBADO | 70% - 79%: EN RIESGO | < 70%: REPROBADO
 */

date_default_timezone_set('America/Guayaquil');

require_once __DIR__ . '/../config/database.php';

class ReporteModel {

    /**
     * Lista las materias activas según el rol del usuario.
     */
    public static function listarMaterias($idUsuario, $rol) {
        $pdo = Conexion::conectar();

        if ($rol === 'DOCENTE') {
            $sql = "SELECT m.id, m.codigo, m.nombre, m.periodo,
                           COALESCE(u.nombre_completo, 'Sin docente') AS docente
                    FROM materias m
                    LEFT JOIN usuarios u ON m.id_docente = u.id
                    WHERE m.activa = true AND m.id_docente = :id_docente
                    ORDER BY m.codigo ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_docente' => $idUsuario]);
        } else {
            $sql = "SELECT m.id, m.codigo, m.nombre, m.periodo,
                           COALESCE(u.nombre_completo, 'Sin docente') AS docente
                    FROM materias m
                    LEFT JOIN usuarios u ON m.id_docente = u.id
                    WHERE m.activa = true
                    ORDER BY m.codigo ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Contadores en tiempo real para el Dashboard (inicio.php):
     * - Total alumnos matriculados
     * - Total materias activas
     * - Asistencias registradas hoy
     */
    public static function obtenerDashboard($idUsuario, $rol) {
        $pdo = Conexion::conectar();
        $fechaHoy = date('Y-m-d');

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
     * Calcula el consolidado de asistencia FISEI por materia y parcial.
     */
    public static function obtenerConsolidado($idMateria, $parcial, $idUsuario, $rol) {
        $idMateria = (int)$idMateria;
        if ($idMateria <= 0) {
            throw new Exception("Seleccione una asignatura válida.");
        }

        if ($parcial !== 'Parcial 1' && $parcial !== 'Parcial 2' && $parcial !== 'Reporte Semestre') {
            $parcial = 'Parcial 1';
        }

        $pdo = Conexion::conectar();

        // 1. Obtener datos de la materia y validar permiso del docente
        $sqlMat = "SELECT m.id, m.codigo, m.nombre, m.periodo, m.id_docente,
                          COALESCE(u.nombre_completo, 'Sin asignar') AS docente
                   FROM materias m
                   LEFT JOIN usuarios u ON m.id_docente = u.id
                   WHERE m.id = :id";
        $stmtMat = $pdo->prepare($sqlMat);
        $stmtMat->execute([':id' => $idMateria]);
        $materia = $stmtMat->fetch();

        if (!$materia) {
            throw new Exception("La asignatura seleccionada no existe.");
        }

        if ($rol === 'DOCENTE' && (int)$materia['id_docente'] !== (int)$idUsuario) {
            throw new Exception("No tiene permisos para ver el reporte de esta asignatura.");
        }

        // 2. Obtener fechas distintas de clases impartidas
        $fechas = [];
        $fechasParcial = [];

        if ($parcial === 'Reporte Semestre') {
            $sqlFechas = "SELECT DISTINCT TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, parcial
                          FROM asistencias
                          WHERE id_materia = :id_materia
                          ORDER BY parcial ASC, fecha ASC";
            $stmtFechas = $pdo->prepare($sqlFechas);
            $stmtFechas->execute([':id_materia' => $idMateria]);

            foreach ($stmtFechas->fetchAll() as $fila) {
                if (!isset($fechasParcial[$fila['fecha']])) {
                    $fechas[] = $fila['fecha'];
                    $fechasParcial[$fila['fecha']] = $fila['parcial'];
                }
            }
        } else {
            $sqlFechas = "SELECT DISTINCT TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha
                          FROM asistencias
                          WHERE id_materia = :id_materia AND parcial = :parcial
                          ORDER BY fecha ASC";
            $stmtFechas = $pdo->prepare($sqlFechas);
            $stmtFechas->execute([
                ':id_materia' => $idMateria,
                ':parcial'    => $parcial
            ]);

            foreach ($stmtFechas->fetchAll() as $fila) {
                $fechas[] = $fila['fecha'];
                $fechasParcial[$fila['fecha']] = $parcial;
            }
        }
        $totalClases = count($fechas);

        // 3. Obtener estudiantes matriculados en la materia
        $sqlEst = "SELECT e.id, e.cedula, e.nombres, e.apellidos, e.carrera
                   FROM matriculas mat
                   INNER JOIN estudiantes e ON e.id = mat.id_estudiante
                   WHERE mat.id_materia = :id_materia
                   ORDER BY e.apellidos ASC, e.nombres ASC";
        $stmtEst = $pdo->prepare($sqlEst);
        $stmtEst->execute([':id_materia' => $idMateria]);
        $listaEstudiantes = $stmtEst->fetchAll();

        // 4. Obtener todas las asistencias de la materia
        if ($parcial === 'Reporte Semestre') {
            $sqlAsist = "SELECT id_estudiante, TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, estado
                         FROM asistencias
                         WHERE id_materia = :id_materia";
            $stmtAsist = $pdo->prepare($sqlAsist);
            $stmtAsist->execute([':id_materia' => $idMateria]);
        } else {
            $sqlAsist = "SELECT id_estudiante, TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha, estado
                         FROM asistencias
                         WHERE id_materia = :id_materia AND parcial = :parcial";
            $stmtAsist = $pdo->prepare($sqlAsist);
            $stmtAsist->execute([
                ':id_materia' => $idMateria,
                ':parcial'    => $parcial
            ]);
        }

        // Guardar en un arreglo [id_estudiante][fecha] = estado
        $asistenciasPorAlumno = [];
        foreach ($stmtAsist->fetchAll() as $a) {
            $idEst = (int)$a['id_estudiante'];
            $asistenciasPorAlumno[$idEst][$a['fecha']] = $a['estado'];
        }

        // 5. Calcular porcentaje y estado académico de cada estudiante
        $resultadoEstudiantes = [];
        $aprobados = 0;
        $enRiesgo = 0;
        $reprobados = 0;

        foreach ($listaEstudiantes as $est) {
            $idEst = (int)$est['id'];
            $presentes = 0;
            $atrasos = 0;
            $faltas = 0;
            $justificados = 0;
            $detalleFechas = [];

            foreach ($fechas as $f) {
                $estado = isset($asistenciasPorAlumno[$idEst][$f]) ? $asistenciasPorAlumno[$idEst][$f] : '';
                $detalleFechas[$f] = $estado;

                if ($estado === 'PRESENTE') {
                    $presentes++;
                } elseif ($estado === 'ATRASO') {
                    $atrasos++;
                } elseif ($estado === 'FALTA') {
                    $faltas++;
                } elseif ($estado === 'JUSTIFICADO') {
                    $justificados++;
                }
            }

            // Regla FISEI: Presente = 1, Justificado = 1, Atraso = 0.5, Falta = 0
            $validas = $presentes + $justificados + ($atrasos * 0.5);

            if ($totalClases > 0) {
                $porcentaje = round(($validas / $totalClases) * 100, 2);
                if ($porcentaje > 100) {
                    $porcentaje = 100;
                }

                if ($porcentaje >= 80) {
                    $estadoAcademico = 'APROBADO';
                    $aprobados++;
                } elseif ($porcentaje >= 70) {
                    $estadoAcademico = 'EN RIESGO';
                    $enRiesgo++;
                } else {
                    $estadoAcademico = 'REPROBADO';
                    $reprobados++;
                }
            } else {
                $porcentaje = 0;
                $estadoAcademico = 'SIN REGISTROS';
            }

            $resultadoEstudiantes[] = [
                'id_estudiante'       => $idEst,
                'cedula'              => $est['cedula'],
                'nombres'             => $est['nombres'],
                'apellidos'           => $est['apellidos'],
                'carrera'             => $est['carrera'],
                'presentes'           => $presentes,
                'atrasos'             => $atrasos,
                'faltas'              => $faltas,
                'justificados'        => $justificados,
                'asistencias_validas' => $validas,
                'total_clases'        => $totalClases,
                'porcentaje'          => $porcentaje,
                'estado_academico'    => $estadoAcademico,
                'detalle_fechas'      => $detalleFechas
            ];
        }

        return [
            'materia'        => $materia,
            'parcial'        => $parcial,
            'fechas'         => $fechas,
            'fechas_parcial' => $fechasParcial,
            'resumen'        => [
                'total_estudiantes' => count($resultadoEstudiantes),
                'total_clases'      => $totalClases,
                'aprobados'         => $aprobados,
                'en_riesgo'         => $enRiesgo,
                'reprobados'        => $reprobados
            ],
            'estudiantes' => $resultadoEstudiantes
        ];
    }
}
