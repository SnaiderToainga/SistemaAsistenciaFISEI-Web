<?php
/**
 * Controlador AJAX para Dashboard y Reportes FISEI
 */

date_default_timezone_set('America/Guayaquil');

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../models/reporteModel.php';
require_once __DIR__ . '/controller.php';

controllersMvc::verificarSesionAjax(['ADMIN', 'DOCENTE']);

try {
    $accion     = isset($_GET['accion']) ? $_GET['accion'] : '';
    $idUsuario  = (int)$_SESSION['id_usuario'];
    $rolUsuario = $_SESSION['rol'];

    switch ($accion) {
        case 'dashboard':
            $datos = ReporteModel::obtenerDashboard($idUsuario, $rolUsuario);
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Indicadores cargados',
                'data'    => $datos
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'materias':
            $materias = ReporteModel::listarMaterias($idUsuario, $rolUsuario);
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Materias cargadas',
                'data'    => $materias
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'consolidado':
            $idMateria = isset($_GET['id_materia']) ? (int)$_GET['id_materia'] : 0;
            $parcial   = isset($_GET['parcial']) ? $_GET['parcial'] : 'Parcial 1';

            $consolidado = ReporteModel::obtenerConsolidado($idMateria, $parcial, $idUsuario, $rolUsuario);
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Consolidado generado',
                'data'    => $consolidado
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'mensaje' => $e->getMessage(),
        'data'    => null
    ], JSON_UNESCAPED_UNICODE);
}
