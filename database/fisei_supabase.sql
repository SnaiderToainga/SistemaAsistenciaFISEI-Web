-- ==============================================================================
-- Sistema de Control de Asistencia FISEI - Web
-- Universidad Técnica de Ambato (UTA)
-- Respaldo del Esquema Oficial para PostgreSQL (Supabase)
-- ==============================================================================

-- 1. EXTENSIONES RECOMENDADAS PARA POSTGRESQL
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ==============================================================================
-- 2. TABLA: usuarios (Docentes y Administradores de FISEI)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'DOCENTE' CHECK (rol IN ('ADMIN', 'DOCENTE')),
    activo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE usuarios IS 'Cuentas de docentes y administradores autorizados de FISEI';

-- ==============================================================================
-- 3. TABLA: materias (Asignaturas impartidas en el periodo)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS materias (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    periodo VARCHAR(20) NOT NULL DEFAULT '2026-2027',
    id_docente INT REFERENCES usuarios(id) ON DELETE SET NULL,
    activa BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE materias IS 'Asignaturas de las distintas carreras de la facultad';

-- ==============================================================================
-- 4. TABLA: estudiantes (Alumnos registrados con Llavero/Tarjeta RFID y Cédula)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS estudiantes (
    id SERIAL PRIMARY KEY,
    uid_rfid VARCHAR(50) NOT NULL UNIQUE,
    cedula VARCHAR(10) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    carrera VARCHAR(100) NOT NULL DEFAULT 'Software',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE estudiantes IS 'Estudiantes registrados con su identificador físico RFID y cédula ecuatoriana de 10 dígitos';

-- ==============================================================================
-- 5. TABLA: matriculas (Relación de inscripción Estudiante <-> Materia)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS matriculas (
    id SERIAL PRIMARY KEY,
    id_estudiante INT NOT NULL REFERENCES estudiantes(id) ON DELETE CASCADE,
    id_materia INT NOT NULL REFERENCES materias(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_estudiante_materia UNIQUE (id_estudiante, id_materia)
);

COMMENT ON TABLE matriculas IS 'Asignación de asignaturas activas a cada estudiante matriculado';

-- ==============================================================================
-- 6. TABLA: asistencias (Registros de aula mediante lector RFID por parcial)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS asistencias (
    id SERIAL PRIMARY KEY,
    id_estudiante INT NOT NULL REFERENCES estudiantes(id) ON DELETE CASCADE,
    id_materia INT NOT NULL REFERENCES materias(id) ON DELETE CASCADE,
    parcial VARCHAR(15) NOT NULL DEFAULT 'Parcial 1' CHECK (parcial IN ('Parcial 1', 'Parcial 2')),
    fecha DATE NOT NULL DEFAULT CURRENT_DATE,
    hora TIME NOT NULL DEFAULT CURRENT_TIME,
    estado VARCHAR(20) NOT NULL DEFAULT 'PRESENTE' CHECK (estado IN ('PRESENTE', 'ATRASO', 'FALTA', 'JUSTIFICADO')),
    observacion TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_asistencia_diaria UNIQUE (id_estudiante, id_materia, fecha)
);

COMMENT ON TABLE asistencias IS 'Historial de marcaciones por tarjeta/llavero RFID en aula con protección contra duplicidad diaria';

-- ==============================================================================
-- 7. ÍNDICES DE RENDIMIENTO PARA POSTGRESQL
-- ==============================================================================
CREATE INDEX IF NOT EXISTS idx_estudiantes_uid_rfid ON estudiantes (uid_rfid);
CREATE INDEX IF NOT EXISTS idx_estudiantes_cedula ON estudiantes (cedula);
CREATE INDEX IF NOT EXISTS idx_matriculas_estudiante ON matriculas (id_estudiante);
CREATE INDEX IF NOT EXISTS idx_matriculas_materia ON matriculas (id_materia);
CREATE INDEX IF NOT EXISTS idx_asistencias_estudiante_fecha ON asistencias (id_estudiante, fecha);
CREATE INDEX IF NOT EXISTS idx_asistencias_materia_fecha ON asistencias (id_materia, fecha);
CREATE INDEX IF NOT EXISTS idx_asistencias_parcial ON asistencias (parcial);

-- ==============================================================================
-- 8. DATOS SEMILLA (SEED DATA PARA INICIALIZACIÓN)
-- ==============================================================================

-- Usuarios iniciales (password: admin123 generado con password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO usuarios (username, password, nombre_completo, rol, activo)
VALUES
    ('admin.fisei', '$2y$10$sEgEfc9quA3il2J52Flk1OglOvYoPUavLPgfCpq7F.xRbOIGtZhgW', 'Administrador FISEI', 'ADMIN', true),
    ('docente.fisei', '$2y$10$sEgEfc9quA3il2J52Flk1OglOvYoPUavLPgfCpq7F.xRbOIGtZhgW', 'Ing. Docente Titular FISEI', 'DOCENTE', true)
ON CONFLICT (username) DO NOTHING;

-- Catálogo inicial de asignaturas activas asignadas al docente titular
INSERT INTO materias (codigo, nombre, periodo, id_docente, activa)
SELECT v.codigo, v.nombre, v.periodo, u.id, true
FROM (
    VALUES
        ('SIS-501', 'Ingeniería de Software I', '2026-2027'),
        ('SIS-502', 'Arquitectura y Sistemas Distribuidos', '2026-2027'),
        ('SIS-401', 'Programación Orientada a Objetos', '2026-2027'),
        ('TEL-601', 'Redes de Computadoras y Conectividad', '2026-2027')
) AS v(codigo, nombre, periodo)
LEFT JOIN usuarios u ON u.username = 'docente.fisei'
ON CONFLICT (codigo) DO NOTHING;

-- Estudiantes iniciales con los 3 llaveros físicos reales (preservando ceros a la izquierda)
INSERT INTO estudiantes (uid_rfid, cedula, nombres, apellidos, carrera)
VALUES
    ('0006047325', '1809876543', 'María Belén', 'Gómez Díaz', 'Software'),
    ('0007081242', '1805544332', 'Carlos Andrés', 'López Vega', 'Software'),
    ('0002553129', '1805095690', 'Axel Jair', 'Lopez Perez', 'Software')
ON CONFLICT (uid_rfid) DO NOTHING;

-- Matrículas iniciales de prueba
INSERT INTO matriculas (id_estudiante, id_materia)
SELECT e.id, m.id
FROM (
    VALUES
        ('0006047325', 'SIS-501'),
        ('0006047325', 'SIS-502'),
        ('0007081242', 'SIS-501'),
        ('0002553129', 'SIS-401')
) AS v(uid_rfid, codigo)
INNER JOIN estudiantes e ON e.uid_rfid = v.uid_rfid
INNER JOIN materias m ON m.codigo = v.codigo
ON CONFLICT (id_estudiante, id_materia) DO NOTHING;
