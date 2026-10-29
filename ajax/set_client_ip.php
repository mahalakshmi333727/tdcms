<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$ip = trim((string) ($input['ip'] ?? ''));

if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
    $_SESSION['client_public_ip'] = $ip;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}