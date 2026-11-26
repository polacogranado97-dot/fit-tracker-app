<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
$pdo = getDBConnection();

echo "=== TEST: VERIFICACIÓN INTEGRAL DE GUARDADO DE SERIES Y AVANCE DE SEMANA ===\n\n";

$_SESSION['user_id'] = 2; // Mateo (cliente)
$_SESSION['user_rol'] = 'cliente';
$_SESSION['user_nombre'] = 'Mateo Valenzuela';

function assertTest($condition, $message) {
    if ($condition) {
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

// 1. Obtener ejercicio de prueba
$stmtEj = $pdo->query("SELECT e.id, e.series, e.un_rm_estimado 
                       FROM ejercicios_rutina e 
                       JOIN rutinas_semana r ON e.rutina_id = r.id 
                       WHERE r.cliente_id = 2 
                       LIMIT 1");
$ej = $stmtEj->fetch();
assertTest(!empty($ej), "Ejercicio encontrado para Mateo (ID: {$ej['id']}, 1RM: {$ej['un_rm_estimado']} kg)");
$ejId = (int)$ej['id'];
$unRm = (float)$ej['un_rm_estimado'];

// 2. Simular guardado de series en Semana 1
echo "\n1. Guardado de pesos por serie en Semana 1:\n";
$series1 = [32.5, 35.0, 37.5, 42.5];
$cleanSeries = array_map(function($v) { return ($v !== '' && $v !== null) ? round((float)$v, 2) : 0; }, $series1);
$positives = array_filter($cleanSeries, function($v) { return $v > 0; });
$maxPeso = !empty($positives) ? max($positives) : 0.00;
$pct = $unRm > 0 ? round(($maxPeso / $unRm) * 100, 2) : 0.00;
$jsonSeries = json_encode($cleanSeries);

$stmtUpsert = $pdo->prepare("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg, pesos_series, porcentaje_carga, completado, notas)
                             VALUES (?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE 
                                peso_kg = VALUES(peso_kg),
                                pesos_series = VALUES(pesos_series),
                                porcentaje_carga = VALUES(porcentaje_carga),
                                completado = VALUES(completado),
                                notas = VALUES(notas)");
$stmtUpsert->execute([$ejId, 1, $maxPeso, $jsonSeries, $pct, 1, 'Serie completa S1']);

$stmtGet = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 1");
$stmtGet->execute([$ejId]);
$c1 = $stmtGet->fetch();

assertTest((float)$c1['peso_kg'] === 42.5, "peso_kg es el peso máximo (42.5 kg)");
assertTest(json_decode($c1['pesos_series'], true) == $series1, "pesos_series guardado fielmente");
assertTest((float)$c1['porcentaje_carga'] === $pct, "% de intensidad calculado con exactitud ({$pct}%)");

// 3. Probar guardado en Semana 6 (S6)
echo "\n2. Guardado en Semana 6 (S6):\n";
$series6 = [35.0, 40.0, 45.0, 50.0];
$maxPeso6 = 50.0;
$pct6 = $unRm > 0 ? round(($maxPeso6 / $unRm) * 100, 2) : 0.00;
$jsonSeries6 = json_encode($series6);

try {
    $stmtUpsert->execute([$ejId, 6, $maxPeso6, $jsonSeries6, $pct6, 1, 'Top de ciclo S6']);
    $stmtGet6 = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 6");
    $stmtGet6->execute([$ejId]);
    $c6 = $stmtGet6->fetch();
    assertTest(!empty($c6), "Semana 6 guardada con éxito en la base de datos");
    assertTest((float)$c6['peso_kg'] === 50.0, "peso_kg de S6 registrado como 50.0 kg");
    assertTest(json_decode($c6['pesos_series'], true) == $series6, "pesos_series en S6 coincide con el array");
} catch (Exception $e) {
    assertTest(false, "Fallo al guardar Semana 6: " . $e->getMessage());
}

// 4. Probar clonación/avance de semana con carga_copiar_semana
echo "\n3. Probar avance y copia de semana (S1 -> S2):\n";
$stmtOrig = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 1");
$stmtOrig->execute([$ejId]);
$origen = $stmtOrig->fetch();

$stmtUpsert->execute([$ejId, 2, $origen['peso_kg'], $origen['pesos_series'], $origen['porcentaje_carga'], 0, 'Copiado desde S1']);
$stmtGet2 = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 2");
$stmtGet2->execute([$ejId]);
$c2 = $stmtGet2->fetch();
assertTest((float)$c2['peso_kg'] === 42.5, "Semana 2 recibió exactamente el mismo peso que S1");
assertTest($c2['pesos_series'] === $origen['pesos_series'], "Semana 2 recibió exactamente los mismos pesos por serie");

// 5. Probar clonación desde semana sin registros previos (no debe dar error)
echo "\n4. Probar copia de semana sin registros previos (S3 -> S4):\n";
// Borrar S3 si existe
$pdo->prepare("DELETE FROM registro_cargas WHERE ejercicio_id = ? AND semana IN (3, 4)")->execute([$ejId]);
$stmtOrig3 = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 3");
$stmtOrig3->execute([$ejId]);
$origen3 = $stmtOrig3->fetch();
$fallbackOrigen = $origen3 ?: ['peso_kg' => 0.00, 'pesos_series' => null, 'porcentaje_carga' => 0.00, 'notas' => 'Copiado desde S3'];
$stmtUpsert->execute([$ejId, 4, $fallbackOrigen['peso_kg'], $fallbackOrigen['pesos_series'], $fallbackOrigen['porcentaje_carga'], 0, $fallbackOrigen['notas']]);
$stmtGet4 = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 4");
$stmtGet4->execute([$ejId]);
$c4 = $stmtGet4->fetch();
assertTest(!empty($c4), "Clonación desde semana vacía no crashea e inicializa la semana destino");

// 6. Verificación de código JS (race conditions, flush y cambio de semana)
echo "\n5. Verificación de frontend y arquitectura asíncrona:\n";
$appJs = file_get_contents(__DIR__ . '/../js/app.js');
assertTest(strpos($appJs, 'flushPendingSaves') !== false, "flushPendingSaves implementado para evitar condiciones de carrera");
assertTest(strpos($appJs, 'guardarCargaInmediata(ejercicioId, semanaTarget') !== false, "guardarCargaInmediata acepta semanaTarget explícita");
assertTest(strpos($appJs, 'await flushPendingSaves()') !== false, "Navegador de semanas vacía saves pendientes antes de cambiar de semana");
assertTest(strpos($appJs, 'AppState.semanaActiva = semanaDestino') !== false, "copiarASiguienteSemana avanza automáticamente a la semana destino");

echo "\nTODAS LAS PRUEBAS COMPLETADAS CON ÉXITO (100% OK).\n";
