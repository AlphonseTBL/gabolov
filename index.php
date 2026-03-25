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

    $role = strtolower(trim((string) ($user['Rol'] ?? 'miembro')));
    if ($role !== 'administrador') {
        $role = 'miembro';
    }

    return [
        'status' => 'ok',
        'user' => [
            'id' => (int) $user['UsuarioID'],
            'name' => (string) ($user['Nombre'] ?: 'Usuario'),
            'email' => (string) $user['Email'],
            'role' => $role
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

    $role = strtolower(trim((string) ($user['role'] ?? 'miembro')));
    if ($role !== 'administrador') {
        $role = 'miembro';
    }

    return [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => $role
    ];
}

function isAdminRole($role)
{
    return strtolower(trim((string) $role)) === 'administrador';
}

function isCurrentUserAdmin($user)
{
    return is_array($user) && isset($user['role']) && isAdminRole($user['role']);
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

function getAdminDashboardSummary()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return null;
    }

    $call = callProcedureSingleRow($connection, "CALL sp_resumen_admin_dashboard()");
    $connection->close();

    if (!$call['ok'] || empty($call['row']) || !is_array($call['row'])) {
        return null;
    }

    $row = $call['row'];

    return [
        'total_usuarios' => (int) ($row['TotalUsuarios'] ?? 0),
        'total_administradores' => (int) ($row['TotalAdministradores'] ?? 0),
        'total_miembros' => (int) ($row['TotalMiembros'] ?? 0),
        'usuarios_activos' => (int) ($row['UsuariosActivos'] ?? 0),
        'prestamos_activos' => (int) ($row['PrestamosActivos'] ?? 0),
        'bicicletas_disponibles' => (int) ($row['BicicletasDisponibles'] ?? 0),
        'bicicletas_totales' => (int) ($row['BicicletasTotales'] ?? 0)
    ];
}

function getAdminAlumnosList()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_admin_list_alumnos()");
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $items = [];
    foreach ($call['rows'] as $row) {
        $items[] = [
            'id' => (int) ($row['AlumnoID'] ?? 0),
            'nombre' => (string) ($row['Nombre'] ?? ''),
            'campo' => (string) ($row['Carrera'] ?? ''),
            'usuario_id' => isset($row['UsuarioID']) ? (int) $row['UsuarioID'] : null
        ];
    }

    return $items;
}

function getAdminMaestrosList()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_admin_list_maestros()");
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $items = [];
    foreach ($call['rows'] as $row) {
        $items[] = [
            'id' => (int) ($row['MaestroID'] ?? 0),
            'nombre' => (string) ($row['Nombre'] ?? ''),
            'campo' => (string) ($row['Departamento'] ?? ''),
            'usuario_id' => isset($row['UsuarioID']) ? (int) $row['UsuarioID'] : null
        ];
    }

    return $items;
}

function getAdminUsersList()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_admin_list_usuarios()");
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $items = [];
    foreach ($call['rows'] as $row) {
        $items[] = [
            'id' => (int) ($row['UsuarioID'] ?? 0),
            'nombre' => (string) ($row['Nombre'] ?? ''),
            'email' => (string) ($row['Email'] ?? ''),
            'rol' => (string) ($row['Rol'] ?? 'miembro'),
            'activo' => (int) ($row['Activo'] ?? 0),
            'ultimo_uso' => (string) ($row['UltimoUso'] ?? '')
        ];
    }

    return $items;
}

function createAdministratorUserAccount($name, $email, $password)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_crear_usuario_admin(?, ?, ?)",
        'sss',
        [(string) $name, (string) $email, (string) $passwordHash]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function upsertAlumnoAdmin($id, $nombre, $carrera)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_upsert_alumno(?, ?, ?)",
        'iss',
        [(int) $id, (string) $nombre, (string) $carrera]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function upsertMaestroAdmin($id, $nombre, $departamento)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_upsert_maestro(?, ?, ?)",
        'iss',
        [(int) $id, (string) $nombre, (string) $departamento]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function deleteAlumnoAdmin($id)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_delete_alumno(?)",
        'i',
        [(int) $id]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function deleteMaestroAdmin($id)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_delete_maestro(?)",
        'i',
        [(int) $id]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function getAdminBikesList()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_admin_list_bicicletas()");
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $items = [];
    foreach ($call['rows'] as $row) {
        $items[] = [
            'id' => (int) ($row['BicicletaID'] ?? 0),
            'modelo' => (string) ($row['Modelo'] ?? ''),
            'estado' => (string) ($row['Estado'] ?? 'Disponible'),
            'imagen_url' => (string) ($row['ImagenURL'] ?? ''),
            'comentarios' => (string) ($row['Comentarios'] ?? ''),
            'ultimo_uso' => (string) ($row['UltimoUso'] ?? '')
        ];
    }

    return $items;
}

function createBikeAdmin($modelo, $imagenUrl, $comentarios)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_create_bicicleta(?, ?, ?)",
        'sss',
        [(string) $modelo, (string) $imagenUrl, (string) $comentarios]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function updateBikeAdmin($id, $modelo, $estado, $imagenUrl, $comentarios)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_update_bicicleta(?, ?, ?, ?, ?)",
        'issss',
        [(int) $id, (string) $modelo, (string) $estado, (string) $imagenUrl, (string) $comentarios]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function deleteBikeAdmin($id)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow(
        $connection,
        "CALL sp_admin_delete_bicicleta(?)",
        'i',
        [(int) $id]
    );
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function getAdminTripHistory()
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return [];
    }

    $call = callProcedureRows($connection, "CALL sp_admin_historial_viajes()");
    $connection->close();

    if (!$call['ok']) {
        return [];
    }

    $items = [];
    foreach ($call['rows'] as $row) {
        $items[] = [
            'prestamo_id' => (int) ($row['PrestamoID'] ?? 0),
            'usuario_id' => (int) ($row['UsuarioID'] ?? 0),
            'nombre_usuario' => (string) ($row['NombreUsuario'] ?? 'Usuario no encontrado'),
            'bicicleta_id' => (int) ($row['BicicletaID'] ?? 0),
            'modelo' => (string) ($row['Modelo'] ?? ''),
            'dia_uso' => (string) ($row['DiaUso'] ?? ''),
            'estado_viaje' => (int) ($row['EstadoViaje'] ?? 0)
        ];
    }

    return $items;
}

function getActiveLoanByUserId($userId)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return null;
    }

    $call = callProcedureSingleRow($connection, "CALL sp_prestamo_activo_usuario(?)", 'i', [(int) $userId]);
    $connection->close();

    if (!$call['ok'] || empty($call['row']) || !is_array($call['row'])) {
        return null;
    }

    $row = $call['row'];

    return [
        'prestamo_id' => (int) ($row['PrestamoID'] ?? 0),
        'bicicleta_id' => (int) ($row['BicicletaID'] ?? 0),
        'modelo' => (string) ($row['Modelo'] ?? 'Sin modelo'),
        'dia_uso' => (string) ($row['DiaUso'] ?? '')
    ];
}

function buildAdminReportsData($summary, $tripHistory)
{
    $totalTrips = count($tripHistory);
    $tripsInProgress = 0;
    $tripsFinished = 0;
    $modelUsage = [];
    $userUsage = [];

    foreach ($tripHistory as $trip) {
        $isFinished = (int) ($trip['estado_viaje'] ?? 0) === 1;
        if ($isFinished) {
            $tripsFinished++;
        } else {
            $tripsInProgress++;
        }

        $model = (string) ($trip['modelo'] ?? 'Sin modelo');
        if (!isset($modelUsage[$model])) {
            $modelUsage[$model] = 0;
        }
        $modelUsage[$model]++;

        $userKey = (int) ($trip['usuario_id'] ?? 0) . '|' . (string) ($trip['nombre_usuario'] ?? 'Usuario');
        if (!isset($userUsage[$userKey])) {
            $userUsage[$userKey] = 0;
        }
        $userUsage[$userKey]++;
    }

    arsort($modelUsage);
    arsort($userUsage);

    $topModels = [];
    foreach (array_slice($modelUsage, 0, 5, true) as $model => $count) {
        $topModels[] = ['modelo' => $model, 'viajes' => (int) $count];
    }

    $topUsers = [];
    foreach (array_slice($userUsage, 0, 5, true) as $composite => $count) {
        $parts = explode('|', $composite, 2);
        $topUsers[] = [
            'usuario_id' => (int) ($parts[0] ?? 0),
            'nombre' => (string) ($parts[1] ?? 'Usuario'),
            'viajes' => (int) $count
        ];
    }

    $fleetSize = (int) ($summary['bicicletas_totales'] ?? 0);
    $activeLoans = (int) ($summary['prestamos_activos'] ?? 0);
    $fleetUsagePct = $fleetSize > 0 ? round(($activeLoans / $fleetSize) * 100, 1) : 0.0;

    return [
        'total_viajes' => $totalTrips,
        'viajes_en_curso' => $tripsInProgress,
        'viajes_finalizados' => $tripsFinished,
        'uso_flotilla_porcentaje' => $fleetUsagePct,
        'top_modelos' => $topModels,
        'top_usuarios' => $topUsers
    ];
}

function normalizeAdminReportMonth($rawMonth)
{
    $month = trim((string) $rawMonth);
    if ($month === '' || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        return date('Y-m');
    }

    return $month;
}

function filterTripsByMonth($tripHistory, $yearMonth)
{
    $prefix = $yearMonth . '-';
    $filtered = [];

    foreach ($tripHistory as $trip) {
        $tripDate = (string) ($trip['dia_uso'] ?? '');
        if ($tripDate !== '' && strpos($tripDate, $prefix) === 0) {
            $filtered[] = $trip;
        }
    }

    return $filtered;
}

function finalizeUserActiveTrip($userId)
{
    $connection = createDatabaseConnection();

    if (!$connection) {
        return 'error_conexion';
    }

    $call = callProcedureSingleRow($connection, "CALL sp_finalizar_viaje_usuario(?)", 'i', [(int) $userId]);
    $connection->close();

    if (!$call['ok']) {
        return 'error_consulta';
    }

    return strtolower(trim((string) ($call['row']['Status'] ?? 'error_consulta')));
}

function parseAdminBulkInput($rawText)
{
    $rows = [];
    $invalid = 0;
    $lines = preg_split('/\r\n|\r|\n/', (string) $rawText);

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) !== 3) {
            $invalid++;
            continue;
        }

        $id = (int) $parts[0];
        $nombre = $parts[1];
        $campo = $parts[2];

        if ($id <= 0 || $nombre === '' || $campo === '') {
            $invalid++;
            continue;
        }

        $rows[] = [
            'id' => $id,
            'nombre' => substr($nombre, 0, 80),
            'campo' => substr($campo, 0, 80)
        ];
    }

    return ['rows' => $rows, 'invalid' => $invalid];
}

function buildAdminBulkStatus($statusKey, $inserted, $updated, $errors)
{
    return $statusKey . '&inserted=' . (int) $inserted . '&updated=' . (int) $updated . '&errors=' . (int) $errors;
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

    $currentUser = getCurrentUserFromSession();
    $isAdminUser = isCurrentUserAdmin($currentUser);

    if (isset($_POST['admin_bulk_alumnos'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $bulk = parseAdminBulkInput((string) ($_POST['bulk_alumnos_data'] ?? ''));
        $inserted = 0;
        $updated = 0;
        $errors = (int) $bulk['invalid'];

        foreach ($bulk['rows'] as $item) {
            $result = upsertAlumnoAdmin((int) $item['id'], (string) $item['nombre'], (string) $item['campo']);
            if ($result === 'insertado') {
                $inserted++;
            } elseif ($result === 'actualizado') {
                $updated++;
            } else {
                $errors++;
            }
        }

        $status = buildAdminBulkStatus('bulk_alumnos', $inserted, $updated, $errors);
        header('Location: index.php?action=admin&status=' . urlencode($status));
        exit;
    }

    if (isset($_POST['admin_create_admin_user'], $_POST['admin_user_name'], $_POST['admin_user_email'], $_POST['admin_user_password'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $name = trim((string) $_POST['admin_user_name']);
        $email = trim((string) $_POST['admin_user_email']);
        $password = (string) $_POST['admin_user_password'];

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            header('Location: index.php?action=admin&admin_section=usuarios&status=admin_user_invalido');
            exit;
        }

        $result = createAdministratorUserAccount($name, $email, $password);
        header('Location: index.php?action=admin&admin_section=usuarios&status=' . urlencode('admin_user_' . $result));
        exit;
    }

    if (isset($_POST['admin_bulk_maestros'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $bulk = parseAdminBulkInput((string) ($_POST['bulk_maestros_data'] ?? ''));
        $inserted = 0;
        $updated = 0;
        $errors = (int) $bulk['invalid'];

        foreach ($bulk['rows'] as $item) {
            $result = upsertMaestroAdmin((int) $item['id'], (string) $item['nombre'], (string) $item['campo']);
            if ($result === 'insertado') {
                $inserted++;
            } elseif ($result === 'actualizado') {
                $updated++;
            } else {
                $errors++;
            }
        }

        $status = buildAdminBulkStatus('bulk_maestros', $inserted, $updated, $errors);
        header('Location: index.php?action=admin&status=' . urlencode($status));
        exit;
    }

    if (isset($_POST['admin_update_alumno'], $_POST['alumno_id'], $_POST['alumno_nombre'], $_POST['alumno_carrera'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $id = (int) $_POST['alumno_id'];
        $nombre = trim((string) $_POST['alumno_nombre']);
        $carrera = trim((string) $_POST['alumno_carrera']);

        if ($id <= 0 || $nombre === '' || $carrera === '') {
            header('Location: index.php?action=admin&status=alumno_invalido');
            exit;
        }

        $result = upsertAlumnoAdmin($id, $nombre, $carrera);
        header('Location: index.php?action=admin&status=' . urlencode('alumno_' . $result));
        exit;
    }

    if (isset($_POST['admin_update_maestro'], $_POST['maestro_id'], $_POST['maestro_nombre'], $_POST['maestro_departamento'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $id = (int) $_POST['maestro_id'];
        $nombre = trim((string) $_POST['maestro_nombre']);
        $departamento = trim((string) $_POST['maestro_departamento']);

        if ($id <= 0 || $nombre === '' || $departamento === '') {
            header('Location: index.php?action=admin&status=maestro_invalido');
            exit;
        }

        $result = upsertMaestroAdmin($id, $nombre, $departamento);
        header('Location: index.php?action=admin&status=' . urlencode('maestro_' . $result));
        exit;
    }

    if (isset($_POST['admin_delete_alumno'], $_POST['alumno_id'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $result = deleteAlumnoAdmin((int) $_POST['alumno_id']);
        header('Location: index.php?action=admin&status=' . urlencode('alumno_' . $result));
        exit;
    }

    if (isset($_POST['admin_delete_maestro'], $_POST['maestro_id'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $result = deleteMaestroAdmin((int) $_POST['maestro_id']);
        header('Location: index.php?action=admin&status=' . urlencode('maestro_' . $result));
        exit;
    }

    if (isset($_POST['admin_create_bike'], $_POST['bike_modelo'], $_POST['bike_imagen_url'], $_POST['bike_comentarios'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $modelo = trim((string) $_POST['bike_modelo']);
        $imagenUrl = trim((string) $_POST['bike_imagen_url']);
        $comentarios = trim((string) $_POST['bike_comentarios']);

        if ($modelo === '' || $imagenUrl === '' || $comentarios === '') {
            header('Location: index.php?action=admin&status=bicicleta_invalida');
            exit;
        }

        $result = createBikeAdmin($modelo, $imagenUrl, $comentarios);
        header('Location: index.php?action=admin&status=' . urlencode('bicicleta_' . $result));
        exit;
    }

    if (isset($_POST['admin_update_bike'], $_POST['bike_id'], $_POST['bike_modelo'], $_POST['bike_estado'], $_POST['bike_imagen_url'], $_POST['bike_comentarios'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $bikeId = (int) $_POST['bike_id'];
        $modelo = trim((string) $_POST['bike_modelo']);
        $estado = trim((string) $_POST['bike_estado']);
        $imagenUrl = trim((string) $_POST['bike_imagen_url']);
        $comentarios = trim((string) $_POST['bike_comentarios']);

        if ($bikeId <= 0 || $modelo === '' || $imagenUrl === '' || $comentarios === '') {
            header('Location: index.php?action=admin&status=bicicleta_invalida');
            exit;
        }

        $result = updateBikeAdmin($bikeId, $modelo, $estado, $imagenUrl, $comentarios);
        header('Location: index.php?action=admin&status=' . urlencode('bicicleta_' . $result));
        exit;
    }

    if (isset($_POST['admin_delete_bike'], $_POST['bike_id'])) {
        if (!$isAdminUser) {
            header('Location: index.php?action=admin&status=no_autorizado');
            exit;
        }

        $result = deleteBikeAdmin((int) $_POST['bike_id']);
        header('Location: index.php?action=admin&status=' . urlencode('bicicleta_' . $result));
        exit;
    }

    if (isset($_POST['finish_trip'])) {
        if (!$currentUser) {
            header('Location: index.php?action=trip&status=login_requerido');
            exit;
        }

        $result = finalizeUserActiveTrip((int) $currentUser['id']);
        header('Location: index.php?action=trip&status=' . urlencode($result));
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

    if ($action === 'trip') {
        if ($status === 'finalizado') {
            return ['type' => 'success', 'text' => 'Viaje finalizado correctamente.'];
        }

        if ($status === 'sin_viaje_activo') {
            return ['type' => 'warning', 'text' => 'No tienes un viaje activo para finalizar.'];
        }

        if ($status === 'login_requerido') {
            return ['type' => 'danger', 'text' => 'Debes iniciar sesion para finalizar el viaje.'];
        }

        return ['type' => 'danger', 'text' => 'No fue posible finalizar el viaje.'];
    }

    if ($action === 'admin') {
        if ($status === 'no_autorizado') {
            return ['type' => 'danger', 'text' => 'No tienes permisos para acceder al panel de administracion.'];
        }

        if (strpos($status, 'bulk_alumnos') === 0) {
            parse_str(str_replace('&amp;', '&', $status), $parts);
            return ['type' => 'info', 'text' => 'Carga masiva de alumnos completada. Insertados: ' . (int) ($parts['inserted'] ?? 0) . ', actualizados: ' . (int) ($parts['updated'] ?? 0) . ', errores: ' . (int) ($parts['errors'] ?? 0) . '.'];
        }

        if (strpos($status, 'bulk_maestros') === 0) {
            parse_str(str_replace('&amp;', '&', $status), $parts);
            return ['type' => 'info', 'text' => 'Carga masiva de maestros completada. Insertados: ' . (int) ($parts['inserted'] ?? 0) . ', actualizados: ' . (int) ($parts['updated'] ?? 0) . ', errores: ' . (int) ($parts['errors'] ?? 0) . '.'];
        }

        if ($status === 'alumno_insertado' || $status === 'alumno_actualizado') {
            return ['type' => 'success', 'text' => 'Alumno guardado correctamente.'];
        }

        if ($status === 'maestro_insertado' || $status === 'maestro_actualizado') {
            return ['type' => 'success', 'text' => 'Maestro guardado correctamente.'];
        }

        if ($status === 'alumno_eliminado') {
            return ['type' => 'success', 'text' => 'Alumno eliminado correctamente.'];
        }

        if ($status === 'maestro_eliminado') {
            return ['type' => 'success', 'text' => 'Maestro eliminado correctamente.'];
        }

        if ($status === 'alumno_vinculado_usuario' || $status === 'maestro_vinculado_usuario') {
            return ['type' => 'warning', 'text' => 'No se puede eliminar porque ese registro ya esta vinculado a una cuenta de usuario.'];
        }

        if ($status === 'alumno_no_encontrado' || $status === 'maestro_no_encontrado') {
            return ['type' => 'warning', 'text' => 'Registro no encontrado.'];
        }

        if ($status === 'alumno_invalido' || $status === 'maestro_invalido') {
            return ['type' => 'danger', 'text' => 'Datos invalidos para guardar el registro.'];
        }

        if ($status === 'bicicleta_insertado' || $status === 'bicicleta_actualizado') {
            return ['type' => 'success', 'text' => 'Bicicleta guardada correctamente.'];
        }

        if ($status === 'bicicleta_eliminado') {
            return ['type' => 'success', 'text' => 'Bicicleta eliminada correctamente.'];
        }

        if ($status === 'bicicleta_con_historial') {
            return ['type' => 'warning', 'text' => 'No se puede eliminar la bicicleta porque tiene historial de viajes.'];
        }

        if ($status === 'bicicleta_invalida') {
            return ['type' => 'danger', 'text' => 'Datos invalidos para guardar la bicicleta.'];
        }

        if ($status === 'bicicleta_no_encontrado') {
            return ['type' => 'warning', 'text' => 'Bicicleta no encontrada.'];
        }

        if ($status === 'admin_user_ok') {
            return ['type' => 'success', 'text' => 'Administrador creado correctamente.'];
        }

        if ($status === 'admin_user_email_duplicado') {
            return ['type' => 'warning', 'text' => 'Ese correo ya esta registrado en el sistema.'];
        }

        if ($status === 'admin_user_invalido') {
            return ['type' => 'danger', 'text' => 'Datos invalidos para crear administrador.'];
        }

        if (strpos($status, 'alumno_error') === 0 || strpos($status, 'maestro_error') === 0 || strpos($status, 'bicicleta_error') === 0 || strpos($status, 'admin_user_error') === 0) {
            return ['type' => 'danger', 'text' => 'No fue posible completar la operacion solicitada.'];
        }
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
$activeLoan = $currentUser ? getActiveLoanByUserId($currentUser['id']) : null;
$isAdminUser = isCurrentUserAdmin($currentUser);
$currentAction = isset($_GET['action']) ? (string) $_GET['action'] : '';
$adminActiveSection = isset($_GET['admin_section']) ? (string) $_GET['admin_section'] : 'dashboard';

$showAdminPanel = $currentAction === 'admin' && $isAdminUser;
$adminSummary = $showAdminPanel ? getAdminDashboardSummary() : null;
$adminUsers = $showAdminPanel ? getAdminUsersList() : [];
$adminAlumnos = $showAdminPanel ? getAdminAlumnosList() : [];
$adminMaestros = $showAdminPanel ? getAdminMaestrosList() : [];
$adminBikes = $showAdminPanel ? getAdminBikesList() : [];
$adminTrips = $showAdminPanel ? getAdminTripHistory() : [];
$adminReportMonth = $showAdminPanel ? normalizeAdminReportMonth($_GET['admin_report_month'] ?? date('Y-m')) : date('Y-m');
$adminTripsMonthly = $showAdminPanel ? filterTripsByMonth($adminTrips, $adminReportMonth) : [];
$adminReports = $showAdminPanel ? buildAdminReportsData($adminSummary ?: [], $adminTripsMonthly) : [];

if ($currentAction === 'admin' && !$isAdminUser && $flashMessage === null) {
    $flashMessage = ['type' => 'danger', 'text' => 'No tienes permisos para acceder al panel de administracion.'];
}

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
