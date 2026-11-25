<?php
/**
 * Entidad Estudiante.
 */

class Estudiante {

    private ?int $id;
    private string $uidRfid;
    private string $cedula;
    private string $nombres;
    private string $apellidos;
    private string $carrera;
    private string $materias;
    private array $materiasIds;

    public function __construct(
        ?int $id = null,
        string $uidRfid = '',
        string $cedula = '',
        string $nombres = '',
        string $apellidos = '',
        string $carrera = 'Software',
        string $materias = '',
        array $materiasIds = []
    ) {
        $this->id          = $id;
        $this->uidRfid     = $uidRfid;
        $this->cedula      = $cedula;
        $this->nombres     = $nombres;
        $this->apellidos   = $apellidos;
        $this->carrera     = $carrera;
        $this->materias    = $materias;
        $this->materiasIds = array_map('intval', $materiasIds);
    }

    public static function fromArray(array $data): self {
        $materiasIds = $data['materias_ids'] ?? ($data['materias'] ?? []);
        if (is_string($materiasIds)) {
            $decoded = json_decode($materiasIds, true);
            $materiasIds = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($materiasIds)) {
            $materiasIds = [];
        }

        return new self(
            isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null,
            trim((string)($data['uid_rfid'] ?? '')),
            trim((string)($data['cedula'] ?? '')),
            trim((string)($data['nombres'] ?? '')),
            trim((string)($data['apellidos'] ?? '')),
            trim((string)($data['carrera'] ?? 'Software')) ?: 'Software',
            is_string($data['materias'] ?? null) ? $data['materias'] : '',
            $materiasIds
        );
    }

    public function toArray(): array {
        return [
            'id'           => $this->id,
            'uid_rfid'     => $this->uidRfid,
            'cedula'       => $this->cedula,
            'nombres'      => $this->nombres,
            'apellidos'    => $this->apellidos,
            'carrera'      => $this->carrera,
            'materias'     => $this->materias,
            'materias_ids' => $this->materiasIds
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getUidRfid(): string { return $this->uidRfid; }
    public function setUidRfid(string $uidRfid): void { $this->uidRfid = $uidRfid; }

    public function getCedula(): string { return $this->cedula; }
    public function setCedula(string $cedula): void { $this->cedula = $cedula; }

    public function getNombres(): string { return $this->nombres; }
    public function setNombres(string $nombres): void { $this->nombres = $nombres; }

    public function getApellidos(): string { return $this->apellidos; }
    public function setApellidos(string $apellidos): void { $this->apellidos = $apellidos; }

    public function getCarrera(): string { return $this->carrera; }
    public function setCarrera(string $carrera): void { $this->carrera = $carrera; }

    public function getMaterias(): string { return $this->materias; }
    public function setMaterias(string $materias): void { $this->materias = $materias; }

    public function getMateriasIds(): array { return $this->materiasIds; }
    public function setMateriasIds(array $materiasIds): void { $this->materiasIds = array_map('intval', $materiasIds); }
}
