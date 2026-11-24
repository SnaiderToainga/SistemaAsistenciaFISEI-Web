<?php
/**
 * ==============================================================================
 * Modelo de Gestión de Asignaturas / Materias
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Consultas preparadas con PDO para PostgreSQL de Supabase.
 * Tabla materias: (id, codigo, nombre, periodo, id_docente, activa)
 */

require_once __DIR__ . '/../config/database.php';

class MateriaModel {

    /**
     * Lista todas las asignaturas con el nombre del docente responsable
     * y el número de estudiantes matriculados.
     *
     * @return array Lista de materias (activas e inactivas)
     */
    public static function listarMaterias(): array {
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
        $materias = $stmt->fetchAll();

        foreach ($materias as &$materia) {
            $materia['id']                 = (int)$materia['id'];
            $materia['id_docente']         = $materia['id_docente'] !== null ? (int)$materia['id_docente'] : null;
            $materia['activa']             = (bool)$materia['activa'];
            $materia['total_matriculados'] = (int)$materia['total_matriculados'];
        }

        return $materias;
    }

    /**
     * Lista los docentes activos disponibles para asignar a una materia.
     *
     * @return array Usuarios con rol DOCENTE y activo = true
     */
    public static function listarDocentes(): array {
        $pdo = Conexion::conectar();

        $sql = "SELECT id, username, nombre_completo
                FROM usuarios
                WHERE rol = 'DOCENTE' AND activo = true
                ORDER BY nombre_completo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Registra una nueva asignatura.
     *
     * @param array $datos (codigo, nombre, periodo, id_docente, activa)
     * @return array Datos de la materia creada con su nuevo ID
     * @throws Exception Si falla la validación o inserción
     */
    public static function guardarMateria(array $datos): array {
        $pdo = Conexion::conectar();

        // 1. Validaciones de servidor
        $materia = self::validarDatosMateria($pdo, $datos);

        // 2. Comprobar duplicidad del código de asignatura
        $sqlCheck = "SELECT id FROM materias WHERE UPPER(codigo) = :codigo LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':codigo' => $materia['codigo']]);

        if ($stmtCheck->fetch()) {
            throw new Exception("Ya existe una asignatura registrada con el código {$materia['codigo']}.");
        }

        // 3. Insertar materia con RETURNING id
        try {
            $sql = "INSERT INTO materias (codigo, nombre, periodo, id_docente, activa)
                    VALUES (:codigo, :nombre, :periodo, :id_docente, :activa)
                    RETURNING id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':codigo'     => $materia['codigo'],
                ':nombre'     => $materia['nombre'],
                ':periodo'    => $materia['periodo'],
                ':id_docente' => $materia['id_docente'],
                ':activa'     => $materia['activa'] ? 'true' : 'false'
            ]);
            $materia['id'] = (int)$stmt->fetchColumn();

            return $materia;
        } catch (PDOException $e) {
            throw new Exception("Error al guardar la asignatura: " . self::mensajeErrorBd($e));
        }
    }

    /**
     * Modifica los datos de una asignatura existente.
     *
     * @param int $id ID de la materia
     * @param array $datos (codigo, nombre, periodo, id_docente, activa)
     * @return bool True si la operación fue exitosa
     * @throws Exception Si falla la validación o actualización
     */
    public static function modificarMateria(int $id, array $datos): bool {
        if ($id <= 0) {
            throw new Exception("ID de asignatura no válido para modificación.");
        }

        $pdo = Conexion::conectar();

        // 1. Validaciones de servidor
        $materia = self::validarDatosMateria($pdo, $datos);
        self::verificarExistencia($pdo, $id);

        // 2. Comprobar duplicidad del código en otros registros
        $sqlCheck = "SELECT id FROM materias WHERE UPPER(codigo) = :codigo AND id != :id LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':codigo' => $materia['codigo'],
            ':id'     => $id
        ]);

        if ($stmtCheck->fetch()) {
            throw new Exception("El código {$materia['codigo']} ya está asignado a otra asignatura.");
        }

        // 3. Actualizar materia
        try {
            $sql = "UPDATE materias
                    SET codigo = :codigo,
                        nombre = :nombre,
                        periodo = :periodo,
                        id_docente = :id_docente,
                        activa = :activa
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':codigo'     => $materia['codigo'],
                ':nombre'     => $materia['nombre'],
                ':periodo'    => $materia['periodo'],
                ':id_docente' => $materia['id_docente'],
                ':activa'     => $materia['activa'] ? 'true' : 'false',
                ':id'         => $id
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error al modificar la asignatura: " . self::mensajeErrorBd($e));
        }
    }

    /**
     * Activa o desactiva una asignatura sin eliminar sus matrículas ni asistencias.
     *
     * @param int $id ID de la materia
     * @param bool $activa Nuevo estado
     * @return bool True si se actualizó
     * @throws Exception Si la materia no existe
     */
    public static function cambiarEstado(int $id, bool $activa): bool {
        if ($id <= 0) {
            throw new Exception("ID de asignatura no válido.");
        }

        $pdo = Conexion::conectar();
        self::verificarExistencia($pdo, $id);

        $sql = "UPDATE materias SET activa = :activa WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':activa' => $activa ? 'true' : 'false',
            ':id'     => $id
        ]);
    }

    /**
     * Comprueba que la asignatura exista.
     *
     * @throws Exception Si no existe
     */
    private static function verificarExistencia(PDO $pdo, int $id): void {
        $stmt = $pdo->prepare("SELECT id FROM materias WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {
            throw new Exception("La asignatura seleccionada no existe.");
        }
    }

    /**
     * Valida y normaliza los campos de una asignatura.
     *
     * @param PDO $pdo Conexión activa (para validar el docente)
     * @param array $datos Datos recibidos del formulario
     * @return array Datos normalizados
     * @throws Exception Si algún campo es inválido
     */
    private static function validarDatosMateria(PDO $pdo, array $datos): array {
        $codigo  = strtoupper(trim((string)($datos['codigo'] ?? '')));
        $nombre  = trim((string)($datos['nombre'] ?? ''));
        $periodo = trim((string)($datos['periodo'] ?? ''));

        if ($codigo === '') {
            throw new Exception("El código de la asignatura es obligatorio.");
        }

        if (!preg_match('/^[A-Z0-9][A-Z0-9\-]{1,19}$/', $codigo)) {
            throw new Exception("El código debe tener entre 2 y 20 caracteres (letras, números y guion). Ej: SIS-501.");
        }

        if ($nombre === '') {
            throw new Exception("El nombre de la asignatura es obligatorio.");
        }

        if (mb_strlen($nombre, 'UTF-8') > 150) {
            throw new Exception("El nombre de la asignatura no puede superar los 150 caracteres.");
        }

        if (!preg_match('/^([0-9]{4})-([0-9]{4})$/', $periodo, $anios) || (int)$anios[2] !== (int)$anios[1] + 1) {
            throw new Exception("El periodo debe tener el formato AAAA-AAAA con años consecutivos. Ej: 2026-2027.");
        }

        // Docente responsable (opcional): debe existir, estar activo y tener rol DOCENTE
        $idDocente = isset($datos['id_docente']) && $datos['id_docente'] !== '' && $datos['id_docente'] !== null
            ? (int)$datos['id_docente']
            : null;

        if ($idDocente !== null) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND rol = 'DOCENTE' AND activo = true");
            $stmt->execute([':id' => $idDocente]);

            if (!$stmt->fetch()) {
                throw new Exception("El docente seleccionado no existe o se encuentra inactivo.");
            }
        }

        $activa = filter_var($datos['activa'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return [
            'codigo'     => $codigo,
            'nombre'     => $nombre,
            'periodo'    => $periodo,
            'id_docente' => $idDocente,
            'activa'     => $activa ?? true
        ];
    }

    /**
     * Traduce errores de restricciones de PostgreSQL a mensajes legibles.
     */
    private static function mensajeErrorBd(PDOException $e): string {
        if ($e->getCode() === '23505') {
            return "el código de asignatura ya se encuentra registrado.";
        }
        if ($e->getCode() === '23503') {
            return "el docente seleccionado no existe.";
        }
        return $e->getMessage();
    }
}
