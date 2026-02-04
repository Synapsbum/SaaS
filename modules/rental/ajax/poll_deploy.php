<?php
$rentalId = (int)($_GET['rental_id'] ?? 0);

$log = __DIR__."/../deploy_logs/rental_{$rentalId}.user.log";
$status = __DIR__."/../deploy_logs/rental_{$rentalId}.status";

echo json_encode([
    'log' => file_exists($log) ? file_get_contents($log) : '',
    'status' => file_exists($status) ? trim(file_get_contents($status)) : 'idle'
]);
