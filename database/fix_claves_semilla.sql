-- ==============================================================================
-- Sistema de Control de Asistencia FISEI - Web
-- Universidad Técnica de Ambato (UTA)
-- Corrección de Claves de los Usuarios Semilla (admin.fisei / docente.fisei)
-- ==============================================================================
-- El hash bcrypt incluido en fisei_supabase.sql no corresponde a la clave
-- 'admin123' (password_verify() retorna false). Este script asigna un hash
-- generado con password_hash('admin123', PASSWORD_DEFAULT) a ambos usuarios.
--
-- Ejecutar una sola vez en: Supabase Dashboard -> SQL Editor
-- ==============================================================================

UPDATE usuarios
SET password = '$2y$10$sEgEfc9quA3il2J52Flk1OglOvYoPUavLPgfCpq7F.xRbOIGtZhgW'
WHERE username IN ('admin.fisei', 'docente.fisei');
