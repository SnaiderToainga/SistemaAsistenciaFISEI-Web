<?php

date_default_timezone_set('America/Guayaquil');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../models/asistenciaModel.php';
require_once __DIR__ . '/controller.php';

controllersMvc::verificarSesionAjax(['ADMIN', 'DOCENTE']);

try {
    $input = $_POST;
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonDecoded = json_decode($rawInput, true);
        if (is_array($jsonDecoded)) {
            $input = array_merge($input, $jsonDecoded);
        }
    }

    $accion = (string)($input['accion'] ?? $_GET['accion'] ?? '');
    $accionesEscritura = ['marcar_rfid', 'actualizar_estado', 'marcar_faltas'];

    if (in_array($accion, $accionesEscritura, true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    $idUsuario  = (int)($_SESSION['id_usuario'] ?? 0);
    $rolUsuario = (string)($_SESSION['rol'] ?? '');

    switch ($accion) {
        case 'materias':
            $materias = AsistenciaModel::listarMateriasDocente($idUsuario, $rolUsuario);
            echo json_encode(['status' => 'ok', 'mensaje' => 'Asignaturas cargadas', 'data' => $materias], JSON_UNESCAPED_UNICODE);
            break;

        case 'fechas':
            $idMateria = (int)($input['id_materia'] ?? $_GET['id_materia'] ?? 0);
            $parcial   = (string)($input['parcial'] ?? $_GET['parcial'] ?? 'Parcial 1');

            $fechas = AsistenciaModel::listarFechasRegistradas($idMateria, $parcial, $idUsuario, $rolUsuario);
            echo json_encode(['status' => 'ok', 'mensaje' => 'Fechas cargadas', 'data' => $fechas], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar':
            $idMateria = (int)($input['id_materia'] ?? $_GET['id_materia'] ?? 0);
            $fecha     = (string)($input['fecha'] ?? $_GET['fecha'] ?? date('Y-m-d'));
            $parcial   = (string)($input['parcial'] ?? $_GET['parcial'] ?? 'Parcial 1');

            $asistencias = AsistenciaModel::listarAsistencias($idMateria, $fecha, $parcial, $idUsuario, $rolUsuario);
            echo json_encode(['status' => 'ok', 'mensaje' => 'Lista cargada', 'data' => $asistencias], JSON_UNESCAPED_UNICODE);
            break;

        case 'marcar_rfid':
            $resultado = AsistenciaModel::registrarAsistenciaRfid(
                (string)($input['uid_rfid'] ?? ''),
                (int)($input['id_materia'] ?? 0),
                (string)($input['fecha'] ?? date('Y-m-d')),
                (string)($input['parcial'] ?? 'Parcial 1'),
                $idUsuario,
                $rolUsuario
            );

            echo json_encode([
                'status'  => $resultado['resultado'],
                'mensaje' => $resultado['mensaje'],
                'data'    => $resultado['data']
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_estado':
            $registro = AsistenciaModel::actualizarEstadoManual(
                (int)($input['id_estudiante'] ?? 0),
                (int)($input['id_materia'] ?? 0),
                (string)($input['fecha'] ?? date('Y-m-d')),
                (string)($input['parcial'] ?? 'Parcial 1'),
                (string)($input['estado'] ?? ''),
                isset($input['observacion']) ? (string)$input['observacion'] : null,
                $idUsuario,
                $rolUsuario
            );

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => "Estado actualizado a {$registro['estado']}.",
                'data'    => $registro
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'marcar_faltas':
            $marcados = AsistenciaModel::marcarFaltasPendientes(
                (int)($input['id_materia'] ?? 0),
                (string)($input['fecha'] ?? date('Y-m-d')),
                (string)($input['parcial'] ?? 'Parcial 1'),
                $idUsuario,
                $rolUsuario
            );

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => $marcados > 0
                    ? "Se registraron {$marcados} falta(s) pendiente(s)."
                    : "No hay estudiantes pendientes.",
                'data'    => ['marcados' => $marcados]
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception("Acción no válida.");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
}
