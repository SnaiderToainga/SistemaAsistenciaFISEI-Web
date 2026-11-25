<?php
/**
 * DAO de Estudiantes y Matrículas.
 * Encapsula todas las consultas SQL y transacciones PDO sobre `estudiantes` y `matriculas`.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../entities/Estudiante.php';
require_once __DIR__ . '/../entities/Materia.php';

class EstudianteDAO {

    /**
     * Lista todos los estudiantes con sus materias activas agrupadas.
     *
     * @return Estudiante[]
     */
    public static function listarConMaterias(): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT 
                    e.id, 
                    e.uid_rfid, 
                    e.cedula, 
                    e.nombres, 
                    e.apellidos, 
                    COALESCE(e.carrera, 'No especificada') AS carrera,
                    COALESCE(STRING_AGG(m.codigo || ' - ' || m.nombre, ', ' ORDER BY m.codigo), '') AS materias,
                    COALESCE(ARRAY_TO_JSON(ARRAY_AGG(m.id) FILTER (WHERE m.id IS NOT NULL)), '[]') AS materias_ids
                FROM estudiantes e
                LEFT JOIN matriculas mat ON e.id = mat.id_estudiante
                LEFT JOIN materias m ON mat.id_materia = m.id AND m.activa = true
                GROUP BY e.id, e.uid_rfid, e.cedula, e.nombres, e.apellidos, e.carrera
                ORDER BY e.apellidos ASC, e.nombres ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return array_map(
            static fn(array $fila): Estudiante => Estudiante::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Lista las asignaturas activas del periodo vigente.
     *
     * @return Materia[]
     */
    public static function listarMateriasActivas(): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT id, codigo, nombre, periodo, id_docente, activa
                FROM materias 
                WHERE activa = true 
                ORDER BY codigo ASC, nombre ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return array_map(
            static fn(array $fila): Materia => Materia::fromArray($fila),
            $stmt->fetchAll()
        );
    }

    /**
     * Busca un estudiante por su UID RFID exacto (string).
     */
    public static function buscarPorRfid(string $uid): ?Estudiante {
        $pdo = Conexion::conectar();

        $sql = "SELECT 
                    e.id, 
                    e.uid_rfid, 
                    e.cedula, 
                    e.nombres, 
                    e.apellidos, 
                    COALESCE(e.carrera, 'Software') AS carrera,
                    COALESCE(ARRAY_TO_JSON(ARRAY_AGG(mat.id_materia) FILTER (WHERE mat.id_materia IS NOT NULL)), '[]') AS materias_ids
                FROM estudiantes e
                LEFT JOIN matriculas mat ON e.id = mat.id_estudiante
                WHERE e.uid_rfid = :uid_rfid
                GROUP BY e.id, e.uid_rfid, e.cedula, e.nombres, e.apellidos, e.carrera
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid_rfid' => $uid]);
        $fila = $stmt->fetch();

        return $fila ? Estudiante::fromArray($fila) : null;
    }

    /**
     * Verifica si existe otro estudiante con la misma cédula o UID RFID.
     */
    public static function buscarDuplicado(string $cedula, string $uidRfid, ?int $excluirId = null): ?array {
        $pdo = Conexion::conectar();

        if ($excluirId !== null) {
            $sql = "SELECT id, cedula, uid_rfid FROM estudiantes
                    WHERE (cedula = :cedula OR uid_rfid = :uid_rfid) AND id != :id
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cedula'   => $cedula,
                ':uid_rfid' => $uidRfid,
                ':id'       => $excluirId
            ]);
        } else {
            $sql = "SELECT id, cedula, uid_rfid FROM estudiantes
                    WHERE cedula = :cedula OR uid_rfid = :uid_rfid
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cedula'   => $cedula,
                ':uid_rfid' => $uidRfid
            ]);
        }

        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /**
     * Inserta un estudiante y sus matrículas dentro de una transacción PDO.
     */
    public static function insertarConMatriculas(Estudiante $estudiante, array $materiasIds): Estudiante {
        $pdo = Conexion::conectar();
        $pdo->beginTransaction();

        try {
            $sqlEstudiante = "INSERT INTO estudiantes (uid_rfid, cedula, nombres, apellidos, carrera) 
                              VALUES (:uid_rfid, :cedula, :nombres, :apellidos, :carrera) 
                              RETURNING id";
            $stmtEstudiante = $pdo->prepare($sqlEstudiante);
            $stmtEstudiante->execute([
                ':uid_rfid'  => $estudiante->getUidRfid(),
                ':cedula'    => $estudiante->getCedula(),
                ':nombres'   => $estudiante->getNombres(),
                ':apellidos' => $estudiante->getApellidos(),
                ':carrera'   => $estudiante->getCarrera()
            ]);

            $idEstudiante = (int)$stmtEstudiante->fetchColumn();
            $estudiante->setId($idEstudiante);
            $estudiante->setMateriasIds($materiasIds);

            self::insertarMatriculas($pdo, $idEstudiante, $materiasIds);

            $pdo->commit();
            return $estudiante;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception("Error al guardar estudiante: " . $e->getMessage());
        }
    }

    /**
     * Actualiza un estudiante y sincroniza sus matrículas dentro de una transacción PDO.
     */
    public static function actualizarConMatriculas(Estudiante $estudiante, array $materiasIds): bool {
        $id = (int)$estudiante->getId();
        $pdo = Conexion::conectar();
        $pdo->beginTransaction();

        try {
            $sqlEstudiante = "UPDATE estudiantes 
                              SET uid_rfid = :uid_rfid, 
                                  cedula = :cedula, 
                                  nombres = :nombres, 
                                  apellidos = :apellidos, 
                                  carrera = :carrera 
                              WHERE id = :id";
            $stmtEstudiante = $pdo->prepare($sqlEstudiante);
            $stmtEstudiante->execute([
                ':uid_rfid'  => $estudiante->getUidRfid(),
                ':cedula'    => $estudiante->getCedula(),
                ':nombres'   => $estudiante->getNombres(),
                ':apellidos' => $estudiante->getApellidos(),
                ':carrera'   => $estudiante->getCarrera(),
                ':id'        => $id
            ]);

            $stmtDelete = $pdo->prepare("DELETE FROM matriculas WHERE id_estudiante = :id");
            $stmtDelete->execute([':id' => $id]);

            self::insertarMatriculas($pdo, $id, $materiasIds);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception("Error al modificar estudiante: " . $e->getMessage());
        }
    }

    /**
     * Elimina un estudiante y sus registros relacionados en una transacción PDO.
     */
    public static function eliminar(int $id): bool {
        $pdo = Conexion::conectar();
        $pdo->beginTransaction();

        try {
            $stmtMat = $pdo->prepare("DELETE FROM matriculas WHERE id_estudiante = :id");
            $stmtMat->execute([':id' => $id]);

            $stmtAsist = $pdo->prepare("DELETE FROM asistencias WHERE id_estudiante = :id");
            $stmtAsist->execute([':id' => $id]);

            $stmtEst = $pdo->prepare("DELETE FROM estudiantes WHERE id = :id");
            $stmtEst->execute([':id' => $id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception("Error al eliminar estudiante: " . $e->getMessage());
        }
    }

    private static function insertarMatriculas(PDO $pdo, int $idEstudiante, array $materiasIds): void {
        if (empty($materiasIds)) {
            return;
        }

        $stmtMatricula = $pdo->prepare("INSERT INTO matriculas (id_estudiante, id_materia) VALUES (:id_estudiante, :id_materia)");
        foreach ($materiasIds as $idMateria) {
            $idMateriaInt = (int)$idMateria;
            if ($idMateriaInt > 0) {
                $stmtMatricula->execute([
                    ':id_estudiante' => $idEstudiante,
                    ':id_materia'    => $idMateriaInt
                ]);
            }
        }
    }
}
