<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        Security::headers();
    }
    
    // Kayıt
    public function register($username, $password, $telegram = null, $email = null) {
        // Validasyon
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return ['success' => false, 'message' => 'Kullanıcı adı 3-20 karakter, sadece harf/rakam/altçizgi'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Şifre en az 8 karakter olmalı'];
        }
        
        // Kontrol
        $exists = $this->db->fetch("SELECT id FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)", 
            [$username, $email]);
        if ($exists) {
            return ['success' => false, 'message' => 'Bu kullanıcı adı veya email zaten kullanımda'];
        }
        
        try {
            $userId = $this->db->insert('users', [
                'username' => $username,
                'password_hash' => Security::hashPassword($password),
                'telegram_username' => $telegram ? Security::clean($telegram) : null,
                'email' => $email ? Security::clean($email) : null,
                'login_ip' => Security::getIP(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return ['success' => true, 'user_id' => $userId, 'message' => 'Kayıt başarılı'];
        } catch (Exception $e) {
            error_log("Register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Kayıt sırasında hata oluştu'];
        }
    }
    
    // Giriş
    public function login($username, $password, $remember = false) {
        $user = $this->db->fetch("SELECT * FROM users WHERE username = ? AND status = 'active'", [$username]);
        
        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı'];
        }
        
        // Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['login_time'] = time();
        
        // Güncelle
        $this->db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'login_ip' => Security::getIP()
        ], 'id = ?', [$user['id']]);
        
        // Remember me
        if ($remember) {
            $token = Security::random();
            setcookie('remember', $token . ':' . $user['id'], time() + (30 * 86400), '/', '', false, true);
        }
        
        return ['success' => true, 'user' => $user];
    }
    
    // Çıkış
    public function logout() {
        session_destroy();
        setcookie('remember', '', time() - 3600, '/');
        return true;
    }
    
    // Kontrol
    public function check() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    // Kullanıcı bilgisi
    public function user() {
        if (!$this->check()) return null;
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }
    
    public function userId() {
        return $_SESSION['user_id'] ?? null;
    }
}
?>