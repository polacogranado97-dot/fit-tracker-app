<?php
/**
 * ==============================================================================
 * CONEXIÓN A BASE DE DATOS Y UTILIDADES (db.php)
 * Fit Tracker App - Web App / PWA de Entrenamiento Personalizado
 * ==============================================================================
 */

// Parámetros de conexión a MariaDB / MySQL (predeterminados para entorno XAMPP)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fit_tracker_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtiene la instancia singleton de la conexión PDO.
 * Si la base de datos o las tablas no existen, intenta inicializarlas automáticamente
 * a partir de schema.sql.
 * 
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Si el error es 1049 (Unknown database), intentamos crearla y ejecutar schema.sql
        if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $serverDsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
                $serverPdo = new PDO($serverDsn, DB_USER, DB_PASS, $options);
                $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                // Conectar a la nueva BD
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

                // Importar schema_v2.sql si existe (o schema.sql)
                $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'schema_v2.sql';
                if (!file_exists($schemaFile)) {
                    $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'schema.sql';
                }
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            } catch (Exception $initException) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Error al auto-inicializar la base de datos: ' . $initException->getMessage()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Error de conexión a la base de datos: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    return $pdo;
}

/**
 * Función auxiliar para emitir respuestas JSON estandarizadas
 * 
 * @param bool $success
 * @param mixed $data
 * @param string|null $message
 * @param int $statusCode
 */
function sendJsonResponse(bool $success, $data = null, ?string $message = null, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }

    $response = ['success' => $success];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Lee y decodifica la carga útil JSON enviada en el cuerpo de la solicitud POST/PUT
 * 
 * @return array
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
