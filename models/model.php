<?php
/**
 * ==============================================================================
 * Modelo de Enlaces y Navegación del Sistema MVC
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 */

class EnlacesPaginas {

    /**
     * Valida y resuelve la ruta del módulo según la acción solicitada.
     * Aplica lista blanca estricta para garantizar seguridad contra LFI.
     *
     * @param string $action Nombre de la acción o vista solicitada
     * @return string Ruta relativa al archivo de vista del módulo
     */
    public static function enlacesPaginasModel(string $action): string {
        $paginasPermitidas = [
            'inicio',
            'login',
            'materias',
            'matriculacion',
            'asistencia',
            'reportes',
            'salir'
        ];

        if (in_array($action, $paginasPermitidas, true)) {
            $archivoModulo = __DIR__ . "/../views/modules/{$action}.php";
            if (file_exists($archivoModulo)) {
                return $archivoModulo;
            }
        }

        // Por defecto temporalmente 'matriculacion' para pruebas directas
        return __DIR__ . "/../views/modules/matriculacion.php";
    }
}

