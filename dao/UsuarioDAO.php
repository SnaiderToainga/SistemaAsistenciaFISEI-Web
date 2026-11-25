<?php
/**
 * DAO de Usuarios (Docentes y Administradores).
 * Encapsula todas las consultas SQL y operaciones PDO sobre la tabla `usuarios`.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../entities/Usuario.php';

class UsuarioDAO {

    /**
     * Busca un usuario por su nombre de usuario institucional.
     */
    public static function buscarPorUsername(string $username): ?Usuario {
        $pdo = Conexion::conectar();

        $sql = "SELECT id, username, password, nombre_completo, rol, activo
                FROM usuarios
                WHERE username = :username
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $fila = $stmt->fetch();

        return $fila ? Usuario::fromArray($fila) : null;
    }

    /**
     * Actualiza el hash de contraseña de un usuario.
     */
    public static function actualizarPassword(int $id, string $passwordHash): bool {
        $pdo = Conexion::conectar();

        $sql = "UPDATE usuarios SET password = :password WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':password' => $passwordHash,
            ':id'       => $id
        ]);
    }

    /**
     * Lista todos los docentes activos ordenados alfabéticamente.
     *
     * @return Usuario[]
     */
    public static function listarDocentesActivos(): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT id, username, nombre_completo, rol, activo
                FROM usuarios
                WHERE rol = 'DOCENTE' AND activo = true
                ORDER BY nombre_completo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return array_map(
            static fn(array $fila): Usuario => Usuario::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Verifica si existe un docente activo por su ID.
     */
    public static function existeDocenteActivo(int $idDocente): bool {
        $pdo = Conexion::conectar();

        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND rol = 'DOCENTE' AND activo = true LIMIT 1");
        $stmt->execute([':id' => $idDocente]);

        return (bool)$stmt->fetch();
    }
}
