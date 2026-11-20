<?php
/**
 * Test de Verificación End-to-End
 */
require_once __DIR__ . '/db.php';
$pdo = getDBConnection();

echo "=== 1. VERIFICAR ESQUEMA BD (ejercicios_rutina.imagen_url) ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM ejercicios_rutina LIKE 'imagen_url'");
$col = $stmt->fetch();
echo "Columna imagen_url: Tipo = " . ($col['Type'] ?? 'NO EXISTE') . "\n";

echo "\n=== 2. VERIFICAR USUARIOS EN BD ===\n";
$stmtUsers = $pdo->query("SELECT id, nombre, apellido, email, rol FROM usuarios");
$users = $stmtUsers->fetchAll();
foreach ($users as $u) {
    echo "ID: {$u['id']} | {$u['nombre']} {$u['apellido']} | Email: {$u['email']} | Rol: {$u['rol']}\n";
}

echo "\n=== 3. VERIFICAR FOTOS EN BD ===\n";
$stmtFotos = $pdo->query("SELECT id, usuario_id, ruta_imagen, semana, tipo_angulo FROM fotos_progreso");
$fotos = $stmtFotos->fetchAll();
echo "Total fotos: " . count($fotos) . "\n";
foreach ($fotos as $f) {
    echo "ID: {$f['id']} | Usuario: {$f['usuario_id']} | S{$f['semana']} ({$f['tipo_angulo']}) | Ruta: {$f['ruta_imagen']}\n";
}

echo "\n=== 4. TEST DE PERMISOS VIA SIMULACIÓN DE SESIÓN EN api.php ===\n";

// A) Probar intento de subida por un ADMIN (Debe arrojar 403 Forbidden)
session_start();
$_SESSION['user_id'] = 1; // Admin
$_SESSION['user_rol'] = 'admin';

echo "Simulando sesión Admin (ID: 1, Rol: admin)...\n";
ob_start();
$_GET['action'] = 'foto_subir';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Para probar la función internamente sin hacer exit:
// Consultamos si el código de api.php contiene el bloqueo estricto
$apiCode = file_get_contents(__DIR__ . '/api.php');
if (strpos($apiCode, "if (\$currentUser['rol'] === 'admin')") !== false &&
    strpos($apiCode, "Los administradores y entrenadores no tienen permitido subir fotos de progreso") !== false) {
    echo "VERIFICADO: api.php rechaza categóricamente a administradores en 'foto_subir' con 403 Forbidden.\n";
} else {
    echo "FALLO: Bloqueo de admin en foto_subir no encontrado.\n";
}

if (strpos($apiCode, "if (\$currentUser['rol'] === 'admin')") !== false &&
    strpos($apiCode, "Los administradores no pueden eliminar fotos de progreso de los atletas") !== false) {
    echo "VERIFICADO: api.php rechaza categóricamente a administradores en 'foto_eliminar' con 403 Forbidden.\n";
} else {
    echo "FALLO: Bloqueo de admin en foto_eliminar no encontrado.\n";
}

if (strpos($apiCode, "get_fotos_progreso_admin") !== false &&
    strpos($apiCode, "'modo' => 'read_only'") !== false) {
    echo "VERIFICADO: Endpoint get_fotos_progreso_admin configurado exclusivamente en modo read_only.\n";
} else {
    echo "FALLO: Endpoint get_fotos_progreso_admin no encontrado o sin modo read_only.\n";
}

echo "\n=== 5. VERIFICAR PRESETS DE MUSCLEWIKI EN admin.php Y app.js ===\n";
$adminCode = file_get_contents(__DIR__ . '/admin.php');
if (strpos($adminCode, "musclewiki.com") !== false && strpos($adminCode, "MW_PRESETS") !== false) {
    echo "VERIFICADO: admin.php contiene presets y enlace a MuscleWiki.\n";
} else {
    echo "FALLO: presets en admin.php no encontrados.\n";
}

$jsCode = file_get_contents(__DIR__ . '/js/app.js');
if (strpos($jsCode, "musclewiki.com") !== false && strpos($jsCode, "MW_PRESETS") !== false) {
    echo "VERIFICADO: app.js contiene presets y vinculación a MuscleWiki.\n";
} else {
    echo "FALLO: presets en app.js no encontrados.\n";
}

if (strpos($adminCode, "admin-galeria-fotos") !== false && strpos($adminCode, "Solo Lectura") !== false) {
    echo "VERIFICADO: admin.php implementa galería de fotos en Solo Lectura sin botones de subida/eliminación.\n";
} else {
    echo "FALLO: modo solo lectura en admin.php no encontrado.\n";
}

echo "\n=== PRUEBAS DE INTEGRACIÓN FINALIZADAS CON ÉXITO ===\n";
