<?php
/**
 * Entidad Usuario (Docente / Administrador).
 */

class Usuario {

    private ?int $id;
    private string $username;
    private ?string $password;
    private string $nombreCompleto;
    private string $rol;
    private bool $activo;

    public function __construct(
        ?int $id = null,
        string $username = '',
        ?string $password = null,
        string $nombreCompleto = '',
        string $rol = 'DOCENTE',
        bool $activo = true
    ) {
        $this->id             = $id;
        $this->username       = $username;
        $this->password       = $password;
        $this->nombreCompleto = $nombreCompleto;
        $this->rol            = $rol;
        $this->activo         = $activo;
    }

    public static function fromArray(array $data): self {
        return new self(
            isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null,
            trim((string)($data['username'] ?? '')),
            isset($data['password']) ? (string)$data['password'] : null,
            trim((string)($data['nombre_completo'] ?? '')),
            strtoupper(trim((string)($data['rol'] ?? 'DOCENTE'))),
            isset($data['activo']) ? (bool)$data['activo'] : true
        );
    }

    public function toArray(bool $incluirPassword = false): array {
        $datos = [
            'id'              => $this->id,
            'username'        => $this->username,
            'nombre_completo' => $this->nombreCompleto,
            'rol'             => $this->rol,
            'activo'          => $this->activo
        ];

        if ($incluirPassword) {
            $datos['password'] = $this->password;
        }

        return $datos;
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): void { $this->username = $username; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): void { $this->password = $password; }

    public function getNombreCompleto(): string { return $this->nombreCompleto; }
    public function setNombreCompleto(string $nombreCompleto): void { $this->nombreCompleto = $nombreCompleto; }

    public function getRol(): string { return $this->rol; }
    public function setRol(string $rol): void { $this->rol = $rol; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): void { $this->activo = $activo; }
}
