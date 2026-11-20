<?php
/**
 * ==============================================================================
 * API RESTFUL JSON V2 (api.php)
 * FitTracker Pro: Autenticación, Roles (Admin/Cliente), Fotos de Progreso, 
 * Rutinas con Imágenes Demostrativas y Registro de Cargas S1..S5.
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Cabeceras CORS y Content-Type
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}

startAuthSession();
$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ======================================================================
        // 1. AUTENTICACIÓN Y SESIÓN (LOGIN, LOGOUT, ME)
        // ======================================================================

        case 'login':
            $data = getJsonInput();
            $email = trim($data['email'] ?? '');
            $password = trim($data['password'] ?? '');

            if (empty($email) || empty($password)) {
                sendJsonResponse(false, null, 'El correo electrónico y la contraseña son requeridos.', 400);
            }

            $resultado = loginUser($email, $password);
            if ($resultado['success']) {
                sendJsonResponse(true, $resultado['user'], $resultado['message']);
            } else {
                sendJsonResponse(false, null, $resultado['message'], 401);
            }
            break;

        case 'registro':
        case 'register':
            // Registro público de usuario desde pantalla de login (SOLO ROL 'cliente')
            $data = getJsonInput();
            $nombre = trim($data['nombre'] ?? '');
            $apellido = trim($data['apellido'] ?? '');
            $email = trim(strtolower($data['email'] ?? ''));
            $password = trim($data['password'] ?? '');
            $peso = isset($data['peso_corporal']) ? (float)$data['peso_corporal'] : 70.0;
            if ($peso <= 0) {
                $peso = 70.0;
            }
            $telefono = trim($data['telefono'] ?? '');

            if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
                sendJsonResponse(false, null, 'Nombre, apellido, correo electrónico y contraseña son obligatorios.', 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse(false, null, 'El formato de correo electrónico ingresado no es válido.', 400);
            }

            if (strlen($password) < 6) {
                sendJsonResponse(false, null, 'La contraseña debe contener al menos 6 caracteres.', 400);
            }

            // Validar que el email no esté repetido
            $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = ?");
            $stmtEmail->execute([$email]);
            if ($stmtEmail->fetch()) {
                sendJsonResponse(false, null, 'El correo electrónico ya se encuentra registrado por otro usuario.', 400);
            }

            // RESTRICCIÓN ESTRICTA DE ROLES:
            // Forzar SIEMPRE rol 'cliente' (nunca 'admin') y activo = 1
            $rol = 'cliente';
            $activo = 1;
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, telefono, activo) 
                                   VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $email, $hash, $rol, $peso, $telefono, $activo]);
            $newUserId = (int)$pdo->lastInsertId();

            // Inicializar automáticamente su primera rutina de 5 microciclos
            $stmtR = $pdo->prepare("INSERT INTO rutinas_semana (cliente_id, nombre, descripcion, fecha_inicio, activo) 
                                    VALUES (?, 'Mesociclo 1 (S1-S5)', 'Planificación inicial de 5 microciclos', CURDATE(), 1)");
            $stmtR->execute([$newUserId]);

            // Iniciar sesión inmediatamente para el usuario registrado
            $loginRes = loginUser($email, $password);
            if ($loginRes['success']) {
                sendJsonResponse(true, $loginRes['user'], 'Cuenta creada con éxito. ¡Bienvenido a FitTracker Pro!');
            } else {
                $stmtGet = $pdo->prepare("SELECT id, nombre, apellido, email, rol, peso_corporal, telefono, activo FROM usuarios WHERE id = ?");
                $stmtGet->execute([$newUserId]);
                sendJsonResponse(true, $stmtGet->fetch(), 'Cuenta de atleta registrada con éxito.');
            }
            break;

        case 'logout':
            logoutUser();
            sendJsonResponse(true, null, 'Sesión cerrada correctamente.');
            break;

        case 'me':
        case 'check_session':
            $currentUser = getCurrentUser();
            if ($currentUser) {
                sendJsonResponse(true, [
                    'authenticated' => true,
                    'user'          => $currentUser
                ]);
            } else {
                sendJsonResponse(true, [
                    'authenticated' => false,
                    'user'          => null
                ]);
            }
            break;


        // ======================================================================
        // 2. GESTIÓN DE USUARIOS Y CLIENTES (AISLAMIENTO MULTI-TENANT)
        // ======================================================================

        case 'clientes':
            // Si es Admin, retorna únicamente los clientes activos vinculados a su sesión (Multi-Tenant)
            // Si es Cliente, retorna únicamente su propio perfil.
            $currentUser = requireAuth();
            $queryBusqueda = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['buscar']) ? trim($_GET['buscar']) : '');
            if ($currentUser['rol'] === 'admin') {
                $sql = "SELECT id, nombre, apellido, email, telefono, peso_corporal, observaciones, foto_perfil, rol, admin_id, activo, created_at 
                        FROM usuarios 
                        WHERE rol = 'cliente' AND activo = 1 AND (admin_id = ? OR admin_id IS NULL)";
                $params = [(int)$currentUser['id']];
                if ($queryBusqueda !== '') {
                    $sql .= " AND (LOWER(nombre) LIKE ? OR LOWER(apellido) LIKE ? OR LOWER(CONCAT(nombre, ' ', apellido)) LIKE ? OR LOWER(email) LIKE ?)";
                    $term = '%' . mb_strtolower($queryBusqueda, 'UTF-8') . '%';
                    $params[] = $term;
                    $params[] = $term;
                    $params[] = $term;
                    $params[] = $term;
                }
                $sql .= " ORDER BY nombre ASC, apellido ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $clientes = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, telefono, peso_corporal, observaciones, foto_perfil, rol, admin_id, activo, created_at 
                                       FROM usuarios 
                                       WHERE id = ?");
                $stmt->execute([(int)$currentUser['id']]);
                $clientes = $stmt->fetchAll();
            }
            sendJsonResponse(true, $clientes);
            break;

        case 'usuarios':
            // Gestión de usuarios: Solo para Administradores, aislado a su propio tenant
            $currentUser = requireAdmin();
            $adminId = (int)$currentUser['id'];
            $queryBusqueda = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['buscar']) ? trim($_GET['buscar']) : '');

            $sql = "SELECT id, nombre, apellido, email, rol, admin_id, peso_corporal, telefono, observaciones, foto_perfil, activo, created_at 
                    FROM usuarios 
                    WHERE (admin_id = ? OR admin_id IS NULL OR id = ?)";
            $params = [$adminId, $adminId];

            if ($queryBusqueda !== '') {
                $sql .= " AND (LOWER(nombre) LIKE ? OR LOWER(apellido) LIKE ? OR LOWER(CONCAT(nombre, ' ', apellido)) LIKE ? OR LOWER(email) LIKE ?)";
                $term = '%' . mb_strtolower($queryBusqueda, 'UTF-8') . '%';
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }

            $sql .= " ORDER BY rol ASC, nombre ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll();
            sendJsonResponse(true, $usuarios);
            break;

        case 'cliente':
        case 'usuario_detalle':
            // Obtener perfil detallado de un cliente/usuario respetando permisos multi-tenant
            $currentUser = requireAuth();
            $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$currentUser['id'];

            if ($id <= 0) {
                sendJsonResponse(false, null, 'ID de usuario no especificado.', 400);
            }

            if (!canAccessUserData($id)) {
                sendJsonResponse(false, null, 'No tienes permisos para acceder a los datos de este usuario.', 403);
            }

            $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, rol, admin_id, peso_corporal, telefono, observaciones, foto_perfil, activo, created_at 
                                   FROM usuarios 
                                   WHERE id = ?");
            $stmt->execute([$id]);
            $cliente = $stmt->fetch();

            if (!$cliente) {
                sendJsonResponse(false, null, 'Usuario no encontrado.', 404);
            }

            sendJsonResponse(true, [
                'cliente' => $cliente,
                'usuario' => $cliente,
                'id' => $cliente['id'],
                'nombre' => $cliente['nombre'],
                'apellido' => $cliente['apellido'],
                'email' => $cliente['email'],
                'rol' => $cliente['rol'],
                'peso_corporal' => $cliente['peso_corporal'],
                'telefono' => $cliente['telefono'],
                'observaciones' => $cliente['observaciones'],
                'foto_perfil' => $cliente['foto_perfil'],
                'activo' => $cliente['activo']
            ], 'Detalle de usuario obtenido correctamente.');
            break;

        case 'usuario_guardar':
        case 'cliente_guardar':
            $currentUser = requireAuth();
            $data = getJsonInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $nombre = trim($data['nombre'] ?? '');
            $apellido = trim($data['apellido'] ?? '');
            $email = trim(strtolower($data['email'] ?? ''));
            $password = trim($data['password'] ?? '');
            $rol = trim($data['rol'] ?? 'cliente');
            $peso = isset($data['peso_corporal']) ? (float)$data['peso_corporal'] : 70.0;
            $telefono = trim($data['telefono'] ?? '');
            $observaciones = trim($data['observaciones'] ?? '');
            $activo = isset($data['activo']) ? ((int)$data['activo'] ? 1 : 0) : 1;

            // Restricción estricta de permisos y roles (Modelo SaaS Multi-Tenant):
            // 1. Un cliente solo puede actualizarse a sí mismo y NO puede cambiar su rol ni desactivarse.
            // 2. Un admin solo puede crear usuarios con rol 'cliente'.
            // 3. Un admin no puede cambiar el rol de un atleta a 'admin' (evitar escalada de privilegios).
            if ($currentUser['rol'] !== 'admin') {
                if ($id <= 0 || $id !== (int)$currentUser['id']) {
                    sendJsonResponse(false, null, 'No tienes permisos para modificar otros usuarios.', 403);
                }
                $rol = $currentUser['rol'];
                $activo = 1;
            } else {
                if ($id > 0) {
                    // Si el admin está editando, verificar que el usuario pertenezca a su espacio multi-tenant
                    if (!canAccessUserData($id)) {
                        sendJsonResponse(false, null, 'No tienes permisos para modificar este usuario.', 403);
                    }
                    // Si no es el propio perfil del admin, el rol debe permanecer como 'cliente'
                    if ($id !== (int)$currentUser['id']) {
                        $rol = 'cliente';
                    }
                } else {
                    // En creación de nuevos usuarios por parte del admin: SIEMPRE rol 'cliente'
                    $rol = 'cliente';
                }
            }

            if (empty($nombre) || empty($apellido) || empty($email)) {
                sendJsonResponse(false, null, 'Nombre, apellido y correo electrónico son obligatorios.', 400);
            }

            // Validar unicidad de email
            $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = ? AND id != ?");
            $stmtEmail->execute([$email, $id]);
            if ($stmtEmail->fetch()) {
                sendJsonResponse(false, null, 'El correo electrónico ya está registrado por otro usuario.', 400);
            }

            if ($id > 0) {
                // Actualizar usuario existente
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE usuarios 
                                           SET nombre = ?, apellido = ?, email = ?, password_hash = ?, rol = ?, peso_corporal = ?, telefono = ?, observaciones = ?, activo = ? 
                                           WHERE id = ?");
                    $stmt->execute([$nombre, $apellido, $email, $hash, $rol, $peso, $telefono, $observaciones, $activo, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios 
                                           SET nombre = ?, apellido = ?, email = ?, rol = ?, peso_corporal = ?, telefono = ?, observaciones = ?, activo = ? 
                                           WHERE id = ?");
                    $stmt->execute([$nombre, $apellido, $email, $rol, $peso, $telefono, $observaciones, $activo, $id]);
                }
                $mensaje = 'Usuario actualizado correctamente.';
            } else {
                // Crear nuevo usuario (Solo Admin desde panel del coach)
                if ($currentUser['rol'] !== 'admin') {
                    sendJsonResponse(false, null, 'Solo los administradores pueden crear nuevos usuarios.', 403);
                }
                // SIEMPRE rol 'cliente' al crear desde panel
                $rol = 'cliente';
                if (empty($password)) {
                    $password = 'cliente123'; // Contraseña por defecto asignada al crear alumno
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Multi-Tenant: Vincular obligatoriamente al admin actual
                $adminIdAsignado = (int)$currentUser['id'];

                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, admin_id, peso_corporal, telefono, observaciones, activo) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $apellido, $email, $hash, $rol, $adminIdAsignado, $peso, $telefono, $observaciones, $activo]);
                $id = (int)$pdo->lastInsertId();

                // Si es cliente, crear automáticamente su primera rutina de 5 semanas
                if ($rol === 'cliente') {
                    $stmtR = $pdo->prepare("INSERT INTO rutinas_semana (cliente_id, nombre, descripcion, fecha_inicio, activo) 
                                            VALUES (?, 'Mesociclo 1 (S1-S5)', 'Planificación inicial de 5 microciclos', CURDATE(), 1)");
                    $stmtR->execute([$id]);
                }
                $mensaje = 'Nuevo usuario registrado con éxito en su espacio de gestión.';
            }

            $stmtGet = $pdo->prepare("SELECT id, nombre, apellido, email, rol, admin_id, peso_corporal, telefono, observaciones, foto_perfil, activo FROM usuarios WHERE id = ?");
            $stmtGet->execute([$id]);
            $usuarioGuardado = $stmtGet->fetch();

            sendJsonResponse(true, $usuarioGuardado, $mensaje);
            break;

        case 'cliente_actualizar_peso':
            $currentUser = requireAuth();
            $data = getJsonInput();
            $id = isset($data['id']) ? (int)$data['id'] : (int)$currentUser['id'];
            $peso = isset($data['peso_corporal']) ? (float)$data['peso_corporal'] : null;

            if ($peso === null || $peso <= 0) {
                sendJsonResponse(false, null, 'Debe especificar un peso corporal válido en kg.', 400);
            }

            // Verificar permiso (Admin del alumno o el propio usuario)
            if (!canAccessUserData($id)) {
                sendJsonResponse(false, null, 'No tienes permisos para actualizar el peso de este usuario.', 403);
            }

            $stmt = $pdo->prepare("UPDATE usuarios SET peso_corporal = ? WHERE id = ?");
            $stmt->execute([$peso, $id]);
            sendJsonResponse(true, ['peso_corporal' => $peso], 'Peso corporal actualizado correctamente.');
            break;

        case 'usuario_eliminar':
            $currentUser = requireAdmin();
            $data = getJsonInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;

            if ($id <= 0 || $id === (int)$currentUser['id']) {
                sendJsonResponse(false, null, 'No puedes eliminar tu propio usuario administrador.', 400);
            }

            // Validar que el atleta pertenezca al espacio del admin actual
            if (!canAccessUserData($id)) {
                sendJsonResponse(false, null, 'No tienes permisos para eliminar este usuario.', 403);
            }

            // Eliminar archivos físicos de fotos de progreso antes de borrar de la base de datos
            $stmtFotos = $pdo->prepare("SELECT ruta_imagen FROM fotos_progreso WHERE usuario_id = ?");
            $stmtFotos->execute([$id]);
            $fotosUsuario = $stmtFotos->fetchAll();
            foreach ($fotosUsuario as $f) {
                if (!empty($f['ruta_imagen'])) {
                    $archivoFisico = __DIR__ . '/' . $f['ruta_imagen'];
                    if (file_exists($archivoFisico) && is_file($archivoFisico)) {
                        @unlink($archivoFisico);
                    }
                }
            }

            // Eliminación física y permanente del usuario (Cascada a rutinas, cargas y fotos)
            $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmtDel->execute([$id]);
            sendJsonResponse(true, ['id' => $id], 'Atleta y todo su historial eliminados de forma permanente.');
            break;


        // ======================================================================
        // 3. RUTINAS Y VISUALIZACIÓN POR SEMANA (S1..S5) CON CALENTAMIENTO
        // ======================================================================

        case 'rutina':
            $currentUser = requireAuth();
            $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : (int)$currentUser['id'];
            $semana = isset($_GET['semana']) ? (int)$_GET['semana'] : 1;

            if ($semana < 1 || $semana > 5) {
                $semana = 1;
            }

            // Si es cliente, solo puede consultar su propia rutina
            if ($currentUser['rol'] !== 'admin' && $clienteId !== (int)$currentUser['id']) {
                $clienteId = (int)$currentUser['id'];
            }

            // Control de aislamiento Multi-Tenant:
            if (!canAccessUserData($clienteId)) {
                sendJsonResponse(false, null, 'No tienes permisos para consultar la rutina de este cliente.', 403);
            }

            // Datos del cliente
            $stmtC = $pdo->prepare("SELECT id, nombre, apellido, email, telefono, peso_corporal, observaciones, foto_perfil, rol FROM usuarios WHERE id = ?");
            $stmtC->execute([$clienteId]);
            $cliente = $stmtC->fetch();
            if (!$cliente) {
                sendJsonResponse(false, null, 'Cliente no encontrado.', 404);
            }

            // Rutina activa
            $stmtR = $pdo->prepare("SELECT * FROM rutinas_semana WHERE cliente_id = ? AND activo = 1 ORDER BY id DESC LIMIT 1");
            $stmtR->execute([$clienteId]);
            $rutina = $stmtR->fetch();

            if (!$rutina) {
                // Crear rutina inicial si no existía
                $stmtNew = $pdo->prepare("INSERT INTO rutinas_semana (cliente_id, nombre, descripcion, fecha_inicio, activo) 
                                          VALUES (?, 'Mesociclo 1 (S1-S5)', 'Rutina de 5 microciclos', CURDATE(), 1)");
                $stmtNew->execute([$clienteId]);
                $stmtR->execute([$clienteId]);
                $rutina = $stmtR->fetch();
            }

            $rutinaId = (int)$rutina['id'];

            // Obtener todos los ejercicios con su imagen demostrativa
            $stmtE = $pdo->prepare("SELECT id, rutina_id, dia_semana, nombre_ejercicio, series, repeticiones, un_rm_estimado, imagen_url, observaciones, orden 
                                    FROM ejercicios_rutina 
                                    WHERE rutina_id = ? 
                                    ORDER BY dia_semana ASC, orden ASC, id ASC");
            $stmtE->execute([$rutinaId]);
            $ejercicios = $stmtE->fetchAll();

            // Cargar notas de movilidad / calentamiento previo para cada día
            $stmtMov = $pdo->prepare("SELECT dia_semana, movilidad FROM rutina_calentamiento WHERE rutina_id = ?");
            $stmtMov->execute([$rutinaId]);
            $movilidades = $stmtMov->fetchAll(PDO::FETCH_KEY_PAIR);

            // Cargar registro de cargas para estos ejercicios (S1..S5)
            $cargasPorEjercicio = [];
            if (!empty($ejercicios)) {
                $ejercicioIds = array_column($ejercicios, 'id');
                $placeholders = implode(',', array_fill(0, count($ejercicioIds), '?'));
                $stmtCargas = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id IN ($placeholders)");
                $stmtCargas->execute($ejercicioIds);
                $todasCargas = $stmtCargas->fetchAll();

                foreach ($todasCargas as $c) {
                    $cargasPorEjercicio[$c['ejercicio_id']][$c['semana']] = $c;
                }
            }

            // Agrupar por días y asociar carga de semana activa + historial S1..S5
            $diasAgrupados = [];
            foreach ($ejercicios as $ej) {
                $eId = (int)$ej['id'];
                $diaNum = (int)$ej['dia_semana'];

                $cargaActual = $cargasPorEjercicio[$eId][$semana] ?? [
                    'id'               => null,
                    'ejercicio_id'     => $eId,
                    'semana'           => $semana,
                    'peso_kg'          => 0.00,
                    'porcentaje_carga' => 0.00,
                    'completado'       => 0,
                    'notas'            => ''
                ];

                $historial = [];
                for ($s = 1; $s <= 5; $s++) {
                    $historial[$s] = $cargasPorEjercicio[$eId][$s] ?? [
                        'ejercicio_id'     => $eId,
                        'semana'           => $s,
                        'peso_kg'          => 0.00,
                        'porcentaje_carga' => 0.00,
                        'completado'       => 0,
                        'notas'            => ''
                    ];
                }

                $ej['carga_actual'] = $cargaActual;
                $ej['historial_cargas'] = $historial;

                if (!isset($diasAgrupados[$diaNum])) {
                    $diasAgrupados[$diaNum] = [
                        'dia_semana' => $diaNum,
                        'titulo'     => "Día $diaNum",
                        'movilidad'  => $movilidades[$diaNum] ?? '',
                        'ejercicios' => []
                    ];
                }
                $diasAgrupados[$diaNum]['ejercicios'][] = $ej;
            }

            // Incluir días que tengan movilidad configurada aunque aún no tengan ejercicios
            foreach ($movilidades as $dNum => $mTexto) {
                $dInt = (int)$dNum;
                if (!isset($diasAgrupados[$dInt])) {
                    $diasAgrupados[$dInt] = [
                        'dia_semana' => $dInt,
                        'titulo'     => "Día $dInt",
                        'movilidad'  => $mTexto,
                        'ejercicios' => []
                    ];
                }
            }
            ksort($diasAgrupados);

            sendJsonResponse(true, [
                'cliente'       => $cliente,
                'rutina'        => $rutina,
                'semana_activa' => $semana,
                'dias'          => array_values($diasAgrupados)
            ]);
            break;

        case 'rutina_guardar_movilidad':
            // Permite al Administrador configurar el bloque de movilidad previa por día
            $currentUser = requireAdmin();
            $data = getJsonInput();
            $rutinaId = isset($data['rutina_id']) ? (int)$data['rutina_id'] : 0;
            $diaSemana = isset($data['dia_semana']) ? (int)$data['dia_semana'] : 1;
            $movilidad = trim($data['movilidad'] ?? '');

            if ($rutinaId <= 0 || $diaSemana < 1 || $diaSemana > 7) {
                sendJsonResponse(false, null, 'Parámetros de rutina o día inválidos.', 400);
            }

            // Validar que la rutina pertenezca al espacio del admin actual
            $stmtR = $pdo->prepare("SELECT cliente_id FROM rutinas_semana WHERE id = ?");
            $stmtR->execute([$rutinaId]);
            $rutina = $stmtR->fetch();
            if (!$rutina || !canAccessUserData((int)$rutina['cliente_id'])) {
                sendJsonResponse(false, null, 'No tienes permisos para modificar el calentamiento de esta rutina.', 403);
            }

            $stmtUpsertMov = $pdo->prepare("INSERT INTO rutina_calentamiento (rutina_id, dia_semana, movilidad) 
                                           VALUES (?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE movilidad = VALUES(movilidad), updated_at = NOW()");
            $stmtUpsertMov->execute([$rutinaId, $diaSemana, $movilidad]);

            sendJsonResponse(true, [
                'rutina_id'  => $rutinaId,
                'dia_semana' => $diaSemana,
                'movilidad'  => $movilidad
            ], "Bloque de movilidad y calentamiento previo del Día $diaSemana actualizado con éxito.");
            break;

        case 'ejercicio_guardar':
            // Solo los Administradores / Entrenadores pueden crear o modificar ejercicios de la rutina
            $currentUser = requireAdmin();
            $data = getJsonInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $rutinaId = isset($data['rutina_id']) ? (int)$data['rutina_id'] : 0;
            $diaSemana = isset($data['dia_semana']) ? (int)$data['dia_semana'] : 1;
            $nombre = trim($data['nombre_ejercicio'] ?? '');
            $series = isset($data['series']) ? (int)$data['series'] : 4;
            $repeticiones = trim($data['repeticiones'] ?? '8-10');
            $unRm = isset($data['un_rm_estimado']) ? (float)$data['un_rm_estimado'] : 0.00;
            $imagenUrl = trim($data['imagen_url'] ?? '');
            $observaciones = trim($data['observaciones'] ?? '');
            $orden = isset($data['orden']) ? (int)$data['orden'] : 1;

            if ($rutinaId <= 0 || empty($nombre)) {
                sendJsonResponse(false, null, 'El nombre del ejercicio y la rutina son obligatorios.', 400);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE ejercicios_rutina 
                                       SET dia_semana = ?, nombre_ejercicio = ?, series = ?, repeticiones = ?, un_rm_estimado = ?, imagen_url = ?, observaciones = ?, orden = ? 
                                       WHERE id = ?");
                $stmt->execute([$diaSemana, $nombre, $series, $repeticiones, $unRm, $imagenUrl ?: null, $observaciones, $orden, $id]);
                $mensaje = 'Ejercicio actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO ejercicios_rutina (rutina_id, dia_semana, nombre_ejercicio, series, repeticiones, un_rm_estimado, imagen_url, observaciones, orden) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$rutinaId, $diaSemana, $nombre, $series, $repeticiones, $unRm, $imagenUrl ?: null, $observaciones, $orden]);
                $id = (int)$pdo->lastInsertId();
                $mensaje = 'Ejercicio añadido a la rutina.';
            }

            $stmtEj = $pdo->prepare("SELECT * FROM ejercicios_rutina WHERE id = ?");
            $stmtEj->execute([$id]);
            sendJsonResponse(true, $stmtEj->fetch(), $mensaje);
            break;

        case 'ejercicio_eliminar':
            $currentUser = requireAdmin();
            $data = getJsonInput();
            $id = isset($data['id']) ? (int)$data['id'] : 0;

            if ($id <= 0) {
                sendJsonResponse(false, null, 'ID de ejercicio inválido.', 400);
            }

            $stmt = $pdo->prepare("DELETE FROM ejercicios_rutina WHERE id = ?");
            $stmt->execute([$id]);
            sendJsonResponse(true, null, 'Ejercicio eliminado de la rutina.');
            break;


        // ======================================================================
        // 4. REGISTRO Y ACTUALIZACIÓN DE CARGAS (S1..S5)
        // ======================================================================

        case 'carga_guardar':
            $currentUser = requireAuth();
            $data = getJsonInput();
            $ejercicioId = isset($data['ejercicio_id']) ? (int)$data['ejercicio_id'] : 0;
            $semana = isset($data['semana']) ? (int)$data['semana'] : 1;
            $pesoKg = isset($data['peso_kg']) ? (float)$data['peso_kg'] : 0.00;
            $porcentaje = isset($data['porcentaje_carga']) ? (float)$data['porcentaje_carga'] : 0.00;
            $completado = isset($data['completado']) ? ((int)$data['completado'] ? 1 : 0) : 0;
            $notas = trim($data['notas'] ?? '');

            if ($ejercicioId <= 0 || $semana < 1 || $semana > 5) {
                sendJsonResponse(false, null, 'Ejercicio o semana fuera de rango.', 400);
            }

            // Validar que el ejercicio pertenezca al cliente actual (si no es admin)
            $stmtCheck = $pdo->prepare("SELECT e.id, e.un_rm_estimado, r.cliente_id 
                                        FROM ejercicios_rutina e 
                                        JOIN rutinas_semana r ON e.rutina_id = r.id 
                                        WHERE e.id = ?");
            $stmtCheck->execute([$ejercicioId]);
            $ejInfo = $stmtCheck->fetch();

            if (!$ejInfo) {
                sendJsonResponse(false, null, 'Ejercicio no encontrado.', 404);
            }

            if (!canAccessUserData((int)$ejInfo['cliente_id'])) {
                sendJsonResponse(false, null, 'No tienes permisos para modificar las cargas de este atleta.', 403);
            }

            $unRm = (float)$ejInfo['un_rm_estimado'];
            if ($pesoKg > 0 && $unRm > 0 && ($porcentaje <= 0 || !isset($data['porcentaje_carga']))) {
                $porcentaje = round(($pesoKg / $unRm) * 100, 2);
            } elseif ($porcentaje > 0 && $unRm > 0 && ($pesoKg <= 0 || !isset($data['peso_kg']))) {
                $pesoKg = round(($porcentaje / 100) * $unRm, 2);
            }

            $stmtUpsert = $pdo->prepare("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg, porcentaje_carga, completado, notas)
                                         VALUES (?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE 
                                            peso_kg = VALUES(peso_kg),
                                            porcentaje_carga = VALUES(porcentaje_carga),
                                            completado = VALUES(completado),
                                            notas = VALUES(notas),
                                            updated_at = NOW()");
            $stmtUpsert->execute([$ejercicioId, $semana, $pesoKg, $porcentaje, $completado, $notas]);

            $stmtGet = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = ?");
            $stmtGet->execute([$ejercicioId, $semana]);
            sendJsonResponse(true, $stmtGet->fetch(), "Carga para Semana $semana actualizada correctamente.");
            break;

        case 'carga_copiar_semana':
            $currentUser = requireAuth();
            $data = getJsonInput();
            $ejercicioId = isset($data['ejercicio_id']) ? (int)$data['ejercicio_id'] : 0;
            $semanaOrigen = isset($data['semana_origen']) ? (int)$data['semana_origen'] : 1;
            $semanaDestino = isset($data['semana_destino']) ? (int)$data['semana_destino'] : 2;
            $incrementoKg = isset($data['incremento_kg']) ? (float)$data['incremento_kg'] : 0.0;

            // Validar propiedad del ejercicio
            $stmtCheck = $pdo->prepare("SELECT e.id, e.un_rm_estimado, r.cliente_id 
                                        FROM ejercicios_rutina e 
                                        JOIN rutinas_semana r ON e.rutina_id = r.id 
                                        WHERE e.id = ?");
            $stmtCheck->execute([$ejercicioId]);
            $ejInfo = $stmtCheck->fetch();

            if (!$ejInfo || !canAccessUserData((int)$ejInfo['cliente_id'])) {
                sendJsonResponse(false, null, 'No autorizado.', 403);
            }

            $stmtOrig = $pdo->prepare("SELECT * FROM registro_cargas WHERE ejercicio_id = ? AND semana = ?");
            $stmtOrig->execute([$ejercicioId, $semanaOrigen]);
            $origen = $stmtOrig->fetch();

            if (!$origen) {
                sendJsonResponse(false, null, "No hay cargas registradas en la Semana $semanaOrigen para clonar.", 404);
            }

            // Copia fiel: la semana de destino recibe exactamente el mismo peso y porcentaje que tenía al cierre (sin incremento forzado)
            $nuevoPeso = max(0, (float)$origen['peso_kg'] + $incrementoKg);
            $unRm = (float)$ejInfo['un_rm_estimado'];
            $nuevoPorcentaje = ($incrementoKg > 0 && $unRm > 0)
                ? round(($nuevoPeso / $unRm) * 100, 2)
                : (float)$origen['porcentaje_carga'];

            $stmtUpsert = $pdo->prepare("INSERT INTO registro_cargas (ejercicio_id, semana, peso_kg, porcentaje_carga, completado, notas)
                                         VALUES (?, ?, ?, ?, 0, ?)
                                         ON DUPLICATE KEY UPDATE 
                                            peso_kg = VALUES(peso_kg),
                                            porcentaje_carga = VALUES(porcentaje_carga),
                                            completado = 0,
                                            notas = VALUES(notas)");
            $stmtUpsert->execute([$ejercicioId, $semanaDestino, $nuevoPeso, $nuevoPorcentaje, $origen['notas'] ?: ('Copiado desde S' . $semanaOrigen)]);

            sendJsonResponse(true, [
                'ejercicio_id'     => $ejercicioId,
                'semana'           => $semanaDestino,
                'peso_kg'          => $nuevoPeso,
                'porcentaje_carga' => $nuevoPorcentaje
            ], "Carga copiada a Semana $semanaDestino con los mismos valores de S$semanaOrigen.");
            break;


        // ======================================================================
        // 5. MÓDULO DE FOTOS DE PROGRESO (SUBIDA, LISTADO, COMPARACIÓN, ELIMINAR)
        // ======================================================================

        // ======================================================================
        // 5. MÓDULO DE FOTOS DE PROGRESO (SUBIDA, LISTADO, COMPARACIÓN, ELIMINAR)
        // ======================================================================

        case 'upload_progreso':
        case 'foto_subir':
            $currentUser = requireAuth();

            // RESTRICCIÓN ESTRICTA DE SEGURIDAD:
            // Los administradores/entrenadores NO pueden subir fotos en nombre del alumno.
            // Es potestad exclusiva del usuario/cliente subir sus propias fotos de progreso.
            if ($currentUser['rol'] === 'admin') {
                sendJsonResponse(false, null, 'Restricción de permisos: Los administradores NO están autorizados para subir fotos de progreso en nombre de los atletas. Solo el atleta/cliente puede subir sus propias fotos desde su perfil personal.', 403);
            }

            // El usuario que sube es forzosamente el cliente autenticado
            $usuarioId = (int)$currentUser['id'];

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = 'No se ha seleccionado ninguna imagen válida.';
                if (isset($_FILES['foto']['error'])) {
                    $errorMsg .= ' Código error: ' . $_FILES['foto']['error'];
                }
                sendJsonResponse(false, null, $errorMsg, 400);
            }

            $file = $_FILES['foto'];
            $maxSize = 8 * 1024 * 1024; // 8 MB

            if ($file['size'] > $maxSize) {
                sendJsonResponse(false, null, 'El tamaño de la imagen supera el límite de 8MB.', 400);
            }

            // Validar tipo MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/jpg'  => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                'image/svg+xml' => 'svg'
            ];

            if (!isset($allowedMimes[$mime])) {
                sendJsonResponse(false, null, 'Formato de imagen no permitido. Usa JPG, PNG, WEBP, GIF o SVG.', 400);
            }

            $ext = $allowedMimes[$mime];
            $dirSubida = __DIR__ . '/uploads/progreso';
            if (!is_dir($dirSubida)) {
                mkdir($dirSubida, 0755, true);
            }

            // Generar nombre de archivo único y seguro
            $nombreArchivo = sprintf('progreso_%d_s%d_%s.%s', $usuarioId, (int)($_POST['semana'] ?? 1), uniqid(), $ext);
            $destino = $dirSubida . DIRECTORY_SEPARATOR . $nombreArchivo;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                sendJsonResponse(false, null, 'Error al almacenar el archivo en el servidor.', 500);
            }

            $rutaRelativa = 'uploads/progreso/' . $nombreArchivo;
            $semana = isset($_POST['semana']) ? max(1, min(5, (int)$_POST['semana'])) : 1;
            $tipoAngulo = trim($_POST['tipo_angulo'] ?? 'frente');
            $fechaSubida = !empty($_POST['fecha_subida']) ? $_POST['fecha_subida'] : date('Y-m-d');
            $observaciones = trim($_POST['observaciones'] ?? '');

            $stmtFoto = $pdo->prepare("INSERT INTO fotos_progreso (usuario_id, ruta_imagen, fecha_subida, semana, tipo_angulo, observaciones) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
            $stmtFoto->execute([$usuarioId, $rutaRelativa, $fechaSubida, $semana, $tipoAngulo, $observaciones]);
            $fotoId = (int)$pdo->lastInsertId();

            $stmtGet = $pdo->prepare("SELECT * FROM fotos_progreso WHERE id = ?");
            $stmtGet->execute([$fotoId]);
            $fotoGuardada = $stmtGet->fetch();

            sendJsonResponse(true, $fotoGuardada, 'Foto de progreso subida exitosamente por el atleta.');
            break;

        case 'fotos_listar':
            // Listado de fotos para el usuario autenticado (cliente)
            $currentUser = requireAuth();
            $usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : (int)$currentUser['id'];
            $semana = isset($_GET['semana']) ? (int)$_GET['semana'] : 0;

            // Si es cliente, solo puede consultar sus propias fotos
            if ($currentUser['rol'] !== 'admin') {
                $usuarioId = (int)$currentUser['id'];
            }

            $sql = "SELECT * FROM fotos_progreso WHERE usuario_id = ?";
            $params = [$usuarioId];

            if ($semana > 0 && $semana <= 5) {
                $sql .= " AND semana = ?";
                $params[] = $semana;
            }

            $sql .= " ORDER BY fecha_subida DESC, semana DESC, id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $fotos = $stmt->fetchAll();

            sendJsonResponse(true, $fotos);
            break;

        case 'get_fotos_progreso_admin':
        case 'fotos_listar_admin':
            // Endpoint EXCLUSIVO para Administradores: Consulta en modo SOLO LECTURA de cualquier alumno
            $currentUser = requireAdmin();
            $usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
            $semana = isset($_GET['semana']) ? (int)$_GET['semana'] : 0;

            if ($usuarioId <= 0) {
                sendJsonResponse(false, null, 'Debe especificar el ID del alumno para consultar su progreso visual.', 400);
            }

            // Aislamiento Multi-Tenant: verificar que el alumno pertenezca al admin actual
            if (!canAccessUserData($usuarioId)) {
                sendJsonResponse(false, null, 'No tienes permisos para consultar las fotos de este alumno.', 403);
            }

            // Verificar existencia del alumno
            $stmtAlumno = $pdo->prepare("SELECT id, nombre, apellido, peso_corporal FROM usuarios WHERE id = ?");
            $stmtAlumno->execute([$usuarioId]);
            $alumno = $stmtAlumno->fetch();

            if (!$alumno) {
                sendJsonResponse(false, null, 'Alumno no encontrado.', 404);
            }

            $sql = "SELECT * FROM fotos_progreso WHERE usuario_id = ?";
            $params = [$usuarioId];

            if ($semana > 0 && $semana <= 5) {
                $sql .= " AND semana = ?";
                $params[] = $semana;
            }

            $sql .= " ORDER BY fecha_subida DESC, semana DESC, id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $fotos = $stmt->fetchAll();

            sendJsonResponse(true, [
                'alumno' => $alumno,
                'fotos'  => $fotos,
                'total'  => count($fotos),
                'modo'   => 'read_only'
            ], 'Galería de fotos de progreso en modo solo lectura para entrenador.');
            break;

        case 'foto_eliminar':
            $currentUser = requireAuth();

            // RESTRICCIÓN: El administrador NO puede eliminar fotos de progreso de los alumnos
            if ($currentUser['rol'] === 'admin') {
                sendJsonResponse(false, null, 'Restricción de permisos: Los administradores tienen acceso en modo solo lectura y no pueden eliminar fotos de los atletas.', 403);
            }

            $data = getJsonInput();
            $fotoId = isset($data['id']) ? (int)$data['id'] : 0;

            if ($fotoId <= 0) {
                sendJsonResponse(false, null, 'ID de foto inválido.', 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM fotos_progreso WHERE id = ?");
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch();

            if (!$foto) {
                sendJsonResponse(false, null, 'Foto no encontrada.', 404);
            }

            // Solo el dueño de la foto puede eliminarla
            if ((int)$foto['usuario_id'] !== (int)$currentUser['id']) {
                sendJsonResponse(false, null, 'No tienes permiso para eliminar esta foto.', 403);
            }

            // Eliminar archivo físico si existe
            $archivoFisico = __DIR__ . '/' . $foto['ruta_imagen'];
            if (file_exists($archivoFisico) && is_file($archivoFisico)) {
                @unlink($archivoFisico);
            }

            $stmtDel = $pdo->prepare("DELETE FROM fotos_progreso WHERE id = ?");
            $stmtDel->execute([$fotoId]);

            sendJsonResponse(true, null, 'Foto de progreso eliminada por el atleta.');
            break;


        // ======================================================================
        // 6. GESTIÓN Y LISTADO DE IMÁGENES DE EJERCICIOS (LOCALES Y SUBIDAS)
        // ======================================================================

        case 'listar_imagenes_locales':
            $dir = __DIR__ . '/assets/ejercicios/exercise_images';
            $archivosValidos = [];
            $formatosPermitidos = ['gif', 'png', 'jpg', 'jpeg', 'svg', 'webp'];

            if (is_dir($dir)) {
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $rutaCompleta = $dir . DIRECTORY_SEPARATOR . $item;
                    if (is_file($rutaCompleta)) {
                        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                        if (in_array($ext, $formatosPermitidos)) {
                            // Limpiar nombre para visualización amigable
                            $nombreBase = pathinfo($item, PATHINFO_FILENAME);
                            $nombreLimpio = preg_replace('/^\d+[-_]?/', '', $nombreBase);
                            $nombreLimpio = str_replace('_', ' ', $nombreLimpio);
                            $nombreLimpio = trim(preg_replace('/\s+/', ' ', $nombreLimpio));

                            $archivosValidos[] = [
                                'archivo' => $item,
                                'nombre'  => $nombreLimpio ?: $item,
                                'ruta'    => 'assets/ejercicios/exercise_images/' . $item
                            ];
                        }
                    }
                }
            }

            // Ordenar alfabéticamente por nombre
            usort($archivosValidos, function ($a, $b) {
                return strnatcasecmp($a['nombre'], $b['nombre']);
            });

            sendJsonResponse(true, $archivosValidos, 'Listado de imágenes locales obtenido con éxito.');
            break;

        case 'ejercicio_subir_imagen':
            $currentUser = requireAdmin();

            if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                sendJsonResponse(false, null, 'No se ha enviado un archivo de imagen válido.', 400);
            }

            $file = $_FILES['imagen'];
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/jpg'  => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                'image/svg+xml' => 'svg'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowedMimes[$mime])) {
                sendJsonResponse(false, null, 'Formato no permitido. Usa JPG, PNG, WEBP, GIF o SVG.', 400);
            }

            $ext = $allowedMimes[$mime];
            $dirSubida = __DIR__ . '/uploads/ejercicios';
            if (!is_dir($dirSubida)) {
                mkdir($dirSubida, 0755, true);
            }

            $nombreArchivo = sprintf('ejercicio_%s.%s', uniqid(), $ext);
            $destino = $dirSubida . DIRECTORY_SEPARATOR . $nombreArchivo;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                sendJsonResponse(false, null, 'Error al guardar la imagen en el servidor.', 500);
            }

            $rutaRelativa = 'uploads/ejercicios/' . $nombreArchivo;
            sendJsonResponse(true, ['url' => $rutaRelativa], 'Imagen demostrativa subida con éxito.');
            break;

        default:
            sendJsonResponse(false, null, "Acción desconocida o no especificada ('$action').", 400);
            break;
    }
} catch (PDOException $e) {
    sendJsonResponse(false, null, 'Error en base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendJsonResponse(false, null, 'Error del servidor: ' . $e->getMessage(), 500);
}
