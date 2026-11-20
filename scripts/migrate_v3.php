<?php
/**
 * ==============================================================================
 * MIGRACIÓN DE BASE DE DATOS V3 - SAAS MULTI-TENANT & CALENTAMIENTO PREVIO
 * ==============================================================================
 */

require_once __DIR__ . '/../db.php';

$pdo = getDBConnection();

echo "Iniciando migración v3...\n";

// 1. Agregar columna admin_id a usuarios si no existe
$cols = $pdo->query("SHOW COLUMNS FROM `usuarios` LIKE 'admin_id'")->fetchAll();
if (empty($cols)) {
    echo "Agregando columna admin_id a usuarios...\n";
    $pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `admin_id` INT NULL DEFAULT NULL AFTER `rol`");
    $pdo->exec("ALTER TABLE `usuarios` ADD INDEX `idx_usuarios_admin` (`admin_id`, `rol`, `activo`)");
    
    // Asignar los clientes existentes al admin con ID 1
    $pdo->exec("UPDATE `usuarios` SET `admin_id` = 1 WHERE `rol` = 'cliente' AND `admin_id` IS NULL");
    echo "Columna admin_id agregada y clientes existentes asignados al Admin ID 1.\n";
} else {
    echo "La columna admin_id ya existe en usuarios.\n";
}

// 2. Crear tabla rutina_calentamiento para bloques de movilidad previa
echo "Creando tabla rutina_calentamiento si no existe...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS `rutina_calentamiento` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
echo "Tabla rutina_calentamiento lista.\n";

// 3. Insertar movilidad de prueba para Mateo (Rutina ID 1) para Día 1 y Día 2
$stmtCheckMov = $pdo->query("SELECT COUNT(*) FROM `rutina_calentamiento` WHERE `rutina_id` = 1");
if ($stmtCheckMov->fetchColumn() == 0) {
    echo "Insertando movilidad inicial de demostración para Rutina 1...\n";
    $pdo->exec("INSERT INTO `rutina_calentamiento` (`rutina_id`, `dia_semana`, `movilidad`) VALUES
    (1, 1, '• 5 min elíptica o caminadora suave (RPE 4-5)\n• Dislocaciones de hombro con banda elástica: 2 series x 15 reps\n• Face pulls ligeros con banda: 2 series x 15 reps\n• Series de aproximación en press banca: barra sola x 15 reps, 50% x 6 reps'),
    (1, 2, '• 5 min bicicleta estática para temperatura de rodilla\n• Movilidad de tobillo contra pared: 2 series x 12 reps por lado\n• Puente de glúteo bipodal con pausa de 2 seg: 2 series x 15 reps\n• Sentadilla goblet ligera de calentamiento: 2 series x 10 reps');");
}

// 4. Índices adicionales de rendimiento para soportar +100 alumnos
echo "Verificando optimizaciones de índices...\n";
try {
    $pdo->exec("ALTER TABLE `rutinas_semana` ADD INDEX `idx_rutinas_cliente_activa` (`cliente_id`, `activo`, `id`)");
} catch (Exception $e) {
    // Si ya existe, continuar
}

try {
    $pdo->exec("ALTER TABLE `ejercicios_rutina` ADD INDEX `idx_ejercicios_rutina_dia` (`rutina_id`, `dia_semana`, `orden`)");
} catch (Exception $e) {
    // Si ya existe, continuar
}

echo "Migración v3 completada con éxito.\n";
