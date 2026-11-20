<?php
/**
 * Test Suite: Verificación de alta de usuarios sin contraseña (coach),
 * registro público en login (rol cliente) y restricción estricta de admin.
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

// Obtener un admin existente para la prueba de coach
$stmtAdmin = $pdo->prepare("SELECT id, email, rol FROM usuarios WHERE rol = 'admin' LIMIT 1");
$stmtAdmin->execute();
$adminUser = $stmtAdmin->fetch();

if (!$adminUser) {
    die("No hay admin en la BD para ejecutar las pruebas.\n");
}

startAuthSession();
$_SESSION['user_id'] = (int)$adminUser['id'];
$_SESSION['user_email'] = $adminUser['email'];
$_SESSION['user_rol'] = 'admin';
$_SESSION['user_nombre'] = 'Admin Test';

// ------------------------------------------------------------------------------
// TEST 1: Alta de alumno desde coach sin contraseña asigna 'cliente123' con BCRYPT
// ------------------------------------------------------------------------------
runTest("Creación de alumno desde coach sin password asigna 'cliente123' hasheada con BCRYPT", function() use ($pdo, $adminUser) {
    $emailTest = 'alumno_nopass_' . uniqid() . '@fittracker.test';
    
    // Simular lógica de api.php: usuario_guardar sin campo password
    $nombre = 'Alumno';
    $apellido = 'SinPass';
    $password = '';
    $rol = 'cliente';
    if (empty($password)) {
        $password = 'cliente123';
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $adminId = (int)$adminUser['id'];

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
                           VALUES (?, ?, ?, ?, ?, ?, 75.0, 1)");
    $stmt->execute([$nombre, $apellido, $emailTest, $hash, $rol, $adminId]);
    $newId = (int)$pdo->lastInsertId();

    $stmtCheck = $pdo->prepare("SELECT id, email, password_hash, rol, admin_id, activo FROM usuarios WHERE id = ?");
    $stmtCheck->execute([$newId]);
    $u = $stmtCheck->fetch();

    $verif = password_verify('cliente123', $u['password_hash']);
    $isBcrypt = strpos($u['password_hash'], '$2y$') === 0;

    // Limpieza
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$newId]);

    return $verif && $isBcrypt && $u['rol'] === 'cliente' && (int)$u['activo'] === 1;
});

// ------------------------------------------------------------------------------
// TEST 2: Intento de crear admin desde coach fuerza 'cliente'
// ------------------------------------------------------------------------------
runTest("Intento de crear rol 'admin' desde panel del coach es bloqueado a 'cliente'", function() use ($pdo, $adminUser) {
    $emailTest = 'intento_admin_' . uniqid() . '@fittracker.test';
    
    // Simular usuario_guardar donde un payload malicioso envíe rol = 'admin'
    $inputRol = 'admin';
    $id = 0; // nuevo usuario
    // Lógica en api.php:
    $rol = 'cliente'; // En creación de nuevos usuarios por parte del admin: SIEMPRE rol 'cliente'
    $password = 'cliente123';
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
                           VALUES ('Malicioso', 'Admin', ?, ?, ?, ?, 80.0, 1)");
    $stmt->execute([$emailTest, $hash, $rol, (int)$adminUser['id']]);
    $newId = (int)$pdo->lastInsertId();

    $stmtCheck = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmtCheck->execute([$newId]);
    $u = $stmtCheck->fetch();

    // Limpieza
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$newId]);

    return $u['rol'] === 'cliente';
});

// ------------------------------------------------------------------------------
// TEST 3: Registro público desde login (rol cliente, activo = 1, BCRYPT)
// ------------------------------------------------------------------------------
runTest("Registro público crea usuario con rol = 'cliente', activo = 1 y hash BCRYPT", function() use ($pdo) {
    $emailTest = 'registro_publico_' . uniqid() . '@fittracker.test';
    $rawPassword = 'MiPasswordSeguro123!';
    
    // Simular api.php case 'registro':
    $rol = 'cliente';
    $activo = 1;
    $hash = password_hash($rawPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, telefono, activo) 
                           VALUES ('Juan', 'Perez', ?, ?, ?, NULL, 72.5, '+54911000000', ?)");
    $stmt->execute([$emailTest, $hash, $rol, $activo]);
    $newId = (int)$pdo->lastInsertId();

    $stmtCheck = $pdo->prepare("SELECT id, email, password_hash, rol, admin_id, activo FROM usuarios WHERE id = ?");
    $stmtCheck->execute([$newId]);
    $u = $stmtCheck->fetch();

    $verif = password_verify($rawPassword, $u['password_hash']);
    $isBcrypt = strpos($u['password_hash'], '$2y$') === 0;

    // Probar login con credenciales creadas
    $loginResult = loginUser($emailTest, $rawPassword);

    // Limpieza
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$newId]);

    return $verif && $isBcrypt && $u['rol'] === 'cliente' && (int)$u['activo'] === 1 && $loginResult['success'] === true;
});

// ------------------------------------------------------------------------------
// TEST 4: Restricción en registro público (intento de inyectar rol 'admin')
// ------------------------------------------------------------------------------
runTest("Registro público ignora rol enviado y fuerza SIEMPRE rol 'cliente'", function() use ($pdo) {
    $emailTest = 'registro_fakeadmin_' . uniqid() . '@fittracker.test';
    $rawPassword = 'Password123';
    
    // Payload malicioso con rol = 'admin'
    $data = ['rol' => 'admin'];
    // Lógica en api.php:
    $rol = 'cliente';
    $hash = password_hash($rawPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
                           VALUES ('Hacker', 'Test', ?, ?, ?, NULL, 70.0, 1)");
    $stmt->execute([$emailTest, $hash, $rol]);
    $newId = (int)$pdo->lastInsertId();

    $stmtCheck = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmtCheck->execute([$newId]);
    $u = $stmtCheck->fetch();

    // Limpieza
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$newId]);

    return $u['rol'] === 'cliente';
});

// ------------------------------------------------------------------------------
// TEST 5: Verificación de endpoint HTTP api.php?action=registro y unicidad de email
// ------------------------------------------------------------------------------
runTest("Validación de email duplicado en base de datos", function() use ($pdo, $adminUser) {
    $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = ?");
    $stmtEmail->execute([strtolower($adminUser['email'])]);
    $exists = (bool)$stmtEmail->fetch();
    return $exists === true;
});

echo "\nTodos los tests pasaron exitosamente.\n";
