<?php
/**
 * ==============================================================================
 * Modelo de Autenticación de Usuarios (Login)
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Consultas preparadas con PDO para PostgreSQL de Supabase.
 * Las contraseñas se almacenan cifradas con password_hash() (bcrypt).
 */

require_once __DIR__ . '/../config/database.php';

class LoginModel {

    /**
     * Hash bcrypt ficticio utilizado cuando el usuario no existe, para que el
     * tiempo de respuesta sea equivalente y no revele qué usuarios existen.
     */
    private const HASH_FICTICIO = '$2y$10$snEkd5Jh6Zmf7IDHrB7mg.kVFxuZ5Zz9.mYf4LuTiJrVNbBlDcyU.';

    /**
     * Busca un usuario por su nombre de usuario institucional.
     *
     * @param string $username Nombre de usuario (ej: admin.fisei)
     * @return array|null Datos completos del usuario (incluye hash) o null si no existe
     */
    public static function buscarUsuarioPorUsername(string $username): ?array {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $pdo = Conexion::conectar();

        $sql = "SELECT id, username, password, nombre_completo, rol, activo
                FROM usuarios
                WHERE username = :username
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Valida las credenciales de acceso usando password_verify().
     *
     * @param string $username Nombre de usuario
     * @param string $password Contraseña en texto plano ingresada en el formulario
     * @return array|null Datos del usuario autenticado (sin hash) o null si las credenciales no coinciden
     * @throws Exception Si la cuenta se encuentra desactivada
     */
    public static function validarCredenciales(string $username, string $password): ?array {
        $usuario = self::buscarUsuarioPorUsername($username);

        // Verificar siempre un hash (real o ficticio) para evitar enumeración por tiempo
        $hash = $usuario['password'] ?? self::HASH_FICTICIO;
        $passwordValida = password_verify($password, $hash);

        if (!$usuario || !$passwordValida) {
            return null;
        }

        if (!$usuario['activo']) {
            throw new Exception("Su cuenta se encuentra desactivada. Contacte al administrador de FISEI.");
        }

        // Actualizar el hash si el algoritmo por defecto de PHP ha cambiado
        if (password_needs_rehash($usuario['password'], PASSWORD_DEFAULT)) {
            self::actualizarPassword((int)$usuario['id'], $password);
        }

        unset($usuario['password']);
        return $usuario;
    }

    /**
     * Actualiza la contraseña de un usuario cifrándola con password_hash().
     *
     * @param int $id ID del usuario
     * @param string $password Nueva contraseña en texto plano
     * @return bool True si se actualizó
     */
    public static function actualizarPassword(int $id, string $password): bool {
        $pdo = Conexion::conectar();

        $sql = "UPDATE usuarios SET password = :password WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':id'       => $id
        ]);
    }
}
