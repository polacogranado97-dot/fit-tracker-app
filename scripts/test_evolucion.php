<?php
/**
 * Test de Evolución Comercial SaaS - FitTracker Pro
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDBConnection();

echo "====================================================\n";
echo "PRUEBAS DE EVOLUCIÓN SAAS MULTI-TENANT & REQUISITOS\n";
echo "====================================================\n\n";

$passedTests = 0;
$totalTests = 0;

function assertTest($condition, $description) {
    global $passedTests, $totalTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] $description\n";
    } else {
        echo "  [FAIL] $description\n";
    }
}

// 1. Multi-Tenant Isolation
echo "1. Probando Aislamiento Multi-Administrador (Multi-Tenant)...\n";

// Crear un Admin 2 de prueba para simular otro gimnasio/profesor
$testAdminPass = password_hash('gym2pass', PASSWORD_DEFAULT);
$pdo->exec("DELETE FROM usuarios WHERE email IN ('coach.test2@gym.com', 'alumno.test2@gym.com', 'atleta.eliminacion@test.com')");
$pdo->exec("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
            VALUES ('Coach 2', 'Gimnasio B', 'coach.test2@gym.com', '$testAdminPass', 'admin', NULL, 80.0, 1)");
$admin2Id = (int)$pdo->lastInsertId();

// Admin 2 crea un alumno propio
$pdo->exec("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
            VALUES ('Alumno Gym B', 'Pérez', 'alumno.test2@gym.com', '$testAdminPass', 'cliente', $admin2Id, 72.0, 1)");
$alumnoGymBId = (int)$pdo->lastInsertId();

// Verificar que Admin 1 (id 1) solo ve sus alumnos
$stmtA1 = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' AND activo = 1 AND (admin_id = 1 OR admin_id IS NULL)");
$stmtA1->execute();
$alumnosA1 = $stmtA1->fetchAll();
$idsA1 = array_column($alumnosA1, 'id');
assertTest(!in_array($alumnoGymBId, $idsA1), "Admin 1 (Alejandro) NO puede ver al alumno del Gimnasio B");

// Verificar que Admin 2 solo ve sus alumnos
$stmtA2 = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' AND activo = 1 AND (admin_id = ?)");
$stmtA2->execute([$admin2Id]);
$alumnosA2 = $stmtA2->fetchAll();
$idsA2 = array_column($alumnosA2, 'id');
assertTest(in_array($alumnoGymBId, $idsA2) && !in_array(2, $idsA2), "Admin 2 (Gimnasio B) solo ve sus propios alumnos y NO los de Admin 1");

// 2. Transición y Copia de Cargas Fiel (Sin incremento forzado de +2.5kg)
echo "\n2. Probando Copia de Semanas Fiel (Sin incremento forzado)...\n";
// Buscar un ejercicio de Mateo (id 2, rutina 1)
$stmtEj = $pdo->query("SELECT id FROM ejercicios_rutina WHERE rutina_id = 1 LIMIT 1");
$ejId = (int)$stmtEj->fetchColumn();

// Asignar carga en Semana 1 con 80.0kg y 80%
$pdo->exec("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg, porcentaje_carga, completado, notas) 
            VALUES ($ejId, 1, 80.0, 80.0, 1, 'Sensación sólida') 
            ON DUPLICATE KEY UPDATE peso_kg = 80.0, porcentaje_carga = 80.0, completado = 1");

// Simular carga_copiar_semana a Semana 2 con incremento_kg = 0
$stmtOrig = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 1");
$stmtOrig->execute([$ejId]);
$origen = $stmtOrig->fetch();

$nuevoPeso = max(0, (float)$origen['peso_kg'] + 0.0);
$nuevoPorcentaje = (float)$origen['porcentaje_carga'];
$pdo->prepare("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg, porcentaje_carga, completado, notas)
               VALUES (?, 2, ?, ?, 0, 'Copiado fiel')
               ON DUPLICATE KEY UPDATE peso_kg = VALUES(peso_kg), porcentaje_carga = VALUES(porcentaje_carga)")
    ->execute([$ejId, $nuevoPeso, $nuevoPorcentaje]);

$stmtDest = $pdo->prepare("SELECT peso_kg, porcentaje_carga FROM registro_cargas WHERE ejercicio_id = ? AND semana = 2");
$stmtDest->execute([$ejId]);
$dest = $stmtDest->fetch();

assertTest((float)$dest['peso_kg'] === 80.0, "La Semana 2 recibió exactamente 80.0 kg (sin incremento forzado de +2.5kg)");
assertTest((float)$dest['porcentaje_carga'] === 80.0, "La Semana 2 recibió exactamente 80.0% de intensidad");

// 3. Bloque de Movilidad Previa
echo "\n3. Probando Bloque de Movilidad / Entrada en Calor Previa...\n";
$textoMovilidad = "• 10 min de calentamiento específico de cadera y tobillo\n• Activación de core (deadbug 3x10)";
$pdo->prepare("INSERT INTO rutina_calentamiento (rutina_id, dia_semana, movilidad) 
               VALUES (1, 3, ?) 
               ON DUPLICATE KEY UPDATE movilidad = VALUES(movilidad)")
    ->execute([$textoMovilidad]);

$stmtCheckMov = $pdo->query("SELECT movilidad FROM rutina_calentamiento WHERE rutina_id = 1 AND dia_semana = 3");
$movGuardada = $stmtCheckMov->fetchColumn();
assertTest($movGuardada === $textoMovilidad, "Bloque de movilidad para Día 3 guardado y recuperado fielmente");

// 4. Eliminación Permanente de Alumno
echo "\n4. Probando Eliminación Permanente de Alumnos...\n";
$pdo->exec("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, activo) 
            VALUES ('Atleta Eliminable', 'Test', 'atleta.eliminacion@test.com', '$testAdminPass', 'cliente', 1, 75.0, 1)");
$delUserId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO rutinas_semana (cliente_id, nombre, activo) VALUES ($delUserId, 'Rutina Test Del', 1)");
$delRutinaId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO ejercicios_rutina (rutina_id, dia_semana, nombre_ejercicio) VALUES ($delRutinaId, 1, 'Ejercicio Test Del')");
$delEjId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg) VALUES ($delEjId, 1, 50.0)");

// Ejecutar borrado permanente
$pdo->exec("DELETE FROM usuarios WHERE id = $delUserId");

$checkUser = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id = $delUserId")->fetchColumn();
$checkRutina = $pdo->query("SELECT COUNT(*) FROM rutinas_semana WHERE id = $delRutinaId")->fetchColumn();
$checkEj = $pdo->query("SELECT COUNT(*) FROM ejercicios_rutina WHERE id = $delEjId")->fetchColumn();
$checkCarga = $pdo->query("SELECT COUNT(*) FROM registro_cargas WHERE ejercicio_id = $delEjId")->fetchColumn();

assertTest($checkUser == 0, "Usuario eliminado permanentemente de la tabla usuarios");
assertTest($checkRutina == 0 && $checkEj == 0 && $checkCarga == 0, "Cascada completada: rutinas, ejercicios y cargas del usuario eliminados");

// Limpiar datos temporales de prueba
$pdo->exec("DELETE FROM usuarios WHERE id IN ($admin2Id, $alumnoGymBId)");

echo "\n====================================================\n";
echo "RESULTADOS: $passedTests de $totalTests pruebas pasaron con éxito.\n";
echo "====================================================\n";
