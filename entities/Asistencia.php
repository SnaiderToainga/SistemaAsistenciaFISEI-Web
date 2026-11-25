<?php
/**
 * Entidad Asistencia.
 */

class Asistencia {

    private ?int $id;
    private int $idEstudiante;
    private int $idMateria;
    private string $parcial;
    private string $fecha;
    private ?string $hora;
    private string $estado;
    private ?string $observacion;

    public function __construct(
        ?int $id = null,
        int $idEstudiante = 0,
        int $idMateria = 0,
        string $parcial = 'Parcial 1',
        string $fecha = '',
        ?string $hora = null,
        string $estado = 'PRESENTE',
        ?string $observacion = null
    ) {
        $this->id           = $id;
        $this->idEstudiante = $idEstudiante;
        $this->idMateria    = $idMateria;
        $this->parcial      = $parcial;
        $this->fecha        = $fecha;
        $this->hora         = $hora;
        $this->estado       = $estado;
        $this->observacion  = $observacion;
    }

    public static function fromArray(array $data): self {
        return new self(
            isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null,
            (int)($data['id_estudiante'] ?? 0),
            (int)($data['id_materia'] ?? 0),
            trim((string)($data['parcial'] ?? 'Parcial 1')),
            trim((string)($data['fecha'] ?? '')),
            isset($data['hora']) ? (string)$data['hora'] : null,
            strtoupper(trim((string)($data['estado'] ?? 'PRESENTE'))),
            isset($data['observacion']) && $data['observacion'] !== '' ? (string)$data['observacion'] : null
        );
    }

    public function toArray(): array {
        return [
            'id'            => $this->id,
            'id_estudiante' => $this->idEstudiante,
            'id_materia'    => $this->idMateria,
            'parcial'       => $this->parcial,
            'fecha'         => $this->fecha,
            'hora'          => $this->hora,
            'estado'        => $this->estado,
            'observacion'   => $this->observacion ?? ''
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getIdEstudiante(): int { return $this->idEstudiante; }
    public function setIdEstudiante(int $idEstudiante): void { $this->idEstudiante = $idEstudiante; }

    public function getIdMateria(): int { return $this->idMateria; }
    public function setIdMateria(int $idMateria): void { $this->idMateria = $idMateria; }

    public function getParcial(): string { return $this->parcial; }
    public function setParcial(string $parcial): void { $this->parcial = $parcial; }

    public function getFecha(): string { return $this->fecha; }
    public function setFecha(string $fecha): void { $this->fecha = $fecha; }

    public function getHora(): ?string { return $this->hora; }
    public function setHora(?string $hora): void { $this->hora = $hora; }

    public function getEstado(): string { return $this->estado; }
    public function setEstado(string $estado): void { $this->estado = $estado; }

    public function getObservacion(): ?string { return $this->observacion; }
    public function setObservacion(?string $observacion): void { $this->observacion = $observacion; }
}
