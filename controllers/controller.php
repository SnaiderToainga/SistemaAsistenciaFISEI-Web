<?php
/**
 * ==============================================================================
 * Controlador Principal MVC
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Incluye el Middleware de Sesión que protege las rutas según el rol del usuario.
 */

class controllersMvc {

    /**
     * Acciones accesibles sin haber iniciado sesión.
     */
    public const ACCIONES_PUBLICAS = ['login', 'salir'];

    /**
     * Acciones administrativas restringidas exclusivamente al rol ADMIN.
     * El rol DOCENTE no las ve en el menú ni puede acceder por URL.
     */
    public const ACCIONES_ADMIN = ['materias', 'matriculacion'];

    /**
     * Carga y renderiza la plantilla principal de la aplicación.
     * El middleware se ejecuta antes de enviar cualquier salida HTML
     * para poder redirigir mediante cabeceras HTTP.
     */
    public function gettemplate(): void {
        $this->middlewareSesion();

        $mvc = $this;
        include __DIR__ . "/../views/template.php";
    }

    /**
     * Interpreta la variable $_GET['action'] y despacha el módulo correspondiente.
     */
    public function enlacesPaginasControllers(): void {
        $action = self::accionSolicitada();

        $modulo = EnlacesPaginas::enlacesPaginasModel($action);
        include $modulo;
    }

    /**
     * Resuelve la acción solicitada. Si no se especifica ninguna, usa
     * 'inicio' para usuarios autenticados y 'login' para visitantes.
     *
     * @return string Acción normalizada
     */
    public static function accionSolicitada(): string {
        $action = isset($_GET['action']) && is_string($_GET['action']) ? trim($_GET['action']) : '';

        if ($action === '') {
            $action = self::sesionActiva() ? 'inicio' : 'login';
        }

        return $action;
    }

    // =========================================================================
    // MIDDLEWARE DE SESIÓN
    // =========================================================================

    /**
     * Protege las rutas del sistema:
     * - Sin $_SESSION['id_usuario'] redirige a ?action=login.
     * - Un usuario autenticado que visita el login es enviado a ?action=inicio.
     * - El rol DOCENTE no puede acceder a las acciones administrativas.
     * - La acción 'salir' destruye la sesión antes de renderizar la plantilla.
     */
    private function middlewareSesion(): void {
        self::iniciarSesion();
        $action = self::accionSolicitada();

        // Cierre de sesión (antes de imprimir el menú para que ya no se muestre)
        if ($action === 'salir') {
            self::cerrarSesion();
            return;
        }

        // Visitante sin sesión intentando acceder a una página protegida
        if (!self::sesionActiva()) {
            if (!in_array($action, self::ACCIONES_PUBLICAS, true)) {
                self::redirigir('login');
            }
            return;
        }

        // Usuario autenticado intentando ver nuevamente el login
        if ($action === 'login') {
            self::redirigir('inicio');
        }

        // Acción inexistente: se envía al inicio en lugar de la vista por defecto
        $modulo = EnlacesPaginas::enlacesPaginasModel($action);
        if (basename($modulo, '.php') !== $action) {
            self::redirigir('inicio');
        }

        // Restricción de opciones administrativas por rol
        if (in_array($action, self::ACCIONES_ADMIN, true) && !self::esAdmin()) {
            self::redirigir('inicio', ['acceso' => 'denegado']);
        }
    }

    /**
     * Middleware para controladores AJAX: responde en JSON con 401/403 y
     * detiene la ejecución si no existe sesión o el rol no está autorizado.
     *
     * @param array $rolesPermitidos Roles autorizados (vacío = cualquier usuario autenticado)
     */
    public static function verificarSesionAjax(array $rolesPermitidos = []): void {
        self::iniciarSesion();

        if (!self::sesionActiva()) {
            self::responderJsonError(401, 'Su sesión ha expirado. Inicie sesión nuevamente.');
        }

        if (!empty($rolesPermitidos) && !in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
            self::responderJsonError(403, 'No tiene permisos para realizar esta operación.');
        }
    }

    // =========================================================================
    // UTILIDADES DE SESIÓN
    // =========================================================================

    /**
     * Inicia la sesión PHP si aún no ha sido iniciada.
     */
    public static function iniciarSesion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Indica si existe un usuario autenticado en la sesión actual.
     */
    public static function sesionActiva(): bool {
        return !empty($_SESSION['id_usuario']);
    }

    /**
     * Indica si el usuario autenticado tiene rol ADMIN.
     */
    public static function esAdmin(): bool {
        return self::sesionActiva() && ($_SESSION['rol'] ?? '') === 'ADMIN';
    }

    /**
     * Indica si el rol del usuario autenticado puede acceder a una acción.
     *
     * @param string $action Acción del menú
     */
    public static function puedeAcceder(string $action): bool {
        if (in_array($action, self::ACCIONES_ADMIN, true)) {
            return self::esAdmin();
        }
        return self::sesionActiva();
    }

    /**
     * Destruye la sesión actual y su cookie.
     */
    private static function cerrarSesion(): void {
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

    /**
     * Redirige a una acción del sistema y finaliza la ejecución.
     *
     * @param string $action Acción destino
     * @param array $parametros Parámetros GET adicionales
     */
    private static function redirigir(string $action, array $parametros = []): void {
        $query = http_build_query(array_merge(['action' => $action], $parametros));
        header("Location: index.php?{$query}");
        exit;
    }

    /**
     * Envía una respuesta JSON de error con el formato estándar del sistema y finaliza.
     */
    private static function responderJsonError(int $codigo, string $mensaje): void {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status'  => 'error',
            'mensaje' => $mensaje,
            'data'    => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
