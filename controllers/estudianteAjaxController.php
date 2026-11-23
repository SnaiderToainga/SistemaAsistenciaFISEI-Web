<?php
/**
 * ==============================================================================
 * Controlador AJAX para Gestión de Estudiantes y Matriculación
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Responde exclusivamente en formato JSON estructurado:
 * { "status": "ok"|"error"|"not_found", "mensaje": "...", "data": ... }
 */

// Cabeceras estrictas para respuestas JSON limpias
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../models/estudianteModel.php';

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

    switch ($accion) {

        // 1. Obtener la lista de todos los estudiantes con sus materias
        case 'listar':
            $estudiantes = EstudianteModel::listarEstudiantes();
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Lista de estudiantes recuperada exitosamente',
                'data'    => $estudiantes
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 2. Obtener el catálogo de asignaturas activas para los checkboxes
        case 'materias':
            $materias = EstudianteModel::listarMateriasActivas();
            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Catálogo de materias activas obtenido',
                'data'    => $materias
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 3. Registrar nuevo estudiante y sus matrículas
        case 'guardar':
            $datos = [
                'uid_rfid'  => $input['uid_rfid']  ?? '',
                'cedula'    => $input['cedula']    ?? '',
                'nombres'   => $input['nombres']   ?? '',
                'apellidos' => $input['apellidos'] ?? '',
                'carrera'   => $input['carrera']   ?? 'Software'
            ];

            // Manejo de materias seleccionadas (array o cadena separada por comas)
            $materiasIds = [];
            if (isset($input['materias'])) {
                if (is_array($input['materias'])) {
                    $materiasIds = array_map('intval', $input['materias']);
                } elseif (is_string($input['materias'])) {
                    $materiasIds = array_filter(array_map('intval', explode(',', $input['materias'])));
                }
            }

            $nuevoEstudiante = EstudianteModel::guardarEstudiante($datos, $materiasIds);

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Estudiante registrado y matriculado correctamente',
                'data'    => $nuevoEstudiante
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 4. Modificar estudiante existente y sincronizar sus materias
        case 'modificar':
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                throw new Exception("Identificador de estudiante inválido.");
            }

            $datos = [
                'uid_rfid'  => $input['uid_rfid']  ?? '',
                'cedula'    => $input['cedula']    ?? '',
                'nombres'   => $input['nombres']   ?? '',
                'apellidos' => $input['apellidos'] ?? '',
                'carrera'   => $input['carrera']   ?? 'Software'
            ];

            $materiasIds = [];
            if (isset($input['materias'])) {
                if (is_array($input['materias'])) {
                    $materiasIds = array_map('intval', $input['materias']);
                } elseif (is_string($input['materias'])) {
                    $materiasIds = array_filter(array_map('intval', explode(',', $input['materias'])));
                }
            }

            EstudianteModel::modificarEstudiante($id, $datos, $materiasIds);

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Datos del estudiante y matrículas actualizados correctamente',
                'data'    => ['id' => $id]
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 5. Eliminar estudiante
        case 'eliminar':
            $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
            if ($id <= 0) {
                throw new Exception("Identificador de estudiante inválido.");
            }

            EstudianteModel::eliminarEstudiante($id);

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Estudiante eliminado satisfactoriamente',
                'data'    => null
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 6. Búsqueda instantánea por Llavero RFID (Enter emulado por lector USB)
        case 'buscar_rfid':
            $uid = isset($input['uid_rfid']) ? (string)$input['uid_rfid'] : (isset($_GET['uid_rfid']) ? (string)$_GET['uid_rfid'] : '');
            $uid = trim($uid);

            if (empty($uid)) {
                throw new Exception("El UID de llavero RFID no puede estar vacío.");
            }

            $estudiante = EstudianteModel::buscarPorRfid($uid);

            if ($estudiante) {
                echo json_encode([
                    'status'  => 'ok',
                    'mensaje' => 'Estudiante localizado por RFID',
                    'data'    => $estudiante
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'status'  => 'not_found',
                    'mensaje' => 'Llavero no registrado. Ingrese los datos para el nuevo estudiante.',
                    'data'    => null
                ], JSON_UNESCAPED_UNICODE);
            }
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

