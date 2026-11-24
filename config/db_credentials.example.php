<?php
/**
 * Plantilla de credenciales locales para base de datos.
 * Copie este archivo como 'config/db_credentials.php' y complete con sus datos reales de Supabase.
 * Este archivo db_credentials.php está ignorado en .gitignore para seguridad.
 */

return [
    'host'     => 'aws-0-sa-east-1.pooler.supabase.com', // Host del Transaction Pooler de Supabase
    'port'     => '6543',                               // Puerto 6543 (Pooler) o 5432 (Directo)
    'dbname'   => 'postgres',                           // Nombre de la base de datos
    'user'     => 'postgres.tu_proyecto_ref',           // Usuario de Supabase
    'password' => 'TU_PASSWORD_AQUI'                    // Contraseña de base de datos de Supabase
];

