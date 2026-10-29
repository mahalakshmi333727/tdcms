<?php
declare(strict_types=1);

// Direct test of the AJAX handler
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Simulate a POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'assignment_id' => '4',
    'event' => 'copy_text',
    'csrf_token' => 'test'
];

// Override the json_response function to not exit
global $test_response;
$test_response = null;

function json_response($data, $code = 200) {
    global $test_response;
    $test_response = $data;
    echo json_encode($data) . "\n";
}

function require_method($method) {
    // Skip for testing
}

function csrf_protect() {
    // Skip for testing
}

function is_logged_in() {
    return true;
}

function current_user_id() {
    return 3; // RAM user
}

function client_ip() {
    return '127.0.0.1';
}

function rate_limit_check($pdo, $key, $max, $window) {
    return true;
}

// Load dependencies
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Assignment.php';
require_once __DIR__ . '/models/ActivityLog.php';
require_once __DIR__ . '/controllers/user_controller.php';

// Get DB connection
$pdo = Database::getConnection();

// Call the handler
try {
    handle_copy_assignment($pdo);
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
