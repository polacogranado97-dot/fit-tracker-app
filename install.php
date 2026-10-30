<?php
/**
 * ==============================================================================
 * INSTALADOR Y MIGRACIÓN DE BASE DE DATOS (install.php)
 * Fit Tracker App - Inicializa la base de datos fit_tracker_db y carga schema.sql
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getDBConnection();
    
    // Leer schema_v2.sql con codificación UTF-8
    $sqlFile = __DIR__ . '/schema_v2.sql';
    if (!file_exists($sqlFile)) {
        $sqlFile = __DIR__ . '/schema.sql';
    }
    if (!file_exists($sqlFile)) {
        throw new Exception("El archivo de esquema SQL no se encuentra en el directorio raíz.");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Ejecutar statements
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec($sql);
    
    $success = true;
    $message = "¡Base de datos 'fit_tracker_db' inicializada correctamente con datos de prueba!";
} catch (Exception $e) {
    $success = false;
    $message = "Error en la inicialización: " . $e->getMessage();
}

if (php_sapi_name() === 'cli') {
    echo ($success ? "[OK] " : "[ERROR] ") . $message . PHP_EOL;
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador FitTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl text-center">
        <div class="w-16 h-16 rounded-full mx-auto flex items-center justify-center mb-4 <?= $success ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?>">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <?php if ($success): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                <?php endif; ?>
            </svg>
        </div>
        <h1 class="text-xl font-bold mb-2"><?= $success ? 'Instalación Exitosa' : 'Error de Instalación' ?></h1>
        <p class="text-sm text-slate-400 mb-6"><?= htmlspecialchars($message) ?></p>
        <a href="index.html" class="inline-block w-full py-3 px-4 bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-bold rounded-xl shadow-lg hover:brightness-110 transition">
            Abrir FitTracker App
        </a>
    </div>
</body>
</html>
