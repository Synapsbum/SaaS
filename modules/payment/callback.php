<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/Cryptomus.php';

// IP kısıtlaması (Cryptomus IP'leri)
$allowedIps = ['168.119.157.136', '168.119.60.227'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIps)) {
    http_response_code(403);
    die('Forbidden');
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    die('Invalid data');
}

$cryptomus = new CryptomusPayment();
$result = $cryptomus->handleCallback($data);

if ($result['success']) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $result['message']]);
}
?>