<?php
require __DIR__ . '/config/database.php';

$connection = createDatabaseConnection();
if (!$connection) {
    echo "connfail\n";
    exit(1);
}

$statement = $connection->prepare("CALL sp_registrar_usuario_con_id(?, ?, ?, ?)");
if (!$statement) {
    echo "prep_error: " . $connection->error . "\n";
    exit(1);
}

$schoolId = 1001;
$name = 'Diag User';
$email = 'diag' . time() . '@example.com';
$passwordHash = password_hash('123456', PASSWORD_DEFAULT);

$statement->bind_param('isss', $schoolId, $name, $email, $passwordHash);

if (!$statement->execute()) {
    echo "exec_error: " . $statement->error . " | errno: " . $statement->errno . "\n";
    exit(1);
}

$result = $statement->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $result->free();
} else {
    echo "no_result_set\n";
}

$statement->close();
$connection->close();
