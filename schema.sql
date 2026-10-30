-- ==============================================================================
-- SISTEMA DE ENTRENAMIENTO PERSONALIZADO (FIT TRACKER APP)
-- Base de Datos: fit_tracker_db
-- Motor: MariaDB / MySQL (InnoDB con soporte UTF8mb4)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `fit_tracker_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `fit_tracker_db`;

-- ------------------------------------------------------------------------------
-- 1. TABLA: clientes
-- Datos personales, peso corporal actual y anamnesis básica / historial de lesiones
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `registro_cargas`;
DROP TABLE IF EXISTS `ejercicios_rutina`;
DROP TABLE IF EXISTS `rutinas_semana`;
DROP TABLE IF EXISTS `clientes`;

CREATE TABLE `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NULL,
    `telefono` VARCHAR(50) NULL,
    `peso_corporal` DECIMAL(5,2) NOT NULL DEFAULT 70.00 COMMENT 'Peso corporal actual en kg',
    `observaciones` TEXT NULL COMMENT 'Anamnesis, lesiones previas, objetivos y consideraciones médicas',
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_clientes_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. TABLA: rutinas_semana
-- Planificación o microciclo asignado a un cliente (duración típica de 5 semanas S1..S5)
-- ------------------------------------------------------------------------------
CREATE TABLE `rutinas_semana` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `nombre` VARCHAR(150) NOT NULL DEFAULT 'Mesociclo Fuerza & Hipertrofia (S1-S5)',
    `descripcion` TEXT NULL,
    `fecha_inicio` DATE NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rutina_cliente` FOREIGN KEY (`cliente_id`) 
        REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_rutina_cliente` (`cliente_id`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABLA: ejercicios_rutina
-- Ejercicios configurados por día (Día 1, Día 2, etc.) con sus series y repeticiones
-- ------------------------------------------------------------------------------
CREATE TABLE `ejercicios_rutina` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rutina_id` INT NOT NULL,
    `dia_semana` INT NOT NULL DEFAULT 1 COMMENT '1 = Día 1, 2 = Día 2, 3 = Día 3, etc.',
    `nombre_ejercicio` VARCHAR(150) NOT NULL,
    `series` INT NOT NULL DEFAULT 4 COMMENT 'Cantidad de series prescritas',
    `repeticiones` VARCHAR(50) NOT NULL DEFAULT '8-10' COMMENT 'Rango o número de repeticiones (ej. 8-10, 12, RIR 2)',
    `un_rm_estimado` DECIMAL(6,2) NULL DEFAULT 0.00 COMMENT '1RM de referencia en kg para calcular intensidad %',
    `observaciones` TEXT NULL COMMENT 'Detalles técnicos, tempo (3-0-1-0), descansos o variantes',
    `orden` INT NOT NULL DEFAULT 1 COMMENT 'Orden de ejecución dentro del día',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ejercicio_rutina` FOREIGN KEY (`rutina_id`) 
        REFERENCES `rutinas_semana` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_ejercicio_dia` (`rutina_id`, `dia_semana`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABLA: registro_cargas
-- Registro semanal (S1 a S5) del peso aplicado (kg) y porcentaje de intensidad
-- ------------------------------------------------------------------------------
CREATE TABLE `registro_cargas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ejercicio_id` INT NOT NULL,
    `semana` INT NOT NULL COMMENT 'Número de semana del microciclo (1 al 5)',
    `peso_kg` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Carga efectiva en kilogramos',
    `porcentaje_carga` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de carga / intensidad (% sobre 1RM o plan)',
    `completado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Serie/ejercicio completado con éxito',
    `notas` VARCHAR(255) NULL COMMENT 'Sensaciones, RPE o anotación breve del atleta',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_carga_ejercicio` FOREIGN KEY (`ejercicio_id`) 
        REFERENCES `ejercicios_rutina` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_semana_valida` CHECK (`semana` BETWEEN 1 AND 5),
    UNIQUE KEY `uk_ejercicio_semana` (`ejercicio_id`, `semana`),
    INDEX `idx_registro_semana` (`semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==============================================================================
-- DATOS INICIALES DE PRUEBA (SEED DATA)
-- ==============================================================================

-- 1. Clientes iniciales
INSERT INTO `clientes` (`id`, `nombre`, `apellido`, `email`, `telefono`, `peso_corporal`, `observaciones`, `activo`) VALUES
(1, 'Mateo', 'Valenzuela', 'mateo.valenzuela@email.com', '+54 9 11 4567-8901', 78.50, 'Molestia leve en hombro derecho (evitar agarre excesivamente ancho en banca). Objetivo: Sobrecarga progresiva e hipertrofia.', 1),
(2, 'Sofía', 'Rossi', 'sofia.rossi@email.com', '+54 9 11 9876-5432', 62.20, 'Sin patologías ni lesiones previas. Nivel intermedio. Foco en fuerza de tren inferior y estabilidad de core.', 1);

-- 2. Rutina asignada al Cliente 1
INSERT INTO `rutinas_semana` (`id`, `cliente_id`, `nombre`, `descripcion`, `fecha_inicio`, `activo`) VALUES
(1, 1, 'Microciclo Progresivo 5S - Fuerza & Hipertrofia', 'Estructura lineal S1 a S5 con aumento escalonado de intensidad y descarga técnica en S5.', '2026-10-01', 1);

-- 3. Ejercicios distribuidos por Días (Día 1: Torso, Día 2: Pierna, Día 3: Fullbody / Empuje-Tracción)
INSERT INTO `ejercicios_rutina` (`id`, `rutina_id`, `dia_semana`, `nombre_ejercicio`, `series`, `repeticiones`, `un_rm_estimado`, `observaciones`, `orden`) VALUES
-- Día 1
(1, 1, 1, 'Press de Banca Plano con Barra', 4, '8-10', 100.00, 'Pausa de 1 segundo en el pecho. Retracción escapular activa.', 1),
(2, 1, 1, 'Remo con Barra Prono', 4, '10-12', 85.00, 'Torso a 45 grados, codos pegados al cuerpo, foco en dorsal.', 2),
(3, 1, 1, 'Press Militar con Mancuernas', 3, '10-12', 30.00, 'Sentado en banco a 75°. Rango de recorrido completo.', 3),
(4, 1, 1, 'Fondos en Paralelas con Lastre', 3, '10-12', 25.00, 'Inclinación ligera hacia adelante para énfasis en pectoral.', 4),

-- Día 2
(5, 1, 2, 'Sentadilla Trasera con Barra (Barra Alta)', 4, '6-8', 130.00, 'Profundidad paralela o sub-paralela. Respiración diafragmática (valsalva).', 1),
(6, 1, 2, 'Peso Muerto Rumano', 4, '8-10', 110.00, 'Bisagra de cadera pura, rodillas semirrígidas, sentir estiramiento en isquios.', 2),
(7, 1, 2, 'Prensa de Piernas 45°', 3, '12-15', 200.00, 'Pies al centro de la plataforma. No bloquear rodillas en extensión.', 3),
(8, 1, 2, 'Elevación de Talones de Pie', 4, '15-20', 80.00, 'Contracción máxima de 2 segundos en el pico.', 4),

-- Día 3
(9, 1, 3, 'Dominadas con Peso / Asistidas', 4, '6-8', 20.00, 'Agarre neutro o prono al ancho biacromial. Controlar la fase excéntrica.', 1),
(10, 1, 3, 'Press Inclinado con Mancuernas', 3, '10-12', 34.00, 'Banco a 30 grados. Buen estiramiento en la porción clavicular.', 2),
(11, 1, 3, 'Curl de Bíceps con Barra Z', 3, '12-15', 35.00, 'Codos fijos a los costados, sin balanceo lumbar.', 3),
(12, 1, 3, 'Extensiones de Tríceps en Polea Alta', 3, '12-15', 40.00, 'Cuerda o barra recta, apertura final con cuerda.', 4);

-- 4. Registro de Cargas iniciales para S1, S2 y S3 (Demostración de progresión de cargas y %)
INSERT INTO `registro_cargas` (`ejercicio_id`, `semana`, `peso_kg`, `porcentaje_carga`, `completado`, `notas`) VALUES
-- Ejercicio 1 (Press Banca - 1RM: 100 kg)
(1, 1, 75.00, 75.00, 1, 'Buenas sensaciones, RPE 7. Hombro sin molestias'),
(1, 2, 77.50, 77.50, 1, 'RPE 7.5, velocidad de barra óptima'),
(1, 3, 80.00, 80.00, 1, 'RPE 8.0, buena sobrecarga'),
(1, 4, 82.50, 82.50, 0, 'Planificado para S4'),
(1, 5, 85.00, 85.00, 0, 'Objetivo cierre de ciclo'),

-- Ejercicio 2 (Remo con Barra - 1RM: 85 kg)
(2, 1, 60.00, 70.58, 1, 'Completadas las 4 series con técnica limpia'),
(2, 2, 62.50, 73.52, 1, 'Excelente agarre y contracción dorsal'),
(2, 3, 65.00, 76.47, 0, 'En curso'),

-- Ejercicio 5 (Sentadilla Trasera - 1RM: 130 kg)
(5, 1, 95.00, 73.07, 1, 'Activación de glúteo previa muy positiva'),
(5, 2, 100.00, 76.92, 1, 'RPE 7.5, profundidad controlada'),
(5, 3, 105.00, 80.76, 0, 'Objetivo para esta semana');
