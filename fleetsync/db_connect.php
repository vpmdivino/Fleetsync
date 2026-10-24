<?php
// db_connect.php

$host    = 'localhost';
$db      = 'fleet';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// Data Source Name (DSN) tells PDO which driver, host, and database to use
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Crucial configuration options for security and usability
$options = [
    // 1. Throws exceptions on SQL errors (critical for debugging and try/catch)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // 2. Fetches rows as associative arrays by default (e.g., $row['column_name'])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // 3. Disables emulated prepared statements to ensure genuine SQL injection protection
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // echo "Connected successfully!"; // Uncomment to test your connection
} catch (\PDOException $e) {
    // If connection fails, log the error and stop execution.
    // This is a critical error.
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] CRITICAL: Database connection failed. " . $e->getMessage() . PHP_EOL;
    
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents($log_dir . '/error.log', $log_message, FILE_APPEND);
    
    // Display a user-friendly error page and stop script execution
    http_response_code(500);
    die("A critical error occurred. Could not connect to the database. Please check the system logs.");
}

/**
 * Logs detailed error information to a file.
 * @param Throwable $e The exception or error object.
 */
function log_error(Throwable $e) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        // Attempt to create the directory
        @mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    
    $error_message = "[$timestamp] " . get_class($e) . ": " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
    if (!empty($_REQUEST)) {
        $error_message .= "Request Data: " . print_r($_REQUEST, true) . PHP_EOL;
    }
    $error_message .= "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
    $error_message .= "----------------------------------------------------------------------" . PHP_EOL;

    @file_put_contents($log_file, $error_message, FILE_APPEND);
}

/**
 * Logs a system event to the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $action The description of the action performed.
 */
function log_system_event(PDO $pdo, string $action) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $actor_name = $_SESSION['user_name'] ?? 'System';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        $sql = "INSERT INTO system_logs (user_id, actor_name, action, ip_address) VALUES (:user_id, :actor_name, :action, :ip_address)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':actor_name' => $actor_name,
            ':action' => $action,
            ':ip_address' => $ip_address
        ]);
    } catch (PDOException $e) {
        // Log the failure to log, but don't halt the main script execution.
        log_error($e);
    }
}