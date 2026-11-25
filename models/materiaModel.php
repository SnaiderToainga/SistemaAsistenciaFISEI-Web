<?php
/**
 * Modelo de Gestión de Asignaturas / Materias.
 * Contiene validaciones de negocio y delega SQL/PDO a MateriaDAO y UsuarioDAO.
 */

require_once __DIR__ . '/../dao/MateriaDAO.php';
require_once __DIR__ . '/../dao/UsuarioDAO.php';

class MateriaModel {

    /**
     * Lista todas las asignaturas con el nombre del docente responsable
     * y el número de estudiantes matriculados.
     */
    public static function listarMaterias(): array {
        $materias = MateriaDAO::listarTodas();

        return array_map(
            static fn(Materia $m): array => $m->toArray(),
            $materias
        );
    }

    /**
     * Lista los docentes activos disponibles para asignar a una materia.
     */
    public static function listarDocentes(): array {
        $docentes = UsuarioDAO::listarDocentesActivos();

        return array_map(static function (Usuario $u): array {
            return [
                'id'              => $u->getId(),
                'username'        => $u->getUsername(),
                'nombre_completo' => $u->getNombreCompleto()
            ];
        }, $docentes);
    }

    /**
     * Registra una nueva asignatura.
     *
     * @throws Exception Si falla la validación o inserción
     */
    public static function guardarMateria(array $datos): array {
        $materia = self::validarYConstruirMateria($datos);

        if (MateriaDAO::existeCodigo($materia->getCodigo())) {
            throw new Exception("Ya existe una asignatura registrada con el código {$materia->getCodigo()}.");
        }

        try {
            $creada = MateriaDAO::insertar($materia);

            return [
                'id'         => $creada->getId(),
                'codigo'     => $creada->getCodigo(),
                'nombre'     => $creada->getNombre(),
                'periodo'    => $creada->getPeriodo(),
                'id_docente' => $creada->getIdDocente(),
                'activa'     => $creada->isActiva()
            ];
        } catch (PDOException $e) {
            throw new Exception("Error al guardar la asignatura: " . self::mensajeErrorBd($e));
        }
    }

    /**
     * Modifica los datos de una asignatura existente.
     *
     * @throws Exception Si falla la validación o actualización
     */
    public static function modificarMateria(int $id, array $datos): bool {
        if ($id <= 0) {
            throw new Exception("ID de asignatura no válido para modificación.");
        }

        $materia = self::validarYConstruirMateria($datos);
        $materia->setId($id);

        if (!MateriaDAO::existePorId($id)) {
            throw new Exception("La asignatura seleccionada no existe.");
        }

        if (MateriaDAO::existeCodigo($materia->getCodigo(), $id)) {
            throw new Exception("El código {$materia->getCodigo()} ya está asignado a otra asignatura.");
        }

        try {
            return MateriaDAO::actualizar($materia);
        } catch (PDOException $e) {
            throw new Exception("Error al modificar la asignatura: " . self::mensajeErrorBd($e));
        }
    }

    /**
     * Activa o desactiva una asignatura sin eliminar sus matrículas ni asistencias.
     *
     * @throws Exception Si la materia no existe
     */
    public static function cambiarEstado(int $id, bool $activa): bool {
        if ($id <= 0) {
            throw new Exception("ID de asignatura no válido.");
        }

        if (!MateriaDAO::existePorId($id)) {
            throw new Exception("La asignatura seleccionada no existe.");
        }

        return MateriaDAO::cambiarEstado($id, $activa);
    }

    /**
     * Valida y normaliza los campos construyendo una entidad Materia.
     *
     * @throws Exception Si algún campo es inválido
     */
    private static function validarYConstruirMateria(array $datos): Materia {
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

        $idDocente = isset($datos['id_docente']) && $datos['id_docente'] !== '' && $datos['id_docente'] !== null
            ? (int)$datos['id_docente']
            : null;

        if ($idDocente !== null && !UsuarioDAO::existeDocenteActivo($idDocente)) {
            throw new Exception("El docente seleccionado no existe o se encuentra inactivo.");
        }

        $activa = filter_var($datos['activa'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return new Materia(
            null,
            $codigo,
            $nombre,
            $periodo,
            $idDocente,
            $activa ?? true
        );
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
