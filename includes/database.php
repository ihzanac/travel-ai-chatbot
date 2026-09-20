<?php
/**
 * Shared mysqli connection for the app (database layer only).
 */

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

require_once PROJECT_ROOT . '/config/database.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$dbAvailable = false;
$dbWarning = '';
$conn = null;

if (!class_exists('mysqli')) {
    $dbWarning = 'PHP mysqli extension is not enabled. Running without chat history storage.';
} else {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $dbWarning = 'Database connection failed. Running without chat history storage.';
        $conn = null;
    } else {
        $conn->set_charset('utf8mb4');
        $dbAvailable = true;
    }
}
