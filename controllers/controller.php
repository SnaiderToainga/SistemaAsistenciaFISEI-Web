<?php
/**
 * ==============================================================================
 * Controlador Principal MVC
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 */

class controllersMvc {

    /**
     * Carga y renderiza la plantilla principal de la aplicación.
     */
    public function gettemplate(): void {
        $mvc = $this;
        include __DIR__ . "/../views/template.php";
    }

    /**
     * Interpreta la variable $_GET['action'] y despacha el módulo correspondiente.
     * Si no se especifica ninguna, usa 'matriculacion' por defecto para pruebas directas.
     */
    public function enlacesPaginasControllers(): void {
        $action = isset($_GET['action']) && !empty(trim($_GET['action'])) 
            ? trim($_GET['action']) 
            : 'matriculacion';

        // Manejo de cierre de sesión
        if ($action === 'salir') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
        }

        $modulo = EnlacesPaginas::enlacesPaginasModel($action);
        include $modulo;
    }
}
