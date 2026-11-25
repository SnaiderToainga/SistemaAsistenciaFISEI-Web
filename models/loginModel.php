<?php
/**
 * Modelo de Autenticación de Usuarios (Login).
 * Delega la persistencia a UsuarioDAO y trabaja con la entidad Usuario.
 */

require_once __DIR__ . '/../dao/UsuarioDAO.php';

class LoginModel {

    /**
     * Hash bcrypt ficticio utilizado cuando el usuario no existe, para que el
     * tiempo de respuesta sea equivalente y no revele qué usuarios existen.
     */
    private const HASH_FICTICIO = '$2y$10$snEkd5Jh6Zmf7IDHrB7mg.kVFxuZ5Zz9.mYf4LuTiJrVNbBlDcyU.';

    /**
     * Busca un usuario por su nombre de usuario institucional.
     */
    public static function buscarUsuarioPorUsername(string $username): ?array {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $usuario = UsuarioDAO::buscarPorUsername($username);
        return $usuario ? $usuario->toArray(true) : null;
    }

    /**
     * Valida las credenciales de acceso usando password_verify().
     *
     * @throws Exception Si la cuenta se encuentra desactivada
     */
    public static function validarCredenciales(string $username, string $password): ?array {
        $username = trim($username);
        $usuario  = $username !== '' ? UsuarioDAO::buscarPorUsername($username) : null;

        // Verificar siempre un hash (real o ficticio) para evitar enumeración por tiempo
        $hash = $usuario ? ($usuario->getPassword() ?? self::HASH_FICTICIO) : self::HASH_FICTICIO;
        $passwordValida = password_verify($password, $hash);

        if (!$usuario || !$passwordValida) {
            return null;
        }

        if (!$usuario->isActivo()) {
            throw new Exception("Su cuenta se encuentra desactivada. Contacte al administrador de FISEI.");
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            self::actualizarPassword((int)$usuario->getId(), $password);
        }

        return $usuario->toArray(false);
    }

    /**
     * Actualiza la contraseña de un usuario cifrándola con password_hash().
     */
    public static function actualizarPassword(int $id, string $password): bool {
        return UsuarioDAO::actualizarPassword($id, password_hash($password, PASSWORD_DEFAULT));
    }
}
