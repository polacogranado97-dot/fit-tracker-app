<?php
/**
 * ==============================================================================
 * SISTEMA DE AUTENTICACIÓN Y ROLES (auth.php)
 * Manejo de Sesiones Seguras, Login, Logout y Control de Acceso (Admin vs Cliente)
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

/**
 * Inicia la sesión PHP con parámetros de seguridad recomendados
 */
function startAuthSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 60 * 60 * 24 * 7; // 7 días de persistencia
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
        ]);
        session_start();
    }
}

/**
 * Autentica un usuario verificando su correo y contraseña (bcrypt)
 * 
 * @param string $email
 * @param string $password
 * @return array{success: bool, user?: array, message?: string}
 */
function loginUser(string $email, string $password): array {
    startAuthSession();
    $email = trim(strtolower($email));
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Credenciales inválidas o usuario inactivo.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Contraseña incorrecta.'];
    }

    // Regenerar ID de sesión para prevenir Session Fixation
    session_regenerate_id(true);

    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_rol']    = $user['rol'];
    $_SESSION['user_nombre'] = $user['nombre'] . ' ' . $user['apellido'];

    // Ocultar hash de contraseña antes de retornar
    unset($user['password_hash']);

    return [
        'success' => true,
        'user'    => $user,
        'message' => 'Inicio de sesión exitoso.'
    ];
}

/**
 * Cierra la sesión activa del usuario
 */
function logoutUser(): void {
    startAuthSession();
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Retorna true si hay un usuario autenticado
 */
function isLoggedIn(): bool {
    startAuthSession();
    return !empty($_SESSION['user_id']);
}

/**
 * Retorna true si el usuario autenticado tiene rol de Administrador
 */
function isAdmin(): bool {
    startAuthSession();
    return isLoggedIn() && (($_SESSION['user_rol'] ?? '') === 'admin');
}

/**
 * Retorna la información completa del usuario actualmente autenticado
 */
function getCurrentUser(): ?array {
    startAuthSession();
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, rol, admin_id, peso_corporal, telefono, observaciones, foto_perfil, activo, created_at FROM usuarios WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['activo'] !== 1) {
        logoutUser();
        return null;
    }

    return $user;
}

/**
 * Exige que exista un usuario autenticado para continuar.
 * Si es llamada desde una API, emite 401 en JSON. Si es página web, redirige al login.
 */
function requireAuth(bool $isApi = true): array {
    $user = getCurrentUser();
    if (!$user) {
        if ($isApi) {
            sendJsonResponse(false, null, 'No autorizado. Se requiere iniciar sesión.', 401);
        } else {
            header('Location: login.php');
            exit;
        }
    }
    return $user;
}

/**
 * Exige que el usuario autenticado sea Administrador.
 */
function requireAdmin(bool $isApi = true): array {
    $user = requireAuth($isApi);
    if ($user['rol'] !== 'admin') {
        if ($isApi) {
            sendJsonResponse(false, null, 'Acceso denegado. Se requieren permisos de Administrador / Entrenador.', 403);
        } else {
            header('Location: index.php?error=forbidden');
            exit;
        }
    }
    return $user;
}

/**
 * Verifica si el usuario actual tiene permiso para leer o modificar datos del usuario objetivo.
 * El Admin puede acceder a cualquiera; el Cliente solo puede acceder a sí mismo.
 */
function canAccessUserData(int $targetUserId): bool {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    // El propio usuario siempre tiene acceso a sus datos
    if ((int)$currentUser['id'] === $targetUserId) {
        return true;
    }
    // Aislamiento Multi-Administrador: Un Admin solo puede acceder a atletas vinculados a su propia cuenta
    if ($currentUser['rol'] === 'admin') {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND (admin_id = ? OR id = ?)");
        $stmt->execute([$targetUserId, (int)$currentUser['id'], (int)$currentUser['id']]);
        return (bool)$stmt->fetch();
    }
    return false;
}
