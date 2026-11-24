<?php
/**
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Universidad Técnica de Ambato (UTA)
 * ==============================================================================
 * Arquitectura: MVC Pura en PHP 8 (Sin frameworks)
 * Punto de entrada principal de la aplicación.
 */

// Iniciar sesión de forma segura si aún no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Inclusión de los controladores y modelos principales del MVC
require_once __DIR__ . '/controllers/controller.php';
require_once __DIR__ . '/models/model.php';

// Instanciar el controlador MVC y cargar la plantilla principal
$mvc = new controllersMvc();
$mvc->gettemplate();
