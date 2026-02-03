<?php
class Helper {
    
    // Para formatı
    public static function money($amount, $currency = 'USDT') {
        return number_format($amount, 2) . ' ' . $currency;
    }
    
    // Tarih formatı
    public static function date($date, $format = 'd.m.Y H:i') {
        return date($format, strtotime($date));
    }
    
    // Kalan süre
    public static function remaining($expiresAt) {
        $diff = strtotime($expiresAt) - time();
        if ($diff <= 0) return 'Süre doldu';
        
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        
        if ($days > 0) return $days . ' gün ' . $hours . ' saat';
        return $hours . ' saat';
    }
    
    // Durum badge
    public static function statusBadge($status) {
        $classes = [
            'active' => 'success',
            'pending' => 'warning',
            'expired' => 'danger',
            'cancelled' => 'secondary',
            'setup_domain' => 'info',
            'setup_config' => 'info',
            'setup_deploy' => 'primary'
        ];
        $labels = [
            'active' => 'Aktif',
            'pending' => 'Bekliyor',
            'expired' => 'Süresi Doldu',
            'cancelled' => 'İptal',
            'setup_domain' => 'Domain Seçimi',
            'setup_config' => 'Yapılandırma',
            'setup_deploy' => 'Kurulum'
        ];
        
        $class = $classes[$status] ?? 'secondary';
        $label = $labels[$status] ?? $status;
        
        return '<span class="badge bg-' . $class . '">' . $label . '</span>';
    }
    
    // Kısa metin
    public static function excerpt($text, $length = 100) {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
    }
    
    // URL oluştur
    public static function url($path = '') {
        return SITE_URL . BASE_PATH . ltrim($path, '/');
    }
    
    // Redirect
    public static function redirect($path) {
        header('Location: ' . self::url($path));
        exit;
    }
    
    // Flash mesaj
    public static function flash($key, $value = null) {
        if ($value !== null) {
            $_SESSION['flash_' . $key] = $value;
            return true;
        }
        
        if (isset($_SESSION['flash_' . $key])) {
            $value = $_SESSION['flash_' . $key];
            unset($_SESSION['flash_' . $key]);
            return $value;
        }
        return null;
    }
}
?>