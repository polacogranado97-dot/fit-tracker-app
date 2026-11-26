<?php
require_once __DIR__ . '/../db.php';
$pdo = getDBConnection();

echo "=== MIGRACIÓN V4: PESOS POR SERIE Y SEMANA 6 (S6) ===\n\n";

try {
    // 1. Agregar columna pesos_series a registro_cargas si no existe
    $cols = $pdo->query("SHOW COLUMNS FROM registro_cargas LIKE 'pesos_series'")->fetchAll();
    if (empty($cols)) {
        echo "Agregando columna 'pesos_series' a 'registro_cargas'...\n";
        $pdo->exec("ALTER TABLE `registro_cargas` ADD COLUMN `pesos_series` TEXT NULL DEFAULT NULL COMMENT 'JSON array con pesos por serie ej [30, 35, 35, 45]' AFTER `peso_kg`");
        echo "Columna 'pesos_series' agregada con éxito.\n";
    } else {
        echo "La columna 'pesos_series' ya existe.\n";
    }

    // 2. Modificar constraint chk_semana_valida para soportar semanas 1 a 6
    echo "Actualizando constraint de semana (1 a 6)...\n";
    try {
        $pdo->exec("ALTER TABLE `registro_cargas` DROP CONSTRAINT `chk_semana_valida`");
        echo "Constraint anterior eliminada.\n";
    } catch (Exception $e) {
        echo "Aviso al eliminar constraint: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `registro_cargas` ADD CONSTRAINT `chk_semana_valida` CHECK (`semana` BETWEEN 1 AND 6)");
        echo "Constraint `chk_semana_valida` (1 a 6) añadida con éxito.\n";
    } catch (Exception $e) {
        echo "Error al añadir constraint: " . $e->getMessage() . "\n";
    }

    echo "\n=== VERIFICACIÓN DE ESTRUCTURA ===\n";
    $create = $pdo->query("SHOW CREATE TABLE registro_cargas")->fetch();
    echo $create['Create Table'] . "\n";
    echo "\nMigración completada con éxito.\n";
} catch (Exception $e) {
    echo "ERROR CRÍTICO EN MIGRACIÓN: " . $e->getMessage() . "\n";
}
