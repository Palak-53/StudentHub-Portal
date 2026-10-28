<?php

declare(strict_types=1);

$dbHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub_practical8';
$dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
$dbPassword = getenv('STUDENTHUB_DB_PASSWORD');
$dbPassword = $dbPassword === false ? '' : $dbPassword;

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('[StudentHub] PDO database connection failed: ' . $exception->getMessage());
    throw new RuntimeException(
        'Could not connect to the database. Check that MySQL is running and the database settings are correct.'
    );
}
