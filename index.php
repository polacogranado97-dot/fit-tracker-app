<?php
require_once __DIR__ . '/auth.php';
startAuthSession();

// Si ya hay sesión iniciada como admin, dirigir al panel del entrenador
if (isLoggedIn() && isAdmin()) {
    header('Location: admin.php');
    exit;
}

// Para clientes o invitados, cargar la SPA principal
header('Location: index.html');
exit;
