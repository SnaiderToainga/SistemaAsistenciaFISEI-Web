-- ==============================================================================
-- Sistema de Control de Asistencia FISEI - Web
-- Universidad Técnica de Ambato (UTA)
-- Respaldo del Esquema de Base de Datos para PostgreSQL (Supabase)
-- ==============================================================================

-- 1. EXTENSIONES RECOMENDADAS PARA POSTGRESQL
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ==============================================================================
-- 2. TABLA: usuarios (Docentes y Administradores del Sistema)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'DOCENTE', -- 'ADMIN', 'DOCENTE'
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
-- 6. TABLA: asistencias (Registros biométricos y de aula mediante lector RFID)
-- ==============================================================================
CREATE TABLE IF NOT EXISTS asistencias (
    id SERIAL PRIMARY KEY,
    id_estudiante INT NOT NULL REFERENCES estudiantes(id) ON DELETE CASCADE,
    id_materia INT NOT NULL REFERENCES materias(id) ON DELETE CASCADE,
    fecha DATE NOT NULL DEFAULT CURRENT_DATE,
    hora TIME NOT NULL DEFAULT CURRENT_TIME,
    estado VARCHAR(20) NOT NULL DEFAULT 'PRESENTE', -- 'PRESENTE', 'ATRASO', 'FALTA', 'JUSTIFICADO'
    observacion TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE asistencias IS 'Historial de marcaciones por tarjeta/llavero RFID en aula';

-- ==============================================================================
-- 7. ÍNDICES DE RENDIMIENTO PARA POSTGRESQL
-- ==============================================================================
CREATE INDEX IF NOT EXISTS idx_estudiantes_uid_rfid ON estudiantes (uid_rfid);
CREATE INDEX IF NOT EXISTS idx_estudiantes_cedula ON estudiantes (cedula);
CREATE INDEX IF NOT EXISTS idx_matriculas_estudiante ON matriculas (id_estudiante);
CREATE INDEX IF NOT EXISTS idx_matriculas_materia ON matriculas (id_materia);
CREATE INDEX IF NOT EXISTS idx_asistencias_estudiante_fecha ON asistencias (id_estudiante, fecha);
CREATE INDEX IF NOT EXISTS idx_asistencias_materia_fecha ON asistencias (id_materia, fecha);

-- ==============================================================================
-- 8. DATOS SEMILLA DE PRUEBA (SEED DATA PARA INICIALIZACIÓN)
-- ==============================================================================

-- Usuario Administrador / Docente por defecto (password: admin123 con hash bcrypt)
INSERT INTO usuarios (username, password, nombre_completo, rol, activo)
VALUES 
    ('admin.fisei', '$2y$10$eA.4M2bUq84B7a1s5CkeeeR7zH2f7eK6ZgVwP1Xm8c2hYgBqDqSXe', 'Administrador FISEI', 'ADMIN', true),
    ('docente.fisei', '$2y$10$eA.4M2bUq84B7a1s5CkeeeR7zH2f7eK6ZgVwP1Xm8c2hYgBqDqSXe', 'Ing. Docente Titular FISEI', 'DOCENTE', true)
ON CONFLICT (username) DO NOTHING;

-- Catálogo de asignaturas activas de FISEI
INSERT INTO materias (codigo, nombre, periodo, activa)
VALUES 
    ('SIS-501', 'Ingeniería de Software I', '2026-2027', true),
    ('SIS-502', 'Arquitectura y Sistemas Distribuidos', '2026-2027', true),
    ('SIS-401', 'Programación Orientada a Objetos', '2026-2027', true),
    ('TEL-601', 'Redes de Computadoras y Conectividad', '2026-2027', true),
    ('IND-402', 'Gestión de Procesos y Calidad', '2026-2027', true),
    ('SIS-701', 'Inteligencia Artificial y Machine Learning', '2026-2027', true)
ON CONFLICT (codigo) DO NOTHING;

-- Estudiante de prueba con UID RFID con ceros a la izquierda para verificar preservación estricta de string
INSERT INTO estudiantes (uid_rfid, cedula, nombres, apellidos, carrera)
VALUES 
    ('0006047325', '1804567890', 'Carlos Andrés', 'Morales Freire', 'Software')
ON CONFLICT (uid_rfid) DO NOTHING;

-- Matrícula inicial del estudiante de prueba en asignaturas activas
INSERT INTO matriculas (id_estudiante, id_materia)
SELECT e.id, m.id 
FROM estudiantes e, materias m 
WHERE e.uid_rfid = '0006047325' AND m.codigo IN ('SIS-501', 'SIS-502', 'SIS-701')
ON CONFLICT (id_estudiante, id_materia) DO NOTHING;

