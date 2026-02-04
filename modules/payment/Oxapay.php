<?php
class OxapayPayment {
    private $apiKey;
    private $baseUrl = 'https://api.oxapay.com/v1'; // Boşluk yok!
    private $db;
    private $isSandbox;
    
    public function __construct() {
        if (!defined('OXAPAY_API_KEY')) {
            throw new Exception("OXAPAY_API_KEY tanımlanmamış");
        }
        $this->apiKey = trim(OXAPAY_API_KEY);
        $this->isSandbox = defined('OXAPAY_SANDBOX') ? OXAPAY_SANDBOX : false;
        $this->db = Database::getInstance();
    }
    
    /**
     * Yeni fatura oluştur (v1 API)
     */
    public function createPayment($userId, $amount, $returnUrl = null, $callbackUrl = null) {
        try {
            $orderId = 'DEP_' . $userId . '_' . time();
            
            $data = [
                'amount' => (float)$amount,
                'currency' => 'USD',
                'to_currency' => 'USDT',
                'order_id' => $orderId,
                'description' => 'Bakiye #' . $userId,
                'lifetime' => 30,
                'fee_paid_by_payer' => 1,
                'under_paid_coverage' => 2.5,
                'mixed_payment' => true,
                'return_url' => $returnUrl ?: SITE_URL . '/payment/success',
                'callback_url' => $callbackUrl ?: 'https://tokat.bet/callback.php',
                'thanks_message' => 'Odemeniz alindi!',
                'sandbox' => $this->isSandbox
            ];
            
            $result = $this->makeRequest('/payment/invoice', $data);
            
            // Yeni API yapısı: data içinde
            if (isset($result['status']) && $result['status'] == 200 && isset($result['data'])) {
                $paymentData = $result['data'];
                
                $this->db->insert('payments', [
                    'user_id' => $userId,
                    'amount_usdt' => $amount,
                    'cryptomus_uuid' => $paymentData['track_id'], // track_id içinde
                    'cryptomus_order_id' => $orderId,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => true,
                    'payment_url' => $paymentData['payment_url'], // data içinde
                    'trackId' => $paymentData['track_id']
                ];
            }
            
            return [
                'success' => false, 
                'message' => $result['message'] ?? 'API Hatası'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Callback işleme
     */
    public function handleCallback($data) { // Parametre eklendi!
        $input = file_get_contents('php://input');
        
        if (empty($data)) {
            $data = json_decode($input, true) ?: $_POST;
        }
        
        error_log("OXAPAY CALLBACK: " . json_encode($data));
        
        if (!$data || !isset($data['trackId'])) {
            return ['success' => false, 'message' => 'Invalid data'];
        }
        
        $trackId = $data['trackId'];
        $status = $data['status'] ?? '';
        
        $payment = $this->db->fetch(
            "SELECT * FROM payments WHERE cryptomus_uuid = ? AND status = 'pending'", 
            [$trackId]
        );
        
        if (!$payment) {
            error_log("OXAPAY: Payment not found for trackId: $trackId");
            return ['success' => false, 'message' => 'Payment not found'];
        }
        
        if (strtolower($status) === 'paid') {
            try {
                $this->db->beginTransaction();
                
                $this->db->update('payments', [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'callback_data' => json_encode($data)
                ], 'id = ?', [$payment['id']]);
                
                $this->db->query(
                    "UPDATE users SET balance = balance + ? WHERE id = ?", 
                    [$payment['amount_usdt'], $payment['user_id']]
                );
                
                $this->db->commit();
                
                error_log("OXAPAY: Payment confirmed for user " . $payment['user_id']);
                return ['success' => true, 'message' => 'Payment confirmed'];
                
            } catch (Exception $e) {
                $this->db->rollback();
                error_log("OXAPAY DB Error: " . $e->getMessage());
                return ['success' => false, 'message' => 'DB Error'];
            }
        } elseif (in_array(strtolower($status), ['expired', 'failed', 'canceled'])) {
            $this->db->update('payments', [
                'status' => strtolower($status),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$payment['id']]);
            
            return ['success' => true, 'message' => 'Status: ' . $status];
        }
        
        return ['success' => true, 'message' => 'Status: ' . $status];
    }
    
    /**
     * API isteği gönder
     */
    private function makeRequest($endpoint, $data) {
        $url = $this->baseUrl . $endpoint; // Boşluk yok!
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'merchant_api_key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // HTTP kodunu al
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode . " - " . $response);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Error: " . json_last_error_msg());
        }
        
        return $result;
    }
}
?>