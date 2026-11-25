<?php
/**
 * DAO de Materias (Asignaturas).
 * Encapsula todas las consultas SQL y operaciones PDO sobre la tabla `materias`.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../entities/Materia.php';

class MateriaDAO {

    /**
     * Lista todas las asignaturas con su docente y conteo de matriculados.
     *
     * @return Materia[]
     */
    public static function listarTodas(): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT
                    m.id,
                    m.codigo,
                    m.nombre,
                    m.periodo,
                    m.id_docente,
                    m.activa,
                    COALESCE(u.nombre_completo, '') AS docente,
                    (SELECT COUNT(*) FROM matriculas mat WHERE mat.id_materia = m.id) AS total_matriculados
                FROM materias m
                LEFT JOIN usuarios u ON m.id_docente = u.id
                ORDER BY m.activa DESC, m.codigo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return array_map(
            static fn(array $fila): Materia => Materia::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Verifica si existe una asignatura por su ID.
     */
    public static function existePorId(int $id): bool {
        $pdo = Conexion::conectar();

        $stmt = $pdo->prepare("SELECT id FROM materias WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetch();
    }

    /**
     * Verifica si existe otra asignatura con el mismo código (mayúsculas).
     */
    public static function existeCodigo(string $codigo, ?int $excluirId = null): bool {
        $pdo = Conexion::conectar();

        if ($excluirId !== null) {
            $stmt = $pdo->prepare("SELECT id FROM materias WHERE UPPER(codigo) = :codigo AND id != :id LIMIT 1");
            $stmt->execute([':codigo' => $codigo, ':id' => $excluirId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM materias WHERE UPPER(codigo) = :codigo LIMIT 1");
            $stmt->execute([':codigo' => $codigo]);
        }

        return (bool)$stmt->fetch();
    }

    /**
     * Inserta una nueva asignatura usando RETURNING id.
     */
    public static function insertar(Materia $materia): Materia {
        $pdo = Conexion::conectar();

        $sql = "INSERT INTO materias (codigo, nombre, periodo, id_docente, activa)
                VALUES (:codigo, :nombre, :periodo, :id_docente, :activa)
                RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo'     => $materia->getCodigo(),
            ':nombre'     => $materia->getNombre(),
            ':periodo'    => $materia->getPeriodo(),
            ':id_docente' => $materia->getIdDocente(),
            ':activa'     => $materia->isActiva() ? 'true' : 'false'
        ]);

        $materia->setId((int)$stmt->fetchColumn());
        return $materia;
    }

    /**
     * Actualiza los datos de una asignatura existente.
     */
    public static function actualizar(Materia $materia): bool {
        $pdo = Conexion::conectar();

        $sql = "UPDATE materias
                SET codigo = :codigo,
                    nombre = :nombre,
                    periodo = :periodo,
                    id_docente = :id_docente,
                    activa = :activa
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':codigo'     => $materia->getCodigo(),
            ':nombre'     => $materia->getNombre(),
            ':periodo'    => $materia->getPeriodo(),
            ':id_docente' => $materia->getIdDocente(),
            ':activa'     => $materia->isActiva() ? 'true' : 'false',
            ':id'         => (int)$materia->getId()
        ]);
    }

    /**
     * Activa o desactiva una asignatura.
     */
    public static function cambiarEstado(int $id, bool $activa): bool {
        $pdo = Conexion::conectar();

        $sql = "UPDATE materias SET activa = :activa WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':activa' => $activa ? 'true' : 'false',
            ':id'     => $id
        ]);
    }
}
