-- ==============================================================================
-- FIT TRACKER APP - SCHEMA V2 (SISTEMA MULTI-ROL Y FOTOS DE PROGRESO)
-- Base de Datos: fit_tracker_db
-- Motor: MariaDB / MySQL (InnoDB con soporte UTF8mb4)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `fit_tracker_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `fit_tracker_db`;

-- Desactivar temporalmente restricciones foráneas para reconstrucción limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `fotos_progreso`;
DROP TABLE IF EXISTS `registro_cargas`;
DROP TABLE IF EXISTS `ejercicios_rutina`;
DROP TABLE IF EXISTS `rutinas_semana`;
DROP VIEW IF EXISTS `clientes`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. TABLA: usuarios (Gestión de Roles: Admin vs. Cliente)
-- ------------------------------------------------------------------------------
CREATE TABLE `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol` ENUM('admin', 'cliente') NOT NULL DEFAULT 'cliente',
    `admin_id` INT NULL DEFAULT NULL COMMENT 'Aislamiento Multi-Tenant: ID del admin propietario',
    `peso_corporal` DECIMAL(5,2) NOT NULL DEFAULT 70.00 COMMENT 'Peso en kg',
    `telefono` VARCHAR(50) NULL,
    `observaciones` TEXT NULL COMMENT 'Anamnesis, historial de lesiones y metas',
    `foto_perfil` VARCHAR(255) NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_usuarios_rol` (`rol`, `activo`),
    INDEX `idx_usuarios_admin` (`admin_id`, `rol`, `activo`),
    INDEX `idx_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 1.1 TABLA: rutina_calentamiento (Bloque de Movilidad Previa sin GIFs/imágenes)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rutina_calentamiento` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rutina_id` INT NOT NULL,
    `dia_semana` INT NOT NULL DEFAULT 1,
    `movilidad` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_calentamiento_rutina` FOREIGN KEY (`rutina_id`) 
        REFERENCES `rutinas_semana` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uk_rutina_dia_calentamiento` (`rutina_id`, `dia_semana`),
    INDEX `idx_calentamiento_rutina` (`rutina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista retrocompatible para clientes
CREATE VIEW `clientes` AS 
    SELECT `id`, `nombre`, `apellido`, `email`, `telefono`, `peso_corporal`, `observaciones`, `activo`, `created_at`, `updated_at`
    FROM `usuarios` 
    WHERE `rol` = 'cliente';

-- ------------------------------------------------------------------------------
-- 2. TABLA: rutinas_semana (Microciclos de 5 semanas asignados al cliente)
-- ------------------------------------------------------------------------------
CREATE TABLE `rutinas_semana` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL COMMENT 'FK hacia usuarios.id (con rol cliente)',
    `nombre` VARCHAR(150) NOT NULL DEFAULT 'Mesociclo Fuerza & Hipertrofia (S1-S5)',
    `descripcion` TEXT NULL,
    `fecha_inicio` DATE NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rutina_usuario` FOREIGN KEY (`cliente_id`) 
        REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_rutina_cliente` (`cliente_id`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABLA: ejercicios_rutina (Con soporte de imagen demostrativa de técnica)
-- ------------------------------------------------------------------------------
CREATE TABLE `ejercicios_rutina` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rutina_id` INT NOT NULL,
    `dia_semana` INT NOT NULL DEFAULT 1 COMMENT '1 = Día 1, 2 = Día 2, etc.',
    `nombre_ejercicio` VARCHAR(150) NOT NULL,
    `series` INT NOT NULL DEFAULT 4,
    `repeticiones` VARCHAR(50) NOT NULL DEFAULT '8-10',
    `un_rm_estimado` DECIMAL(6,2) NULL DEFAULT 0.00 COMMENT '1RM de referencia para calcular %',
    `imagen_url` VARCHAR(500) NULL COMMENT 'Ruta local o URL directa externa de imagen/GIF de MuscleWiki',
    `observaciones` TEXT NULL COMMENT 'Indicaciones técnicas, tempo y variantes',
    `orden` INT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ejercicio_rutina` FOREIGN KEY (`rutina_id`) 
        REFERENCES `rutinas_semana` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_ejercicio_dia` (`rutina_id`, `dia_semana`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABLA: registro_cargas (Seguimiento semanal S1 a S5)
-- ------------------------------------------------------------------------------
CREATE TABLE `registro_cargas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ejercicio_id` INT NOT NULL,
    `semana` INT NOT NULL COMMENT '1 al 5',
    `peso_kg` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `porcentaje_carga` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `completado` TINYINT(1) NOT NULL DEFAULT 0,
    `notas` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_carga_ejercicio` FOREIGN KEY (`ejercicio_id`) 
        REFERENCES `ejercicios_rutina` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_semana_valida` CHECK (`semana` BETWEEN 1 AND 5),
    UNIQUE KEY `uk_ejercicio_semana` (`ejercicio_id`, `semana`),
    INDEX `idx_registro_semana` (`semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. TABLA: fotos_progreso (Módulo de Galería y Comparación de Evolución)
-- ------------------------------------------------------------------------------
CREATE TABLE `fotos_progreso` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `ruta_imagen` VARCHAR(255) NOT NULL,
    `fecha_subida` DATE NOT NULL,
    `semana` INT NOT NULL DEFAULT 1 COMMENT 'Semana del microciclo (1..5)',
    `tipo_angulo` VARCHAR(50) NOT NULL DEFAULT 'frente' COMMENT 'frente, perfil, espalda',
    `observaciones` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_foto_usuario` FOREIGN KEY (`usuario_id`) 
        REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_fotos_usuario_fecha` (`usuario_id`, `fecha_subida`),
    INDEX `idx_fotos_semana` (`usuario_id`, `semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==============================================================================
-- DATOS INICIALES DE PRUEBA (SEED DATA V2)
-- ==============================================================================

-- 1. Usuarios (1 Administrador / Entrenador y 2 Clientes)
-- Contraseñas:
-- admin@fittracker.com      -> admin123
-- mateo.valenzuela@email.com -> cliente123
-- sofia.rossi@email.com      -> cliente123
INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `email`, `password_hash`, `rol`, `peso_corporal`, `telefono`, `observaciones`, `activo`) VALUES
(1, 'Alejandro', 'Gómez (Coach)', 'admin@fittracker.com', '$2y$10$RAkHaEAwQ6j6NxKNKQG4Je5VAVQTELrQVP/9Ve88v8cdhiMMB7lpG', 'admin', 82.00, '+54 9 11 0000-1111', 'Entrenador principal y administrador del sistema.', 1),
(2, 'Mateo', 'Valenzuela', 'mateo.valenzuela@email.com', '$2y$10$e4pq/pu2Fpo2xw67.167VeVfFUPC8.5Rn2oGMh5aiZeb9oFLqOBt.', 'cliente', 78.50, '+54 9 11 4567-8901', 'Molestia leve en hombro derecho (evitar agarre excesivamente ancho en banca). Objetivo: Sobrecarga progresiva e hipertrofia.', 1),
(3, 'Sofía', 'Rossi', 'sofia.rossi@email.com', '$2y$10$e4pq/pu2Fpo2xw67.167VeVfFUPC8.5Rn2oGMh5aiZeb9oFLqOBt.', 'cliente', 62.20, '+54 9 11 9876-5432', 'Sin patologías ni lesiones previas. Nivel intermedio. Foco en tren inferior y estabilidad.', 1);

-- 2. Rutina asignada al Cliente Mateo (ID = 2)
INSERT INTO `rutinas_semana` (`id`, `cliente_id`, `nombre`, `descripcion`, `fecha_inicio`, `activo`) VALUES
(1, 2, 'Microciclo Progresivo 5S - Fuerza & Hipertrofia', 'Estructura lineal S1 a S5 con aumento escalonado de intensidad y descarga técnica en S5.', '2026-10-01', 1);

-- 3. Ejercicios con imagen técnica ilustrativa (Día 1, Día 2, Día 3)
INSERT INTO `ejercicios_rutina` (`id`, `rutina_id`, `dia_semana`, `nombre_ejercicio`, `series`, `repeticiones`, `un_rm_estimado`, `imagen_url`, `observaciones`, `orden`) VALUES
-- Día 1
(1, 1, 1, 'Press de Banca Plano con Barra', 4, '8-10', 100.00, 'uploads/ejercicios/bench_press.svg', 'Pausa de 1 segundo en el pecho. Retracción escapular activa.', 1),
(2, 1, 1, 'Remo con Barra Prono', 4, '10-12', 85.00, 'uploads/ejercicios/barbell_row.svg', 'Torso a 45 grados, codos pegados al cuerpo, foco en dorsal.', 2),
(3, 1, 1, 'Press Militar con Mancuernas', 3, '10-12', 30.00, 'uploads/ejercicios/overhead_press.svg', 'Sentado en banco a 75°. Rango de recorrido completo.', 3),
(4, 1, 1, 'Fondos en Paralelas con Lastre', 3, '10-12', 25.00, NULL, 'Inclinación ligera hacia adelante para énfasis en pectoral.', 4),

-- Día 2
(5, 1, 2, 'Sentadilla Trasera con Barra (Barra Alta)', 4, '6-8', 130.00, 'uploads/ejercicios/squat.svg', 'Profundidad paralela o sub-paralela. Respiración diafragmática (valsalva).', 1),
(6, 1, 2, 'Peso Muerto Rumano', 4, '8-10', 110.00, 'uploads/ejercicios/deadlift.svg', 'Bisagra de cadera pura, rodillas semirrígidas, sentir estiramiento en isquios.', 2),
(7, 1, 2, 'Prensa de Piernas 45°', 3, '12-15', 200.00, NULL, 'Pies al centro de la plataforma. No bloquear rodillas en extensión.', 3),
(8, 1, 2, 'Elevación de Talones de Pie', 4, '15-20', 80.00, NULL, 'Contracción máxima de 2 segundos en el pico.', 4),

-- Día 3
(9, 1, 3, 'Dominadas con Peso / Asistidas', 4, '6-8', 20.00, NULL, 'Agarre neutro o prono al ancho biacromial. Controlar la fase excéntrica.', 1),
(10, 1, 3, 'Press Inclinado con Mancuernas', 3, '10-12', 34.00, NULL, 'Banco a 30 grados. Buen estiramiento en la porción clavicular.', 2),
(11, 1, 3, 'Curl de Bíceps con Barra Z', 3, '12-15', 35.00, NULL, 'Codos fijos a los costados, sin balanceo lumbar.', 3),
(12, 1, 3, 'Extensiones de Tríceps en Polea Alta', 3, '12-15', 40.00, NULL, 'Cuerda o barra recta, apertura final con cuerda.', 4);

-- 4. Registro de Cargas iniciales para S1..S3
INSERT INTO `registro_cargas` (`ejercicio_id`, `semana`, `peso_kg`, `porcentaje_carga`, `completado`, `notas`) VALUES
(1, 1, 75.00, 75.00, 1, 'Buenas sensaciones, RPE 7. Hombro sin molestias'),
(1, 2, 77.50, 77.50, 1, 'RPE 7.5, velocidad de barra óptima'),
(1, 3, 80.00, 80.00, 1, 'RPE 8.0, buena sobrecarga'),
(1, 4, 82.50, 82.50, 0, 'Planificado para S4'),
(1, 5, 85.00, 85.00, 0, 'Objetivo cierre de ciclo'),
(2, 1, 60.00, 70.58, 1, 'Completadas las 4 series con técnica limpia'),
(2, 2, 62.50, 73.52, 1, 'Excelente agarre y contracción dorsal'),
(5, 1, 95.00, 73.07, 1, 'Activación de glúteo previa muy positiva'),
(5, 2, 100.00, 76.92, 1, 'RPE 7.5, profundidad controlada');

-- 5. Fotos de progreso iniciales para Mateo (para probar el comparador S1 vs S4)
INSERT INTO `fotos_progreso` (`id`, `usuario_id`, `ruta_imagen`, `fecha_subida`, `semana`, `tipo_angulo`, `observaciones`) VALUES
(1, 2, 'uploads/progreso/sample_s1_frente.svg', '2026-10-02', 1, 'frente', 'Inicio del microciclo S1. Peso: 78.5 kg. Foto tomada en ayunas.'),
(2, 2, 'uploads/progreso/sample_s1_perfil.svg', '2026-10-02', 1, 'perfil', 'Postura lateral de inicio S1.'),
(3, 2, 'uploads/progreso/sample_s3_frente.svg', '2026-10-16', 3, 'frente', 'Semana 3. Mayor definición y vascularización en hombros. Peso: 79.1 kg.'),
(4, 2, 'uploads/progreso/sample_s4_frente.svg', '2026-10-23', 4, 'frente', 'Semana 4. Progresión visible en dorsal y pectoral superior. Peso: 79.4 kg.');
