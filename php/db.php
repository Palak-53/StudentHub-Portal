<?php

declare(strict_types=1);

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $dbHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
    $dbName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub_practical8';
    $dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
    $dbPassword = getenv('STUDENTHUB_DB_PASSWORD');
    $dbPassword = $dbPassword === false ? '' : $dbPassword;

    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('[StudentHub] Database connection failed: ' . $exception->getMessage());
    throw new RuntimeException(
        'Could not connect to the database. Check that MySQL is running and the database settings are correct.'
    );
}
