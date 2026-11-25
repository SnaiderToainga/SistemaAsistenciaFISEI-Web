<?php
/**
 * Modelo de Reportes y Dashboard FISEI.
 * Aplica las reglas de cálculo de asistencia y delega SQL/PDO a ReporteDAO.
 * Regla de cálculo:
 * - PRESENTE = 1.0 | JUSTIFICADO = 1.0 | ATRASO = 0.5 | FALTA = 0.0
 * - >= 80%: APROBADO | 70% - 79%: EN RIESGO | < 70%: REPROBADO
 */

date_default_timezone_set('America/Guayaquil');

require_once __DIR__ . '/../dao/ReporteDAO.php';

class ReporteModel {

    /**
     * Lista las materias activas según el rol del usuario.
     */
    public static function listarMaterias($idUsuario, $rol) {
        $materias = ReporteDAO::listarMateriasActivas((int)$idUsuario, (string)$rol);

        return array_map(static function (Materia $m): array {
            return [
                'id'      => $m->getId(),
                'codigo'  => $m->getCodigo(),
                'nombre'  => $m->getNombre(),
                'periodo' => $m->getPeriodo(),
                'docente' => $m->getDocente()
            ];
        }, $materias);
    }

    /**
     * Contadores en tiempo real para el Dashboard (inicio.php).
     */
    public static function obtenerDashboard($idUsuario, $rol) {
        return ReporteDAO::obtenerMetricasDashboard((int)$idUsuario, (string)$rol, date('Y-m-d'));
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

        // 1. Obtener datos de la materia y validar permiso del docente
        $materiaEntidad = ReporteDAO::obtenerMateriaConDocente($idMateria);
        if (!$materiaEntidad) {
            throw new Exception("La asignatura seleccionada no existe.");
        }

        if ($rol === 'DOCENTE' && (int)$materiaEntidad->getIdDocente() !== (int)$idUsuario) {
            throw new Exception("No tiene permisos para ver el reporte de esta asignatura.");
        }

        $materia = [
            'id'         => $materiaEntidad->getId(),
            'codigo'     => $materiaEntidad->getCodigo(),
            'nombre'     => $materiaEntidad->getNombre(),
            'periodo'    => $materiaEntidad->getPeriodo(),
            'id_docente' => $materiaEntidad->getIdDocente(),
            'docente'    => $materiaEntidad->getDocente()
        ];

        // 2. Obtener fechas distintas de clases impartidas
        $fechas = [];
        $fechasParcial = [];

        foreach (ReporteDAO::obtenerFechasImpartidas($idMateria, $parcial) as $fila) {
            if (!isset($fechasParcial[$fila['fecha']])) {
                $fechas[] = $fila['fecha'];
                $fechasParcial[$fila['fecha']] = $fila['parcial'];
            }
        }
        $totalClases = count($fechas);

        // 3. Obtener estudiantes matriculados en la materia
        $listaEstudiantes = ReporteDAO::obtenerEstudiantesMatriculados($idMateria);

        // 4. Obtener todas las asistencias de la materia
        $asistenciasPorAlumno = [];
        foreach (ReporteDAO::obtenerRegistrosAsistencia($idMateria, $parcial) as $a) {
            $idEst = (int)$a['id_estudiante'];
            $asistenciasPorAlumno[$idEst][$a['fecha']] = $a['estado'];
        }

        // 5. Calcular porcentaje y estado académico de cada estudiante
        $resultadoEstudiantes = [];
        $aprobados = 0;
        $enRiesgo = 0;
        $reprobados = 0;

        foreach ($listaEstudiantes as $est) {
            $idEst = (int)$est->getId();
            $presentes = 0;
            $atrasos = 0;
            $faltas = 0;
            $justificados = 0;
            $detalleFechas = [];

            foreach ($fechas as $f) {
                $estado = $asistenciasPorAlumno[$idEst][$f] ?? '';
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
                'cedula'              => $est->getCedula(),
                'nombres'             => $est->getNombres(),
                'apellidos'           => $est->getApellidos(),
                'carrera'             => $est->getCarrera(),
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
