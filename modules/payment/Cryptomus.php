<?php
class CryptomusPayment {
    private $apiKey;
    private $merchantId;
    private $apiUrl = 'https://api.cryptomus.com/v1';
    private $db;
    
    public function __construct() {
        $this->apiKey = CRYPTOMUS_API_KEY;
        $this->merchantId = CRYPTOMUS_MERCHANT_ID;
        $this->db = Database::getInstance();
    }
    
    public function createPayment($userId, $amount, $returnUrl = null, $callbackUrl = null) {
        try {
            $orderId = 'DEP_' . $userId . '_' . time() . '_' . substr(md5(uniqid()), 0, 8);
            
            $data = [
                'amount' => (string)$amount,
                'currency' => 'USD',
                'to_currency' => 'USDT',
                'order_id' => $orderId,
                'url_return' => $returnUrl ?: SITE_URL . '/payment/return',
                'url_callback' => $callbackUrl ?: SITE_URL . '/payment/callback',
                'is_payment_multiple' => false,
                'lifetime' => '3600',
                'network' => 'TRON'
            ];
            
            $result = $this->makeRequest('/payment', $data);
            
            if (isset($result['uuid'])) {
                $this->db->insert('payments', [
                    'user_id' => $userId,
                    'amount_usdt' => $amount,
                    'cryptomus_uuid' => $result['uuid'],
                    'cryptomus_order_id' => $orderId,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => true,
                    'payment_url' => $result['url'],
                    'uuid' => $result['uuid']
                ];
            }
            
            return ['success' => false, 'message' => 'Ödeme oluşturulamadı'];
            
        } catch (Exception $e) {
            error_log("Cryptomus Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'API hatası: ' . $e->getMessage()];
        }
    }
    
    public function handleCallback($postData) {
        $sign = $_SERVER['HTTP_SIGN'] ?? '';
        $jsonData = json_encode($postData);
        $calculatedSign = md5(base64_encode($jsonData) . $this->apiKey);
        
        if (!hash_equals($calculatedSign, $sign)) {
            error_log("Cryptomus: Invalid signature");
            return ['success' => false, 'message' => 'Invalid signature'];
        }
        
        $uuid = $postData['uuid'] ?? '';
        $status = $postData['status'] ?? '';
        $amount = $postData['payment_amount'] ?? $postData['amount'] ?? 0;
        
        $payment = $this->db->fetch("SELECT * FROM payments WHERE cryptomus_uuid = ? AND status = 'pending'", [$uuid]);
        
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }
        
        if ($status === 'paid' || $status === 'paid_over') {
            try {
                $this->db->beginTransaction();
                
                $this->db->update('payments', [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$payment['id']]);
                
                $this->db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $payment['user_id']]);
                
                $this->db->commit();
                
                // Bildirim gönder
                $this->notifyUser($payment['user_id'], $amount);
                
                return ['success' => true, 'message' => 'Payment processed'];
                
            } catch (Exception $e) {
                $this->db->rollback();
                error_log("Payment processing error: " . $e->getMessage());
                return ['success' => false, 'message' => 'Processing error'];
            }
        }
        
        return ['success' => true, 'message' => 'Status: ' . $status];
    }
    
    private function makeRequest($endpoint, $data = [], $method = 'POST') {
        $url = $this->apiUrl . $endpoint;
        $jsonData = json_encode($data);
        $sign = md5(base64_encode($jsonData) . $this->apiKey);
        
        $headers = [
            'Content-Type: application/json',
            'Merchant: ' . $this->merchantId,
            'Sign: ' . $sign
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['state']) && $result['state'] !== 0) {
            throw new Exception($result['message'] ?? 'API Error');
        }
        
        return $result['result'] ?? $result;
    }
    
    private function notifyUser($userId, $amount) {
        $user = $this->db->fetch("SELECT telegram_username FROM users WHERE id = ?", [$userId]);
        
        // Telegram bildirimi varsa gönder
        if ($user['telegram_username']) {
            $message = "💰 *Bakiye Yüklendi!*\\n\\nMiktar: " . number_format($amount, 2) . " USDT\\nTarih: " . date('d.m.Y H:i');
            // Telegram bot API entegrasyonu buraya
        }
    }
}
?>