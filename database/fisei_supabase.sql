-- ==============================================================================
-- Sistema de Control de Asistencia FISEI - Web
-- Universidad Técnica de Ambato (UTA)
-- Respaldo del Esquema Oficial para PostgreSQL (Supabase)
-- ==============================================================================

-- 1. Limpiar tablas anteriores para evitar conflictos de nombres de columnas
DROP TABLE IF EXISTS asistencias CASCADE;
DROP TABLE IF EXISTS matriculas CASCADE;
DROP TABLE IF EXISTS estudiantes CASCADE;
DROP TABLE IF EXISTS materias CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

-- 2. Extensiones recomendadas
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ==============================================================================
-- 3. TABLA: usuarios (Docentes y Administradores de FISEI)
-- ==============================================================================
CREATE TABLE usuarios (
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
-- 4. TABLA: materias (Asignaturas asignadas a docentes)
-- ==============================================================================
CREATE TABLE materias (
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
-- 5. TABLA: estudiantes (Alumnos registrados con Llavero/Tarjeta RFID y Cédula)
-- ==============================================================================
CREATE TABLE estudiantes (
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
-- 6. TABLA: matriculas (Relación de inscripción Estudiante <-> Materia)
-- ==============================================================================
CREATE TABLE matriculas (
    id SERIAL PRIMARY KEY,
    id_estudiante INT NOT NULL REFERENCES estudiantes(id) ON DELETE CASCADE,
    id_materia INT NOT NULL REFERENCES materias(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_estudiante_materia UNIQUE (id_estudiante, id_materia)
);

COMMENT ON TABLE matriculas IS 'Asignación de asignaturas activas a cada estudiante matriculado';

-- ==============================================================================
-- 7. TABLA: asistencias (Con parcial y restricción única por fecha incluidos)
-- ==============================================================================
CREATE TABLE asistencias (
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
-- 8. ÍNDICES DE RENDIMIENTO PARA POSTGRESQL
-- ==============================================================================
CREATE INDEX idx_estudiantes_uid_rfid ON estudiantes (uid_rfid);
CREATE INDEX idx_estudiantes_cedula ON estudiantes (cedula);
CREATE INDEX idx_matriculas_estudiante ON matriculas (id_estudiante);
CREATE INDEX idx_matriculas_materia ON matriculas (id_materia);
CREATE INDEX idx_asistencias_estudiante_fecha ON asistencias (id_estudiante, fecha);
CREATE INDEX idx_asistencias_materia_fecha ON asistencias (id_materia, fecha);
CREATE INDEX idx_asistencias_parcial ON asistencias (parcial);

-- ==============================================================================
-- 9. DATOS SEMILLA (SEED DATA)
-- ==============================================================================

-- Usuarios con clave 'admin123' encriptada en bcrypt
INSERT INTO usuarios (username, password, nombre_completo, rol, activo)
VALUES 
    ('admin.fisei', '$2y$10$eA.4M2bUq84B7a1s5CkeeeR7zH2f7eK6ZgVwP1Xm8c2hYgBqDqSXe', 'Administrador FISEI', 'ADMIN', true),
    ('docente.fisei', '$2y$10$eA.4M2bUq84B7a1s5CkeeeR7zH2f7eK6ZgVwP1Xm8c2hYgBqDqSXe', 'Ing. Docente Titular FISEI', 'DOCENTE', true);

-- Asignaturas iniciales (asignadas al docente con id = 2)
INSERT INTO materias (codigo, nombre, periodo, id_docente, activa)
VALUES 
    ('SIS-501', 'Ingeniería de Software I', '2026-2027', 2, true),
    ('SIS-502', 'Arquitectura y Sistemas Distribuidos', '2026-2027', 2, true),
    ('SIS-401', 'Programación Orientada a Objetos', '2026-2027', 2, true),
    ('TEL-601', 'Redes de Computadoras y Conectividad', '2026-2027', 2, true);

-- Estudiantes iniciales con los 3 llaveros físicos reales
INSERT INTO estudiantes (uid_rfid, cedula, nombres, apellidos, carrera)
VALUES 
    ('0006047325', '1809876543', 'María Belén', 'Gómez Díaz', 'Software'),
    ('0007081242', '1805544332', 'Carlos Andrés', 'López Vega', 'Software'),
    ('0002553129', '1805095690', 'Axel Jair', 'Lopez Perez', 'Software');

-- Matrículas iniciales
INSERT INTO matriculas (id_estudiante, id_materia) VALUES
(1, 1), (1, 2), (2, 1), (3, 3);
