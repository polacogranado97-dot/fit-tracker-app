<?php
/**
 * Script PHP para aplicar la migración V4 directamente desde consola o navegador
 */
require_once __DIR__ . '/../db.php';
$pdo = getDBConnection();

echo "=== APLICANDO MIGRACIÓN V4: PESOS POR SERIE Y SEMANA 6 ===\n\n";

try {
    // 1. Columna pesos_series
    $pdo->exec("ALTER TABLE `registro_cargas` ADD COLUMN IF NOT EXISTS `pesos_series` TEXT NULL DEFAULT NULL COMMENT 'JSON array con pesos por serie ej [30, 35, 35, 45]' AFTER `peso_kg`");
    echo "  [OK] Columna `pesos_series` asegurada en `registro_cargas`.\n";

    // 2. Drop constraint
    $pdo->exec("ALTER TABLE `registro_cargas` DROP CONSTRAINT IF EXISTS `chk_semana_valida`");
    echo "  [OK] Restricción previa de semana liberada.\n";

    // 3. Add constraint 1..6
    $pdo->exec("ALTER TABLE `registro_cargas` ADD CONSTRAINT `chk_semana_valida` CHECK (`semana` BETWEEN 1 AND 6)");
    echo "  [OK] Restricción `chk_semana_valida` actualizada a semanas 1 a 6.\n";

    // 4. Update comment
    $pdo->exec("ALTER TABLE `registro_cargas` MODIFY COLUMN `semana` INT NOT NULL COMMENT 'Semana del microciclo (1 al 6)'");
    echo "  [OK] Comentario de columna `semana` actualizado.\n";

    echo "\nMigración aplicada con éxito sin errores.\n";
} catch (Exception $e) {
    echo "  [ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
