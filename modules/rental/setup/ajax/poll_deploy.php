<?php
/**
 * AJAX: Kurulum loglarını ve status’u döner
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/index.php';

if (!$auth->check()) {
    http_response_code(401);
    exit;
}

$rentalId = (int)($_GET['rental_id'] ?? 0);
if ($rentalId <= 0) {
    http_response_code(400);
    exit;
}

$logDir = __DIR__ . '/../deploy_logs';

$logFile    = $logDir . "/rental_{$rentalId}.log";
$statusFile = $logDir . "/rental_{$rentalId}.status";

$log = file_exists($logFile)
    ? file_get_contents($logFile)
    : '';

$status = file_exists($statusFile)
    ? trim(file_get_contents($statusFile))
    : 'running';

/* DB’den de kontrol (ek güvenlik) */
$r = $db->fetch(
    "SELECT status FROM rentals WHERE id = ? AND user_id = ?",
    [$rentalId, $auth->user()['id']]
);

if ($r && $r['status'] === 'active') {
    $status = 'success';
}

header('Content-Type: application/json');
echo json_encode([
    'log'    => $log,
    'status' => $status
]);
