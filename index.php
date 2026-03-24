<?php
require __DIR__ . '/config/database.php';

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function clearPendingProcedureResults($connection)
{
    while ($connection->more_results() && $connection->next_result()) {
        $bufferedResult = $connection->store_result();
        if ($bufferedResult) {
            $bufferedResult->free();
        }
    }
}

function bindStatementParams($statement, $types, $params)
{
    if ($types === '' || empty($params)) {
        return true;
    }

    $bindArgs = [$types];
    foreach (array_values($params) as $index => $value) {
        $params[$index] = $value;
        $bindArgs[] = &$params[$index];
    }

    return call_user_func_array([$statement, 'bind_param'], $bindArgs);
}

function writeDiagnosticLog($message, $context = [])
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $payload = [
        'time' => date('c'),
        'message' => $message,
        'context' => $context
    ];

    @file_put_contents($logDir . '/runtime.log', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function callProcedureSingleRow($connection, $sql, $types = '', $params = [])
{
    $statement = $connection->prepare($sql);

    if (!$statement) {
        writeDiagnosticLog('prepare_failed_single_row', ['sql' => $sql, 'errno' => (int) $connection->errno, 'error' => (string) $connection->error]);
        return ['ok' => false, 'errno' => (int) $connection->errno, 'error' => (string) $connection->error];
    }

    if (!bindStatementParams($statement, $types, $params)) {
        $error = ['ok' => false, 'errno' => (int) $statement->errno, 'error' => (string) $statement->error];
        writeDiagnosticLog('bind_failed_single_row', ['sql' => $sql, 'types' => $types, 'params' => $params, 'errno' => $error['errno'], 'error' => $error['error']]);
        $statement->close();
        clearPendingProcedureResults($connection);
        return $error;
    }

    if (!$statement->execute()) {
        $error = ['ok' => false, 'errno' => (int) $statement->errno, 'error' => (string) $statement->error];
        writeDiagnosticLog('execute_failed_single_row', ['sql' => $sql, 'types' => $types, 'params' => $params, 'errno' => $error['errno'], 'error' => $error['error']]);
        $statement->close();
        clearPendingProcedureResults($connection);
        return $error;
    }

    $result = $statement->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($result) {
        $result->free();
    }

    $statement->close();
    clearPendingProcedureResults($connection);

    return ['ok' => true, 'row' => $row];
}

function callProcedureRows($connection, $sql, $types = '', $params = [])
{
    $statement = $connection->prepare($sql);

    if (!$statement) {
        writeDiagnosticLog('prepare_failed_rows', ['sql' => $sql, 'errno' => (int) $connection->errno, 'error' => (string) $connection->error]);
        return ['ok' => false, 'errno' => (int) $connection->errno, 'error' => (string) $connection->error];
    }

    if (!bindStatementParams($statement, $types, $params)) {
        $error = ['ok' => false, 'errno' => (int) $statement->errno, 'error' => (string) $statement->error];
        writeDiagnosticLog('bind_failed_rows', ['sql' => $sql, 'types' => $types, 'params' => $params, 'errno' => $error['errno'], 'error' => $error['error']]);
        $statement->close();
        clearPendingProcedureResults($connection);
        return $error;
    }

    if (!$statement->execute()) {
        $error = ['ok' => false, 'errno' => (int) $statement->errno, 'error' => (string) $statement->error];
        writeDiagnosticLog('execute_failed_rows', ['sql' => $sql, 'types' => $types, 'params' => $params, 'errno' => $error['errno'], 'error' => $error['error']]);
        $statement->close();
        clearPendingProcedureResults($connection);
        return $error;
    }

    $rows = [];
    $result = $statement->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }

    $statement->close();
    clearPendingProcedureResults($connection);

    return ['ok' => true, 'rows' => $rows];
}

function mapProcedureErrorToStatus($errorText, $defaultStatus = 'error_consulta')
{
    $message = strtolower(trim((string) $errorText));

    if ($message === '') {
        return $defaultStatus;
    }

    if (strpos($message, 'id_no_encontrado') !== false) {
        return 'id_no_encontrado';
    }

    if (strpos($message, 'id_ya_registrado') !== false) {
        return 'id_ya_registrado';
    }

    if (strpos($message, 'email_duplicado') !== false) {
        return 'email_duplicado';
    }

    if (strpos($message, 'usuario_inactivo') !== false) {
        return 'usuario_inactivo';
    }

    if (strpos($message, 'credenciales_invalidas') !== false) {
        return 'credenciales_invalidas';
    }

    if (strpos($message, 'no_disponible') !== false) {
        return 'no_disponible';
    }

    if (strpos($message, 'usuario_con_prestamo_activo') !== false) {
        return 'usuario_con_prestamo_activo';
    }

    if (strpos($message, 'ya tiene un prestamo activo') !== false) {
        return 'usuario_con_prestamo_activo';
    }

    if (strpos($message, 'no esta disponible') !== false) {
        return 'no_disponible';
    }

    if (strpos($message, 'error_configuracion') !== false) {
        return 'error_configuracion';
    }

    if (strpos($message, 'does not exist') !== false || strpos($message, 'procedure') !== false) {
        return 'error_configuracion';
    }

    return $defaultStatus;
}

function createLoanByModelForUser($model, $userId)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow($connection, "CALL sp_crear_prestamo_por_modelo(?, ?)", 'is', [(int) $userId, (string) $model]);
    $connection->close();

    if (!$call['ok']) {
        return mapProcedureErrorToStatus($call['error'], 'error_consulta');
    }

    return 'ok';
}

function loginUserAccount($email, $password)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return ['status' => 'error_consulta'];
    }

    $call = callProcedureSingleRow($connection, "CALL sp_login_usuario(?)", 's', [(string) $email]);
    $connection->close();

    if (!$call['ok']) {
        return ['status' => mapProcedureErrorToStatus($call['error'], 'error_consulta')];
    }

    $user = $call['row'];
    if (!$user) {
        return ['status' => 'credenciales_invalidas'];
    }

    if ((int) ($user['Activo'] ?? 0) !== 1) {
        return ['status' => 'usuario_inactivo'];
    }

    $hash = (string) ($user['PasswordHash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return ['status' => 'credenciales_invalidas'];
    }

    return [
        'status' => 'ok',
        'user' => [
            'id' => (int) $user['UsuarioID'],
            'name' => (string) ($user['Nombre'] ?: 'Usuario'),
            'email' => (string) $user['Email']
        ]
    ];
}

function getCurrentUserFromSession()
{
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    $user = $_SESSION['user'];

    if (!isset($user['id'], $user['name'], $user['email'])) {
        return null;
    }

    return [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email']
    ];
}

function getLoanHistoryByUserId($userId)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_historial_prestamos_usuario(?)", 'i', [(int) $userId]);
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $history = [];
    foreach ($call['rows'] as $row) {
        $history[] = [
            'prestamo_id' => (int) $row['PrestamoID'],
            'modelo' => (string) ($row['Modelo'] ?: 'Modelo no disponible'),
            'dia_uso' => (string) ($row['DiaUso'] ?: ''),
            'estado_viaje' => (int) ($row['EstadoViaje'] ?? 0)
        ];
    }

    return $history;
}

function registerUserAccount($schoolId, $name, $email, $password)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_registrar_usuario_con_id(?, ?, ?, ?)",
        'isss',
        [(int) $schoolId, (string) $name, (string) $email, (string) $passwordHash]
    );
    $connection->close();

    if (!$call['ok']) {
        writeDiagnosticLog('register_call_failed', ['school_id' => (int) $schoolId, 'email' => (string) $email, 'errno' => (int) ($call['errno'] ?? 0), 'error' => (string) ($call['error'] ?? '')]);
        $mapped = mapProcedureErrorToStatus($call['error'], 'error_consulta');
        if ($mapped !== 'error_consulta') {
            return $mapped;
        }

        $errorText = strtolower((string) ($call['error'] ?? ''));
        if (strpos($errorText, 'duplicate entry') !== false) {
            return 'email_duplicado';
        }

        if (strpos($errorText, 'foreign key') !== false) {
            return 'id_no_encontrado';
        }

        return 'error_consulta';
    }

    $rowStatus = strtolower(trim((string) ($call['row']['Status'] ?? '')));
    if ($rowStatus === 'ok') {
        return 'ok';
    }

    if ($rowStatus === 'id_no_encontrado') {
        return 'id_no_encontrado';
    }

    if ($rowStatus === 'id_ya_registrado') {
        return 'id_ya_registrado';
    }

    if ($rowStatus === 'email_duplicado') {
        return 'email_duplicado';
    }

    return 'error_consulta';
}

function handlePostActions()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (isset($_POST['logout_user'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: index.php?action=login&status=logout');
        exit;
    }

    if (isset($_POST['login_user'], $_POST['login_email'], $_POST['login_password'])) {
        $email = trim((string) $_POST['login_email']);
        $password = (string) $_POST['login_password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            header('Location: index.php?action=login&status=invalid');
            exit;
        }

        $login = loginUserAccount($email, $password);
        if ($login['status'] === 'ok') {
            session_regenerate_id(true);
            $_SESSION['user'] = $login['user'];
        }

        header('Location: index.php?action=login&status=' . urlencode($login['status']));
        exit;
    }

    if (isset($_POST['reserve_bike'], $_POST['bike_model'])) {
        $currentUser = getCurrentUserFromSession();
        if (!$currentUser) {
            header('Location: index.php?action=reserve&status=login_requerido');
            exit;
        }

        $model = trim((string) $_POST['bike_model']);
        if ($model === '') {
            header('Location: index.php?action=reserve&status=invalid');
            exit;
        }

        $result = createLoanByModelForUser($model, (int) $currentUser['id']);
        header('Location: index.php?action=reserve&status=' . urlencode($result));
        exit;
    }

    if (isset($_POST['register_user'], $_POST['register_school_id'], $_POST['register_name'], $_POST['register_email'], $_POST['register_password'])) {
        $schoolId = (int) $_POST['register_school_id'];
        $name = trim((string) $_POST['register_name']);
        $email = trim((string) $_POST['register_email']);
        $password = (string) $_POST['register_password'];

        if ($schoolId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            header('Location: index.php?action=register&status=invalid');
            exit;
        }

        $result = registerUserAccount($schoolId, $name, $email, $password);
        header('Location: index.php?action=register&status=' . urlencode($result));
        exit;
    }
}

function getFlashMessage()
{
    if (!isset($_GET['action'], $_GET['status'])) {
        return null;
    }

    $action = (string) $_GET['action'];
    $status = (string) $_GET['status'];

    if ($action === 'reserve') {
        if ($status === 'ok') {
            return ['type' => 'success', 'text' => 'Prestamo creado correctamente para el modelo seleccionado.'];
        }

        if ($status === 'no_disponible') {
            return ['type' => 'warning', 'text' => 'Ese modelo ya no tiene unidades disponibles.'];
        }

        if ($status === 'usuario_con_prestamo_activo') {
            return ['type' => 'warning', 'text' => 'No puedes reservar otra bicicleta mientras tengas un prestamo activo.'];
        }

        if ($status === 'login_requerido') {
            return ['type' => 'danger', 'text' => 'Debes iniciar sesion para reservar una bicicleta.'];
        }

        if ($status === 'invalid') {
            return ['type' => 'danger', 'text' => 'Solicitud de reserva invalida.'];
        }

        return ['type' => 'danger', 'text' => 'No fue posible actualizar el estado de la unidad.'];
    }

    if ($action === 'register') {
        if ($status === 'ok') {
            return ['type' => 'success', 'text' => 'Registro completado. Ya puedes iniciar sesion cuando se habilite esa opcion.'];
        }

        if ($status === 'error_configuracion') {
            return ['type' => 'danger', 'text' => 'Falta configurar el procedimiento sp_validar_id_escolar en la base de datos.'];
        }

        if ($status === 'id_no_encontrado') {
            return ['type' => 'danger', 'text' => 'El ID escolar no existe en alumnos o maestros.'];
        }

        if ($status === 'id_ya_registrado') {
            return ['type' => 'warning', 'text' => 'Ese ID escolar ya esta vinculado a una cuenta.'];
        }

        if ($status === 'email_duplicado') {
            return ['type' => 'warning', 'text' => 'Ese correo ya esta registrado.'];
        }

        if ($status === 'invalid') {
            return ['type' => 'danger', 'text' => 'Datos invalidos. Verifica nombre, correo y una contraseña de al menos 6 caracteres.'];
        }

        return ['type' => 'danger', 'text' => 'No fue posible completar el registro.'];
    }

    if ($action === 'login') {
        if ($status === 'ok') {
            return ['type' => 'success', 'text' => 'Sesion iniciada correctamente.'];
        }

        if ($status === 'logout') {
            return ['type' => 'info', 'text' => 'Sesion cerrada correctamente.'];
        }

        if ($status === 'usuario_inactivo') {
            return ['type' => 'warning', 'text' => 'Tu cuenta esta inactiva.'];
        }

        if ($status === 'credenciales_invalidas' || $status === 'invalid') {
            return ['type' => 'danger', 'text' => 'Correo o contraseña incorrectos.'];
        }

        return ['type' => 'danger', 'text' => 'No fue posible iniciar sesion.'];
    }

    return null;
}

function getBikesFromDatabase()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_catalogo_bicicletas_resumen()");
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $items = [];
    foreach ($call['rows'] as $row) {
        $items[] = [
            'n' => $row['Modelo'] ?: 'Sin modelo',
            'estado' => $row['EstadoModelo'] ?: 'Ocupada',
            'img' => $row['ImagenURL'],
            'total' => (int) $row['TotalUnidades'],
            'disponibles' => (int) $row['Disponibles']
        ];
    }

    return $items;
}

handlePostActions();

$flashMessage = getFlashMessage();
$currentUser = getCurrentUserFromSession();
$loanHistory = $currentUser ? getLoanHistoryByUserId($currentUser['id']) : [];

$bicicletas = getBikesFromDatabase();

if (empty($bicicletas)) {
    $bicicletas = [
        ['n' => 'Montaña Pro XT', 'estado' => 'Disponible', 'img' => 'https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=400', 'total' => 3, 'disponibles' => 3],
        ['n' => 'Urban Glass V2', 'estado' => 'Disponible', 'img' => 'https://images.pexels.com/photos/276517/pexels-photo-276517.jpeg?auto=compress&cs=tinysrgb&w=400', 'total' => 3, 'disponibles' => 3],
        ['n' => 'E-Volución UTN', 'estado' => 'Disponible', 'img' => 'https://images.pexels.com/photos/1595483/pexels-photo-1595483.jpeg?auto=compress&cs=tinysrgb&w=400', 'total' => 3, 'disponibles' => 3],
        ['n' => 'Ruta Halcón', 'estado' => 'Disponible', 'img' => 'https://images.pexels.com/photos/5449212/pexels-photo-5449212.jpeg?auto=compress&cs=tinysrgb&w=400', 'total' => 3, 'disponibles' => 3]
    ];
}

require __DIR__ . '/templates/page.php';
