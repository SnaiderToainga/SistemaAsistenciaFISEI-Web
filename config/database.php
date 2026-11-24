<?php
/**
 * ==============================================================================
 * Configuración de Conexión a Base de Datos PostgreSQL (Supabase)
 * ==============================================================================
 * Utiliza PDO para conectar con PostgreSQL en la nube de Supabase.
 * Diseñado para Transaction Pooler (puerto 6543) o conexión directa (puerto 5432).
 */

class Conexion {

    /**
     * Retorna una instancia activa de PDO configurada para PostgreSQL / Supabase.
     * 
     * @return PDO
     * @throws Exception Si la conexión no se puede establecer
     */
    public static function conectar(): PDO {
        /**
         * =====================================================================
         * PARÁMETROS DE CONEXIÓN A SUPABASE
         * =====================================================================
         * Obtener estos datos desde:
         * Supabase Dashboard -> Project Settings -> Database -> Connection string
         * 
         * NOTA: Para el Transaction Pooler (recomendado en entornos serverless/web):
         * Host:   aws-0-[region].pooler.supabase.com (ej: aws-0-sa-east-1.pooler.supabase.com)
         * Puerto: 6543
         * Usuario: postgres.[project-ref] (ej: postgres.abcdefghijklmno)
         * Base:   postgres
         * =====================================================================
         */
        $host     = 'aws-1-us-east-1.pooler.supabase.com'; // <-- TU HOST DE SUPABASE AQUÍ
        $port     = '6543';                               // <-- PUERTO (6543 Pooler / 5432 Directo)
        $dbname   = 'postgres';                           // <-- NOMBRE DE BASE DE DATOS
        $user     = 'postgres.xsivpupmzzpmaujzokmz';           // <-- TU USUARIO DE SUPABASE AQUÍ
        $password = 'r_Hcab4Pp2V@7p7';                   // <-- TU PASSWORD DE SUPABASE AQUÍ

        // Soporte para sobreescribir desde archivo local db_credentials.php (ignorado por Git)
        $credencialesFile = __DIR__ . '/db_credentials.php';
        if (file_exists($credencialesFile)) {
            $credenciales = require $credencialesFile;
            if (is_array($credenciales)) {
                $host     = $credenciales['host']     ?? $host;
                $port     = $credenciales['port']     ?? $port;
                $dbname   = $credenciales['dbname']   ?? $dbname;
                $user     = $credenciales['user']     ?? $user;
                $password = $credenciales['password'] ?? $password;
            }
        }

        // DSN para PostgreSQL con sslmode=require mandatorio en Supabase
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10
        ];

        try {
            return new PDO($dsn, $user, $password, $opciones);
        } catch (PDOException $e) {
            error_log("Error de conexión PostgreSQL: " . $e->getMessage());
            throw new Exception("Error al conectar con la base de datos PostgreSQL de Supabase: " . $e->getMessage());
        }
    }
}

