<?php
/**
 * Entidad Materia (Asignatura).
 */

class Materia {

    private ?int $id;
    private string $codigo;
    private string $nombre;
    private string $periodo;
    private ?int $idDocente;
    private bool $activa;
    private string $docente;
    private int $totalMatriculados;

    public function __construct(
        ?int $id = null,
        string $codigo = '',
        string $nombre = '',
        string $periodo = '2026-2027',
        ?int $idDocente = null,
        bool $activa = true,
        string $docente = '',
        int $totalMatriculados = 0
    ) {
        $this->id                = $id;
        $this->codigo            = $codigo;
        $this->nombre            = $nombre;
        $this->periodo           = $periodo;
        $this->idDocente         = $idDocente;
        $this->activa            = $activa;
        $this->docente           = $docente;
        $this->totalMatriculados = $totalMatriculados;
    }

    public static function fromArray(array $data): self {
        $idDocente = isset($data['id_docente']) && $data['id_docente'] !== '' && $data['id_docente'] !== null
            ? (int)$data['id_docente']
            : null;

        return new self(
            isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null,
            strtoupper(trim((string)($data['codigo'] ?? ''))),
            trim((string)($data['nombre'] ?? '')),
            trim((string)($data['periodo'] ?? '2026-2027')),
            $idDocente,
            isset($data['activa']) ? (bool)$data['activa'] : true,
            (string)($data['docente'] ?? ''),
            (int)($data['total_matriculados'] ?? 0)
        );
    }

    public function toArray(): array {
        return [
            'id'                 => $this->id,
            'codigo'             => $this->codigo,
            'nombre'             => $this->nombre,
            'periodo'            => $this->periodo,
            'id_docente'         => $this->idDocente,
            'activa'             => $this->activa,
            'docente'            => $this->docente,
            'total_matriculados' => $this->totalMatriculados
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): void { $this->codigo = $codigo; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void { $this->nombre = $nombre; }

    public function getPeriodo(): string { return $this->periodo; }
    public function setPeriodo(string $periodo): void { $this->periodo = $periodo; }

    public function getIdDocente(): ?int { return $this->idDocente; }
    public function setIdDocente(?int $idDocente): void { $this->idDocente = $idDocente; }

    public function isActiva(): bool { return $this->activa; }
    public function setActiva(bool $activa): void { $this->activa = $activa; }

    public function getDocente(): string { return $this->docente; }
    public function setDocente(string $docente): void { $this->docente = $docente; }

    public function getTotalMatriculados(): int { return $this->totalMatriculados; }
    public function setTotalMatriculados(int $totalMatriculados): void { $this->totalMatriculados = $totalMatriculados; }
}
