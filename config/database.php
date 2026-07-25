<?php

$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        putenv(trim($line));
    }
} else {
    die('FATAL ERROR: .env file not found. Please create a .env file from .env.example');
}

function getDbConnection(): PDO
{
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');

    // Validate required environment variables
    if (!$host || !$port || !$dbname || !$username || $password === false) {
        die('FATAL ERROR: Missing required database environment variables in .env file. '
            . 'Required: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD');
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    try {
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die('FATAL ERROR: Database connection failed. Check your database credentials and configuration.');
    }
}
