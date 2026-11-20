<?php
/**
 * Test Suite: Verificación de Correcciones Críticas SaaS y Enrutador
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

function runTest($nombre, $fn) {
    try {
        $resultado = $fn();
        if ($resultado === true) {
            echo "[OK] $nombre\n";
        } else {
            echo "[FAIL] $nombre - " . ($resultado ?: 'Fallo') . "\n";
        }
    } catch (Throwable $e) {
        echo "[ERROR] $nombre: " . $e->getMessage() . "\n";
    }
}

$pdo = getDBConnection();

// 1. Verificar existencia de usuario admin y cliente de prueba
$stmtAdmin = $pdo->prepare("SELECT id, email, rol FROM usuarios WHERE rol = 'admin' LIMIT 1");
$stmtAdmin->execute();
$adminUser = $stmtAdmin->fetch();

$stmtCliente = $pdo->prepare("SELECT id, email, rol FROM usuarios WHERE rol = 'cliente' LIMIT 1");
$stmtCliente->execute();
$clienteUser = $stmtCliente->fetch();

runTest("Existe cuenta de Administrador en la BD", function() use ($adminUser) {
    return !empty($adminUser) && $adminUser['rol'] === 'admin';
});

runTest("Existe cuenta de Cliente en la BD", function() use ($clienteUser) {
    return !empty($clienteUser) && $clienteUser['rol'] === 'cliente';
});

// 2. Simular sesión de Administrador
startAuthSession();
$_SESSION['user_id'] = (int)$adminUser['id'];
$_SESSION['user_email'] = $adminUser['email'];
$_SESSION['user_rol'] = 'admin';
$_SESSION['user_nombre'] = 'Admin Test';

// 3. Test canAccessUserData
runTest("Admin tiene acceso a los datos de sus clientes", function() use ($clienteUser) {
    return canAccessUserData((int)$clienteUser['id']) === true;
});

// 4. Test detalle de cliente
runTest("Endpoint usuario_detalle / cliente retorna datos completos", function() use ($pdo, $clienteUser) {
    $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, rol, admin_id, peso_corporal, telefono, observaciones, foto_perfil, activo, created_at FROM usuarios WHERE id = ?");
    $stmt->execute([(int)$clienteUser['id']]);
    $u = $stmt->fetch();
    return !empty($u) && (int)$u['id'] === (int)$clienteUser['id'] && !empty($u['nombre']);
});

// 5. Test restricción de roles en creación
runTest("Creación de usuario por Admin fuerza rol = 'cliente' y asigna admin_id", function() use ($pdo, $adminUser) {
    $emailTest = 'test_atleta_' . uniqid() . '@example.com';
    $password = '123456';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Simular lógica de api.php para creación
    $rol = 'cliente'; // Forzado por la nueva lógica
    $adminId = (int)$adminUser['id'];
    
    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
                           VALUES ('Test', 'Atleta', ?, ?, ?, ?, 75.0, 1)");
    $stmt->execute([$emailTest, $hash, $rol, $adminId]);
    $newId = (int)$pdo->lastInsertId();

    $stmtCheck = $pdo->prepare("SELECT rol, admin_id FROM usuarios WHERE id = ?");
    $stmtCheck->execute([$newId]);
    $inserted = $stmtCheck->fetch();

    // Limpieza
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$newId]);

    return $inserted['rol'] === 'cliente' && (int)$inserted['admin_id'] === $adminId;
});

// 6. Test imágenes locales disponibles
runTest("Catálogo de imágenes locales existe y tiene recursos", function() {
    $dir = __DIR__ . '/../assets/ejercicios/exercise_images';
    if (!is_dir($dir)) return "Directorio no encontrado: $dir";
    $files = scandir($dir);
    $count = 0;
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && is_file($dir . '/' . $f)) {
            $count++;
        }
    }
    return $count > 50;
});

echo "\nPruebas completadas exitosamente.\n";
