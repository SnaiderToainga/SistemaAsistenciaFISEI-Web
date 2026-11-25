<?php
/**
 * Modelo de Estudiantes y Matrículas.
 * Contiene las reglas de negocio y validaciones, delegando SQL/PDO a EstudianteDAO.
 */

require_once __DIR__ . '/../dao/EstudianteDAO.php';

class EstudianteModel {

    /**
     * Lista todos los estudiantes con sus materias asignadas agrupadas.
     */
    public static function listarEstudiantes(): array {
        $estudiantes = EstudianteDAO::listarConMaterias();

        return array_map(
            static fn(Estudiante $e): array => $e->toArray(),
            $estudiantes
        );
    }

    /**
     * Lista las asignaturas activas en el periodo vigente.
     */
    public static function listarMateriasActivas(): array {
        $materias = EstudianteDAO::listarMateriasActivas();

        return array_map(static function (Materia $m): array {
            return [
                'id'      => $m->getId(),
                'codigo'  => $m->getCodigo(),
                'nombre'  => $m->getNombre(),
                'periodo' => $m->getPeriodo()
            ];
        }, $materias);
    }

    /**
     * Registra un nuevo estudiante y sus matrículas.
     *
     * @throws Exception Si falla la validación o inserción
     */
    public static function guardarEstudiante(array $datos, array $materiasIds = []): array {
        self::validarDatosEstudiante($datos);

        $estudiante = Estudiante::fromArray($datos);
        $duplicado  = EstudianteDAO::buscarDuplicado($estudiante->getCedula(), $estudiante->getUidRfid());

        if ($duplicado) {
            if ($duplicado['cedula'] === $estudiante->getCedula()) {
                throw new Exception("Ya existe un estudiante registrado con la cédula {$estudiante->getCedula()}.");
            }
            if ($duplicado['uid_rfid'] === $estudiante->getUidRfid()) {
                throw new Exception("El llavero RFID ({$estudiante->getUidRfid()}) ya está asignado a otro estudiante.");
            }
        }

        $creado = EstudianteDAO::insertarConMatriculas($estudiante, $materiasIds);

        return [
            'id'        => $creado->getId(),
            'uid_rfid'  => $creado->getUidRfid(),
            'cedula'    => $creado->getCedula(),
            'nombres'   => $creado->getNombres(),
            'apellidos' => $creado->getApellidos(),
            'carrera'   => $creado->getCarrera(),
            'materias'  => $creado->getMateriasIds()
        ];
    }

    /**
     * Modifica los datos de un estudiante y sincroniza sus matrículas.
     *
     * @throws Exception Si falla la validación o actualización
     */
    public static function modificarEstudiante(int $id, array $datos, array $materiasIds = []): bool {
        if ($id <= 0) {
            throw new Exception("ID de estudiante no válido para modificación.");
        }

        self::validarDatosEstudiante($datos);

        $estudiante = Estudiante::fromArray($datos);
        $estudiante->setId($id);

        $duplicado = EstudianteDAO::buscarDuplicado($estudiante->getCedula(), $estudiante->getUidRfid(), $id);
        if ($duplicado) {
            if ($duplicado['cedula'] === $estudiante->getCedula()) {
                throw new Exception("La cédula {$estudiante->getCedula()} ya está registrada para otro estudiante.");
            }
            if ($duplicado['uid_rfid'] === $estudiante->getUidRfid()) {
                throw new Exception("El llavero RFID ({$estudiante->getUidRfid()}) ya pertenece a otro estudiante.");
            }
        }

        return EstudianteDAO::actualizarConMatriculas($estudiante, $materiasIds);
    }

    /**
     * Elimina a un estudiante y sus registros relacionados en cascada.
     *
     * @throws Exception Si el ID es inválido o falla la eliminación
     */
    public static function eliminarEstudiante(int $id): bool {
        if ($id <= 0) {
            throw new Exception("ID de estudiante inválido para eliminación.");
        }

        return EstudianteDAO::eliminar($id);
    }

    /**
     * Busca a un estudiante por su llavero RFID (estrictamente como String).
     */
    public static function buscarPorRfid(string $uid): ?array {
        $uid = trim($uid);
        if ($uid === '') {
            return null;
        }

        $estudiante = EstudianteDAO::buscarPorRfid($uid);
        if (!$estudiante) {
            return null;
        }

        $datos = $estudiante->toArray();
        unset($datos['materias']);
        return $datos;
    }

    /**
     * Valida cédula de 10 dígitos numéricos y campos requeridos.
     *
     * @throws Exception
     */
    private static function validarDatosEstudiante(array $datos): void {
        if (empty(trim((string)($datos['uid_rfid'] ?? '')))) {
            throw new Exception("El campo UID de Llavero RFID es obligatorio.");
        }

        $cedula = trim((string)($datos['cedula'] ?? ''));
        if ($cedula === '') {
            throw new Exception("El número de cédula es obligatorio.");
        }

        if (!preg_match('/^[0-9]{10}$/', $cedula)) {
            throw new Exception("La cédula debe contener exactamente 10 dígitos numéricos.");
        }

        if (empty(trim((string)($datos['nombres'] ?? '')))) {
            throw new Exception("Los nombres del estudiante son obligatorios.");
        }

        if (empty(trim((string)($datos['apellidos'] ?? '')))) {
            throw new Exception("Los apellidos del estudiante son obligatorios.");
        }
    }
}
