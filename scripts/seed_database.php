<?php
require_once __DIR__ . '/../db.php';
$pdo = getDBConnection();

$sql = file_get_contents(__DIR__ . '/../schema_v2.sql');
// Split by semicolon statements safely or execute directly with PDO multi-query
try {
    $pdo->exec($sql);
    echo "Seed schema_v2.sql executed successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
