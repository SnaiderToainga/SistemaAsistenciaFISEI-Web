<?php
/**
 * ==============================================================================
 * Controlador AJAX de Autenticación (Login y Sesiones)
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Responde exclusivamente en formato JSON estructurado:
 * { "status": "ok"|"error", "mensaje": "...", "data": ... }
 */

// Cabeceras estrictas para respuestas JSON limpias
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../models/loginModel.php';
require_once __DIR__ . '/controller.php';

controllersMvc::iniciarSesion();

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

        // 1. Validar credenciales e iniciar sesión
        case 'ingresar':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new Exception("Método no permitido para el inicio de sesión.");
            }

            $username = isset($input['username']) ? trim((string)$input['username']) : '';
            $password = isset($input['password']) ? (string)$input['password'] : '';

            if ($username === '' || $password === '') {
                throw new Exception("Ingrese su usuario y contraseña.");
            }

            $usuario = LoginModel::validarCredenciales($username, $password);

            if (!$usuario) {
                http_response_code(401);
                echo json_encode([
                    'status'  => 'error',
                    'mensaje' => 'Usuario o contraseña incorrectos.',
                    'data'    => null
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            // Regenerar el ID de sesión para prevenir fijación de sesión
            session_regenerate_id(true);

            $_SESSION['id_usuario']      = (int)$usuario['id'];
            $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
            $_SESSION['rol']             = $usuario['rol'];
            $_SESSION['username']        = $usuario['username'];

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Bienvenido(a), ' . $usuario['nombre_completo'],
                'data'    => [
                    'nombre_completo' => $usuario['nombre_completo'],
                    'rol'             => $usuario['rol'],
                    'redireccion'     => 'index.php?action=inicio'
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 2. Consultar los datos de la sesión activa
        case 'sesion':
            controllersMvc::verificarSesionAjax();

            echo json_encode([
                'status'  => 'ok',
                'mensaje' => 'Sesión activa',
                'data'    => [
                    'id_usuario'      => $_SESSION['id_usuario'],
                    'nombre_completo' => $_SESSION['nombre_completo'],
                    'rol'             => $_SESSION['rol'],
                    'username'        => $_SESSION['username']
                ]
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
