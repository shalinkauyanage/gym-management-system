<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/app.php';

$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'powerfit_platform';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="font-family:Arial;padding:30px"><h2>PowerFit database connection failed</h2><p>Check <code>config/database.php</code>, start WAMP services, and import the SQL files in the README.</p></div>');
}
