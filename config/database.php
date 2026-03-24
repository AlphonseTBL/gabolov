<?php

function createDatabaseConnection()
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $name = getenv('DB_NAME') ?: 'gabicpro';
    $port = (int) (getenv('DB_PORT') ?: 3306);

    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = @new mysqli($host, $user, $pass, $name, $port);

    if ($connection->connect_errno) {
        return null;
    }

    $connection->set_charset('utf8mb4');

    return $connection;
}
