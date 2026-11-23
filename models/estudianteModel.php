<?php
/**
 * ==============================================================================
 * Modelo de Estudiantes y Matrículas
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Consultas preparadas con PDO para PostgreSQL de Supabase.
 */

require_once __DIR__ . '/../config/database.php';

class EstudianteModel {

    /**
     * Lista todos los estudiantes con sus materias asignadas agrupadas.
     * Realiza un LEFT JOIN entre estudiantes, matriculas y materias.
     * 
     * @return array Lista de estudiantes con materias agrupadas y arreglo de IDs
     */
    public static function listarEstudiantes(): array {
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
        $estudiantes = $stmt->fetchAll();

        // Formatear materias_ids como array PHP de enteros
        foreach ($estudiantes as &$estudiante) {
            $ids = json_decode($estudiante['materias_ids'], true);
            $estudiante['materias_ids'] = is_array($ids) ? array_map('intval', $ids) : [];
        }

        return $estudiantes;
    }

    /**
     * Lista las asignaturas activas en el periodo vigente.
     * 
     * @return array Asignaturas con activa = true
     */
    public static function listarMateriasActivas(): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT id, codigo, nombre, periodo 
                FROM materias 
                WHERE activa = true 
                ORDER BY codigo ASC, nombre ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Registra un nuevo estudiante y sus matrículas en una transacción PDO.
     * 
     * @param array $datos Datos personales del alumno (uid_rfid, cedula, nombres, apellidos, carrera)
     * @param array $materiasIds Arreglo de IDs de materias seleccionadas
     * @return array Datos del estudiante creado con su nuevo ID
     * @throws Exception Si falla la validación o inserción
     */
    public static function guardarEstudiante(array $datos, array $materiasIds = []): array {
        $pdo = Conexion::conectar();

        // 1. Validaciones de servidor
        self::validarDatosEstudiante($datos);

        $uidRfid   = trim((string)$datos['uid_rfid']);
        $cedula    = trim((string)$datos['cedula']);
        $nombres   = trim((string)$datos['nombres']);
        $apellidos = trim((string)$datos['apellidos']);
        $carrera   = isset($datos['carrera']) ? trim((string)$datos['carrera']) : 'Software';

        // 2. Comprobar duplicidad de Cédula o Llavero RFID
        $sqlCheck = "SELECT id, cedula, uid_rfid FROM estudiantes WHERE cedula = :cedula OR uid_rfid = :uid_rfid LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':cedula'   => $cedula,
            ':uid_rfid' => $uidRfid
        ]);
        $existente = $stmtCheck->fetch();

        if ($existente) {
            if ($existente['cedula'] === $cedula) {
                throw new Exception("Ya existe un estudiante registrado con la cédula {$cedula}.");
            }
            if ($existente['uid_rfid'] === $uidRfid) {
                throw new Exception("El llavero RFID ({$uidRfid}) ya está asignado a otro estudiante.");
            }
        }

        // 3. Transacción atómica PDO
        $pdo->beginTransaction();

        try {
            // Insertar estudiante con RETURNING id
            $sqlEstudiante = "INSERT INTO estudiantes (uid_rfid, cedula, nombres, apellidos, carrera) 
                              VALUES (:uid_rfid, :cedula, :nombres, :apellidos, :carrera) 
                              RETURNING id";
            $stmtEstudiante = $pdo->prepare($sqlEstudiante);
            $stmtEstudiante->execute([
                ':uid_rfid'  => $uidRfid,
                ':cedula'    => $cedula,
                ':nombres'   => $nombres,
                ':apellidos' => $apellidos,
                ':carrera'   => $carrera
            ]);
            $idEstudiante = (int)$stmtEstudiante->fetchColumn();

            // Insertar matrículas
            if (!empty($materiasIds)) {
                $sqlMatricula = "INSERT INTO matriculas (id_estudiante, id_materia) VALUES (:id_estudiante, :id_materia)";
                $stmtMatricula = $pdo->prepare($sqlMatricula);

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

            $pdo->commit();

            return [
                'id'         => $idEstudiante,
                'uid_rfid'   => $uidRfid,
                'cedula'     => $cedula,
                'nombres'    => $nombres,
                'apellidos'  => $apellidos,
                'carrera'    => $carrera,
                'materias'   => $materiasIds
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception("Error al guardar estudiante: " . $e->getMessage());
        }
    }

    /**
     * Modifica los datos de un estudiante y sincroniza sus matrículas en una transacción PDO.
     * 
     * @param int $id ID del estudiante
     * @param array $datos Datos actualizados del alumno
     * @param array $materiasIds Nuevos IDs de materias seleccionadas
     * @return bool True si la operación fue exitosa
     * @throws Exception Si falla la validación o actualización
     */
    public static function modificarEstudiante(int $id, array $datos, array $materiasIds = []): bool {
        if ($id <= 0) {
            throw new Exception("ID de estudiante no válido para modificación.");
        }

        $pdo = Conexion::conectar();

        // 1. Validaciones de servidor
        self::validarDatosEstudiante($datos);

        $uidRfid   = trim((string)$datos['uid_rfid']);
        $cedula    = trim((string)$datos['cedula']);
        $nombres   = trim((string)$datos['nombres']);
        $apellidos = trim((string)$datos['apellidos']);
        $carrera   = isset($datos['carrera']) ? trim((string)$datos['carrera']) : 'Software';

        // 2. Comprobar duplicidad de Cédula o RFID en otros registros
        $sqlCheck = "SELECT id, cedula, uid_rfid FROM estudiantes 
                     WHERE (cedula = :cedula OR uid_rfid = :uid_rfid) AND id != :id 
                     LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':cedula'   => $cedula,
            ':uid_rfid' => $uidRfid,
            ':id'       => $id
        ]);
        $existente = $stmtCheck->fetch();

        if ($existente) {
            if ($existente['cedula'] === $cedula) {
                throw new Exception("La cédula {$cedula} ya está registrada para otro estudiante.");
            }
            if ($existente['uid_rfid'] === $uidRfid) {
                throw new Exception("El llavero RFID ({$uidRfid}) ya pertenece a otro estudiante.");
            }
        }

        // 3. Transacción atómica PDO
        $pdo->beginTransaction();

        try {
            // Actualizar estudiante
            $sqlEstudiante = "UPDATE estudiantes 
                              SET uid_rfid = :uid_rfid, 
                                  cedula = :cedula, 
                                  nombres = :nombres, 
                                  apellidos = :apellidos, 
                                  carrera = :carrera 
                              WHERE id = :id";
            $stmtEstudiante = $pdo->prepare($sqlEstudiante);
            $stmtEstudiante->execute([
                ':uid_rfid'  => $uidRfid,
                ':cedula'    => $cedula,
                ':nombres'   => $nombres,
                ':apellidos' => $apellidos,
                ':carrera'   => $carrera,
                ':id'        => $id
            ]);

            // Sincronizar matrículas: limpiar anteriores e insertar la nueva selección
            $sqlDeleteMatriculas = "DELETE FROM matriculas WHERE id_estudiante = :id";
            $stmtDelete = $pdo->prepare($sqlDeleteMatriculas);
            $stmtDelete->execute([':id' => $id]);

            if (!empty($materiasIds)) {
                $sqlInsertMatricula = "INSERT INTO matriculas (id_estudiante, id_materia) VALUES (:id_estudiante, :id_materia)";
                $stmtInsert = $pdo->prepare($sqlInsertMatricula);

                foreach ($materiasIds as $idMateria) {
                    $idMateriaInt = (int)$idMateria;
                    if ($idMateriaInt > 0) {
                        $stmtInsert->execute([
                            ':id_estudiante' => $id,
                            ':id_materia'    => $idMateriaInt
                        ]);
                    }
                }
            }

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
     * Elimina a un estudiante y sus registros relacionados en cascada.
     * 
     * @param int $id ID del estudiante
     * @return bool True si se eliminó
     * @throws Exception Si el ID es inválido o falla la eliminación
     */
    public static function eliminarEstudiante(int $id): bool {
        if ($id <= 0) {
            throw new Exception("ID de estudiante inválido para eliminación.");
        }

        $pdo = Conexion::conectar();
        $pdo->beginTransaction();

        try {
            // Eliminar dependencias primero si no hay ON DELETE CASCADE en la BD
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

    /**
     * Busca a un estudiante por su llavero RFID (tratado estrictamente como String
     * para preservar ceros a la izquierda como '0006047325').
     * 
     * @param string $uid UID del llavero RFID
     * @return array|null Datos del estudiante y materias_ids matriculadas o null si no existe
     */
    public static function buscarPorRfid(string $uid): ?array {
        $uid = trim($uid);
        if (empty($uid)) {
            return null;
        }

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
        $estudiante = $stmt->fetch();

        if (!$estudiante) {
            return null;
        }

        // Convertir materias_ids en array PHP de enteros
        $ids = json_decode($estudiante['materias_ids'], true);
        $estudiante['materias_ids'] = is_array($ids) ? array_map('intval', $ids) : [];

        return $estudiante;
    }

    /**
     * Valida cédula de 10 dígitos numéricos y campos requeridos.
     * 
     * @param array $datos
     * @throws Exception
     */
    private static function validarDatosEstudiante(array $datos): void {
        if (empty(trim($datos['uid_rfid'] ?? ''))) {
            throw new Exception("El campo UID de Llavero RFID es obligatorio.");
        }

        $cedula = trim($datos['cedula'] ?? '');
        if (empty($cedula)) {
            throw new Exception("El número de cédula es obligatorio.");
        }

        if (!preg_match('/^[0-9]{10}$/', $cedula)) {
            throw new Exception("La cédula debe contener exactamente 10 dígitos numéricos.");
        }

        if (empty(trim($datos['nombres'] ?? ''))) {
            throw new Exception("Los nombres del estudiante son obligatorios.");
        }

        if (empty(trim($datos['apellidos'] ?? ''))) {
            throw new Exception("Los apellidos del estudiante son obligatorios.");
        }
    }
}

