<?php
/**
 * ==============================================================================
 * Controlador AJAX para Gestión Dinámica de Asignaturas / Materias
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Acceso exclusivo para el rol ADMIN (verificado por el middleware de sesión).
 * Responde exclusivamente en formato JSON estructurado:
 * { "status": "ok"|"error", "mensaje": "...", "data": ... }
 */

// Cabeceras estrictas para respuestas JSON limpias
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../models/materiaModel.php';
require_once __DIR__ . '/controller.php';

// Middleware: solo administradores autenticados
controllersMvc::verificarSesionAjax(['ADMIN']);

try {
    // Captura de datos tanto de FormData ($_POST) como de cuerpo JSON (fetch)
    $input = $_POST;
    $rawInput = file_get_contents('php://input');

    if (!empty($rawInput)) {
        $jsonDecoded = json_decode($rawInput, true);
        if (is_array($jsonDecoded)) {
            $input = array_merge($input, $jsonDecoded);
        }
    }

    // Determinar la acción solicitada
    $accion = $input['accion'] ?? $_GET['accion'] ?? '';

    // Las acciones que modifican datos solo se aceptan por POST
    $accionesEscritura = ['guardar', 'modificar', 'cambiar_estado'];
    if (in_array($accion, $accionesEscritura, true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception("Método no permitido para la acción solicitada.");
    }

    switch ($accion) {

        // 1. Obtener el catálogo completo de asignaturas
        case 'listar':
            $materias = MateriaModel::listarMaterias();
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Lista de asignaturas recuperada exitosamente',
                'data'    => $materias
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 2. Obtener los docentes activos para el selector
        case 'docentes':
            $docentes = MateriaModel::listarDocentes();
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Lista de docentes activos obtenida',
                'data'    => $docentes
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 3. Registrar nueva asignatura
        case 'guardar':
            $datos = [
                'codigo'     => $input['codigo']     ?? '',
                'nombre'     => $input['nombre']     ?? '',
                'periodo'    => $input['periodo']    ?? '',
                'id_docente' => $input['id_docente'] ?? null,
                'activa'     => $input['activa']     ?? true
            ];

            $nuevaMateria = MateriaModel::guardarMateria($datos);

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Asignatura registrada correctamente',
                'data'    => $nuevaMateria
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 4. Modificar asignatura existente
        case 'modificar':
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                throw new Exception("Identificador de asignatura inválido.");
            }

            $datos = [
                'codigo'     => $input['codigo']     ?? '',
                'nombre'     => $input['nombre']     ?? '',
                'periodo'    => $input['periodo']    ?? '',
                'id_docente' => $input['id_docente'] ?? null,
                'activa'     => $input['activa']     ?? true
            ];

            MateriaModel::modificarMateria($id, $datos);

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Datos de la asignatura actualizados correctamente',
                'data'    => ['id' => $id]
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 5. Activar / desactivar asignatura
        case 'cambiar_estado':
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                throw new Exception("Identificador de asignatura inválido.");
            }

            $activa = filter_var($input['activa'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activa === null) {
                throw new Exception("Estado de asignatura inválido.");
            }

            MateriaModel::cambiarEstado($id, $activa);

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => $activa ? 'Asignatura activada correctamente' : 'Asignatura desactivada correctamente',
                'data'    => ['id' => $id, 'activa' => $activa]
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception("Acción no válida o no especificada.");
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'mensaje' => $e->getMessage(),
        'data'    => null
    ], JSON_UNESCAPED_UNICODE);
}
