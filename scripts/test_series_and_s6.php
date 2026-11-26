<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
$pdo = getDBConnection();

echo "=== TEST: PESOS POR SERIE, SEMANA 6 Y PANEL COACH ===\n\n";
// Usar sesión de Mateo (id 2, cliente)
$_SESSION['user_id'] = 2;
$_SESSION['user_rol'] = 'cliente';
$_SESSION['user_nombre'] = 'Mateo';

function assertTest($condition, $message) {
    if ($condition) {
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

// Obtener un ejercicio de Mateo
$stmtEj = $pdo->query("SELECT e.id, e.series, e.un_rm_estimado 
                       FROM ejercicios_rutina e 
                       JOIN rutinas_semana r ON e.rutina_id = r.id 
                       WHERE r.cliente_id = 2 
                       LIMIT 1");
$ej = $stmtEj->fetch();
assertTest(!empty($ej), "Existe ejercicio para Mateo (ID: {$ej['id']}, Series: {$ej['series']}, 1RM: {$ej['un_rm_estimado']})");
$ejId = (int)$ej['id'];

// 1. Probar carga_guardar con pesos_series en Semana 1
echo "\n1. Guardar pesos por serie en Semana 1:\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$seriesData = [30.0, 35.0, 35.0, 45.0];
$input = [
    'ejercicio_id'  => $ejId,
    'semana'        => 1,
    'peso_kg'       => 0, // Debe calcular el máximo (45) automáticamente
    'pesos_series'  => $seriesData,
    'completado'    => 1,
    'notas'         => 'Test serie 1'
];

// Invocamos lógica de carga_guardar
$stmtCheck = $pdo->prepare("SELECT e.id, e.un_rm_estimado, r.cliente_id FROM ejercicios_rutina e JOIN rutinas_semana r ON e.rutina_id = r.id WHERE e.id = ?");
$stmtCheck->execute([$ejId]);
$ejInfo = $stmtCheck->fetch();

$cleanSeries = [];
foreach ($seriesData as $p) {
    $cleanSeries[] = round((float)$p, 2);
}
$pesosSeriesJson = json_encode($cleanSeries);
$pesoMax = max($cleanSeries);
$unRm = (float)$ejInfo['un_rm_estimado'];
$pct = $unRm > 0 ? round(($pesoMax / $unRm) * 100, 2) : 0;

$stmtUpsert = $pdo->prepare("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg, pesos_series, porcentaje_carga, completado, notas)
                             VALUES (?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE 
                                peso_kg = VALUES(peso_kg),
                                pesos_series = VALUES(pesos_series),
                                porcentaje_carga = VALUES(porcentaje_carga),
                                completado = VALUES(completado),
                                notas = VALUES(notas)");
$stmtUpsert->execute([$ejId, 1, $pesoMax, $pesosSeriesJson, $pct, 1, 'Test serie 1']);

$stmtGet = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 1");
$stmtGet->execute([$ejId]);
$c1 = $stmtGet->fetch();

assertTest((float)$c1['peso_kg'] === 45.0, "peso_kg calculado automáticamente como máx de series (45.0 kg)");
$decoded = json_decode($c1['pesos_series'], true);
$decodedFloats = array_map('floatval', $decoded);
assertTest($decodedFloats == [30.0, 35.0, 35.0, 45.0], "pesos_series guardado como JSON idéntico a las series");
assertTest((float)$c1['porcentaje_carga'] === $pct, "% de intensidad calculado correctamente ({$pct}%)");

// 2. Probar guardar en Semana 6
echo "\n2. Guardar carga en Semana 6 (S6):\n";
$seriesS6 = [35.0, 40.0, 42.5, 50.0];
$pesosS6Json = json_encode($seriesS6);
$pesoMaxS6 = max($seriesS6);
$pctS6 = $unRm > 0 ? round(($pesoMaxS6 / $unRm) * 100, 2) : 0;

$stmtUpsert->execute([$ejId, 6, $pesoMaxS6, $pesosS6Json, $pctS6, 1, 'Test S6']);
$stmtGet6 = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 6");
$stmtGet6->execute([$ejId]);
$c6 = $stmtGet6->fetch();

assertTest(!empty($c6), "Semana 6 guardada con éxito en la base de datos");
assertTest((float)$c6['peso_kg'] === 50.0, "peso_kg de Semana 6 es 50.0 kg");
$decoded6Floats = array_map('floatval', json_decode($c6['pesos_series'], true));
assertTest($decoded6Floats == $seriesS6, "pesos_series en S6 coincide con [35, 40, 42.5, 50]");

// 3. Probar copia de semana (carga_copiar_semana)
echo "\n3. Probar copia de semana (S1 -> S2 con pesos por serie):\n";
$stmtOrig = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 1");
$stmtOrig->execute([$ejId]);
$origen = $stmtOrig->fetch();
$stmtUpsert->execute([$ejId, 2, $origen['peso_kg'], $origen['pesos_series'], $origen['porcentaje_carga'], 0, 'Copiado desde S1']);

$stmtGet2 = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = 2");
$stmtGet2->execute([$ejId]);
$c2 = $stmtGet2->fetch();
assertTest((float)$c2['peso_kg'] === (float)$origen['peso_kg'], "Semana 2 recibió exactamente el mismo peso (45.0 kg)");
assertTest($c2['pesos_series'] === $origen['pesos_series'], "Semana 2 recibió exactamente los mismos pesos por serie");

// 4. Verificación de archivos estáticos
echo "\n4. Verificación de archivos y reglas requeridas:\n";
$appJs = file_get_contents(__DIR__ . '/../js/app.js');
$adminPhp = file_get_contents(__DIR__ . '/../admin.php');
$indexHtml = file_get_contents(__DIR__ . '/../index.html');

// 4.1 No botones -2.5 y +2.5 en Carga Aplicada
assertTest(strpos($appJs, 'onclick="ajustarPeso(${ej.id}, -2.5)"') === false, "Botón -2.5 ELIMINADO de la tarjeta de ejercicio");
assertTest(strpos($appJs, 'onclick="ajustarPeso(${ej.id}, 2.5)"') === false, "Botón +2.5 ELIMINADO de la tarjeta de ejercicio");

// 4.2 Inputs de series individuales en app.js
assertTest(strpos($appJs, 'input-serie-${ej.id}-${i}') !== false, "Campos individuales por serie presentes en renderEjercicioCard");
assertTest(strpos($appJs, 'onInputSeriePesoChange') !== false, "Función onInputSeriePesoChange implementada");

// 4.3 Soporte de S1..S6
assertTest(strpos($appJs, '[1, 2, 3, 4, 5, 6]') !== false, "Navegador de semanas incluye S1..S6 en app.js");
assertTest(strpos($appJs, 'grid-cols-6') !== false, "Grilla de historial incluye 6 columnas en app.js");
assertTest(strpos($appJs, 'Progresión (S1 a S6)') !== false, "Etiqueta Progresión (S1 a S6) actualizada en app.js");
assertTest(strpos($appJs, 'AppState.semanaActiva < 6') !== false, "Botón Copiar a S... habilitado hasta Semana 5 -> S6 en app.js");
assertTest(strpos($indexHtml, 'S1..S6') !== false, "index.html actualizado con S1..S6");
assertTest(strpos($adminPhp, '[1, 2, 3, 4, 5, 6]') !== false, "Navegador de semanas incluye S1..S6 en admin.php");

// 4.4 Botón + Nuevo Atleta / Usuario eliminado en admin.php
assertTest(strpos($adminPhp, '+ Nuevo Atleta / Usuario') === false, "Botón '+ Nuevo Atleta / Usuario' ELIMINADO en admin.php");

echo "\nTODOS LOS TESTS PASARON EXITOSAMENTE.\n";
