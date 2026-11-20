<?php
require 'db.php';
$pdo = getDBConnection();
$stmt = $pdo->query('SELECT id, nombre, apellido, email, rol, admin_id FROM usuarios');
while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($u) . PHP_EOL;
}
