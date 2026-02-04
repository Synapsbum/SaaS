<?php
// Oxapay Callback - v1 API Uyumlu
$logFile = __DIR__ . '/oxapay_callback.log';
$timestamp = date('Y-m-d H:i:s');

// Gelen veriyi al (JSON veya FORM)
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: $_POST;

// Log yaz
$log = "[$timestamp] CALLBACK\n";
$log .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log .= "Raw: " . $input . "\n";
$log .= "Parsed: " . print_r($data, true) . "\n";

// Track ID'yi bul
$trackId = $data['trackId'] ?? $data['track_id'] ?? null;
$status = $data['status'] ?? $data['payment_status'] ?? '';
$amount = $data['amount'] ?? $data['pay_amount'] ?? 0;

$log .= "Extracted: trackId=$trackId, status=$status, amount=$amount\n";

if (empty($trackId)) {
    $log .= "ERROR: TrackId bulunamadi!\n";
    file_put_contents($logFile, $log . "---\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'TrackId required']);
    exit;
}

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    
    $db = Database::getInstance();
    
    // Ödemeyi bul
    $payment = $db->fetch(
        "SELECT * FROM payments WHERE cryptomus_uuid = ? AND status = 'pending'", 
        [$trackId]
    );
    
    if (!$payment) {
        // track_id ile de dene
        $payment = $db->fetch(
            "SELECT * FROM payments WHERE cryptomus_order_id LIKE ? AND status = 'pending'",
            ['%' . $trackId . '%']
        );
    }
    
    if (!$payment) {
        $log .= "UYARI: Odeme bulunamadi (trackId: $trackId)\n";
        file_put_contents($logFile, $log . "---\n", FILE_APPEND);
        echo json_encode(['status' => 'success', 'message' => 'Processed']);
        exit;
    }
    
    $log .= "Odeme bulundu: ID " . $payment['id'] . "\n";
    
    // Status kontrolü
    if (strtolower($status) === 'paid' || strtolower($status) === 'paying') {
        $db->beginTransaction();
        
        $db->update('payments', [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
            'callback_data' => json_encode($data) // BU KOLON YOKSA SQL HATASI VERIR
        ], 'id = ?', [$payment['id']]);
        
        $db->query(
            "UPDATE users SET balance = balance + ? WHERE id = ?",
            [$payment['amount_usdt'], $payment['user_id']]
        );
        
        $db->commit();
        
        $log .= "BAŞARILI: Bakiye eklendi!\n";
    } else {
        $log .= "Durum: $status (islem yapilmadi)\n";
    }
    
    file_put_contents($logFile, $log . "---\n", FILE_APPEND);
    echo json_encode(['status' => 'success', 'message' => 'OK']);
    
} catch (Exception $e) {
    $log .= "HATA: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $log . "---\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>