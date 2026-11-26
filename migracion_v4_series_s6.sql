-- ==============================================================================
-- FIT TRACKER PRO - MIGRACIÓN SQL V4 (PESOS POR SERIE Y SEMANA 6)
-- Base de Datos: fit_tracker_db
-- Motor: MariaDB / MySQL (InnoDB con soporte UTF8mb4)
-- Archivo: migracion_v4_series_s6.sql
-- 
-- INSTRUCCIONES DE USO:
-- 1. Desde phpMyAdmin:
--    a) Selecciona la base de datos `fit_tracker_db` a la izquierda.
--    b) Haz clic en la pestaña "Importar" del menú superior (NO usar la pestaña "Estructura" ni formularios visuales).
--    c) En "Seleccionar archivo", elige este archivo `migracion_v4_series_s6.sql`.
--    d) Haz clic en el botón "Continuar" al final de la página.
--
-- 2. O desde la pestaña "SQL" de phpMyAdmin:
--    Copia y pega todo el contenido de este archivo en la caja de texto y pulsa "Continuar".
-- ==============================================================================

USE `fit_tracker_db`;

-- Desactivar temporalmente revisión de claves foráneas para operaciones seguras
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Agregar columna `pesos_series` si aún no existe en la tabla `registro_cargas`
-- ------------------------------------------------------------------------------
ALTER TABLE `registro_cargas` 
ADD COLUMN IF NOT EXISTS `pesos_series` TEXT NULL DEFAULT NULL 
COMMENT 'JSON array con pesos por serie ej [30, 35, 35, 45]' 
AFTER `peso_kg`;

-- ------------------------------------------------------------------------------
-- 2. Eliminar restricción de semana anterior (S1..S5) si existe
-- ------------------------------------------------------------------------------
ALTER TABLE `registro_cargas` 
DROP CONSTRAINT IF EXISTS `chk_semana_valida`;

-- ------------------------------------------------------------------------------
-- 3. Crear restricción actualizada que permite microciclos de Semana 1 a 6 (S1..S6)
-- ------------------------------------------------------------------------------
ALTER TABLE `registro_cargas` 
ADD CONSTRAINT `chk_semana_valida` CHECK (`semana` BETWEEN 1 AND 6);

-- ------------------------------------------------------------------------------
-- 4. Actualizar comentario de la columna semana
-- ------------------------------------------------------------------------------
ALTER TABLE `registro_cargas` 
MODIFY COLUMN `semana` INT NOT NULL COMMENT 'Semana del microciclo (1 al 6)';

-- Reactivar revisión de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- Verificación final informativa (muestra la estructura actualizada)
SHOW COLUMNS FROM `registro_cargas` LIKE 'pesos_series';
