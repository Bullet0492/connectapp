<?php
// Database configuratie - pas aan naar jouw server
define('DB_HOST', 'localhost');
define('DB_NAME', 'connect4it_connectapp');
define('DB_USER', 'connect4it_connectapp');
define('DB_PASS', 'gwF7YCHnbdD9SvJkp55P');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Databaseverbinding mislukt: ' . $e->getMessage());
        }
    }
    return $pdo;
}
