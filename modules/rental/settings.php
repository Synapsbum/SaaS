<?php
$title = 'Script Ayarları';
$user = $auth->user();
$db = Database::getInstance();

// ID'yi doğru şekilde al
$rentalId = isset($id) ? (int)$id : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($rentalId <= 0) {
    $_SESSION['error'] = 'Geçersiz kiralama ID';
    Helper::redirect('rental');
    exit;
}

// Rental kontrolü
$rental = $db->fetch("
    SELECT r.*, s.name as script_name, d.domain
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$rental) {
    Helper::redirect('rental');
    exit;
}

// Ayarları getir
function getSetting($db, $rentalId, $key, $default = '') {
    $result = $db->fetch("
        SELECT setting_value FROM rental_settings 
        WHERE rental_id = ? AND setting_key = ?
    ", [$rentalId, $key]);
    return $result ? $result['setting_value'] : $default;
}

function saveSetting($db, $rentalId, $key, $value) {
    $db->query("
        INSERT INTO rental_settings (rental_id, setting_key, setting_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ", [$rentalId, $key, $value]);
}

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        try {
            saveSetting($db, $rentalId, 'tawkto_id', trim($_POST['tawkto_id'] ?? ''));
            saveSetting($db, $rentalId, 'withdrawal_limit', trim($_POST['withdrawal_limit'] ?? ''));
            saveSetting($db, $rentalId, 'contact_email', trim($_POST['contact_email'] ?? ''));
            saveSetting($db, $rentalId, 'whatsapp_number', trim($_POST['whatsapp_number'] ?? ''));
            saveSetting($db, $rentalId, 'telegram_username', trim($_POST['telegram_username'] ?? ''));
            saveSetting($db, $rentalId, 'min_deposit', trim($_POST['min_deposit'] ?? '0'));
            saveSetting($db, $rentalId, 'max_deposit', trim($_POST['max_deposit'] ?? '0'));
            saveSetting($db, $rentalId, 'bonus_rate', trim($_POST['bonus_rate'] ?? '0'));
            saveSetting($db, $rentalId, 'maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
            saveSetting($db, $rentalId, 'registration_open', isset($_POST['registration_open']) ? '1' : '0');
            
            $_SESSION['success'] = 'Ayarlar başarıyla kaydedildi!';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Bir hata oluştu: ' . $e->getMessage();
        }
        
        Helper::redirect('rental/manage/' . $rentalId . '/settings');
        exit;
    }
}

// Mevcut ayarları al
$settings = [
    'tawkto_id' => getSetting($db, $rentalId, 'tawkto_id'),
    'withdrawal_limit' => getSetting($db, $rentalId, 'withdrawal_limit'),
    'contact_email' => getSetting($db, $rentalId, 'contact_email'),
    'whatsapp_number' => getSetting($db, $rentalId, 'whatsapp_number'),
    'telegram_username' => getSetting($db, $rentalId, 'telegram_username'),
    'min_deposit' => getSetting($db, $rentalId, 'min_deposit', '0'),
    'max_deposit' => getSetting($db, $rentalId, 'max_deposit', '0'),
    'bonus_rate' => getSetting($db, $rentalId, 'bonus_rate', '0'),
    'maintenance_mode' => getSetting($db, $rentalId, 'maintenance_mode', '0'),
    'registration_open' => getSetting($db, $rentalId, 'registration_open', '1')
];

require 'templates/header.php';
?>

<style>
.page-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 24px 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.settings-section {
    background: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 24px;
    transition: all 0.3s;
}

.settings-section:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
}

.section-title h6 {
    font-size: 18px;
    font-weight: 700;
    color: white;
    margin: 0;
    flex: 1;
}

.section-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--primary);
}

.integration-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.integration-badge.active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.integration-badge.inactive {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}

.form-group {
    margin-bottom: 24px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.form-group input,
.form-group textarea {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 12px 16px;
    color: white;
    width: 100%;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(0,0,0,0.4);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 6px;
    display: block;
}

.switch-container {
    padding: 20px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    transition: all 0.3s;
    margin-bottom: 16px;
}

.switch-container:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.3);
}

.switch-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.switch-toggle {
    position: relative;
    width: 52px;
    height: 28px;
    flex-shrink: 0;
}

.switch-toggle input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(107, 114, 128, 0.3);
    transition: .4s;
    border-radius: 28px;
}

.switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.switch-toggle input:checked + .switch-slider {
    background-color: var(--primary);
}

.switch-toggle input:checked + .switch-slider:before {
    transform: translateX(24px);
}

.switch-content {
    flex: 1;
}

.switch-content strong {
    display: block;
    color: white;
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 6px;
}

.switch-content small {
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.action-buttons .btn {
    padding: 14px 32px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.3s;
}

.action-buttons .btn-primary {
    flex: 1;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border: none;
}

.action-buttons .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
}

.action-buttons .btn-outline-secondary {
    min-width: 120px;
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 style="margin: 0 0 8px 0; color: white;"><i class="bi bi-gear me-2"></i>Script Ayarları</h4>
            <div style="color: var(--text-secondary); font-size: 14px;">
                <i class="bi bi-box-seam me-1"></i>
                <?php echo htmlspecialchars($rental['script_name']); ?> 
                <span style="opacity: 0.5; margin: 0 8px;">•</span>
                <i class="bi bi-globe me-1"></i>
                <?php echo htmlspecialchars($rental['domain']); ?>
            </div>
        </div>
        <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Geri Dön
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); border-radius: 12px; margin-bottom: 24px;">
        <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger" style="background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); color: #ff4444; border-radius: 12px; margin-bottom: 24px;">
        <i class="bi bi-x-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="save_settings">
        
        <!-- Genel Ayarlar -->
        <div class="settings-section">
            <div class="section-title">
                <div class="section-icon"><i class="bi bi-globe"></i></div>
                <h6>Genel Ayarlar</h6>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="switch-container">
                        <div class="switch-wrapper">
                            <label class="switch-toggle">
                                <input type="checkbox" name="maintenance_mode" 
                                       id="maintenanceMode" <?php echo $settings['maintenance_mode'] === '1' ? 'checked' : ''; ?>>
                                <span class="switch-slider"></span>
                            </label>
                            <div class="switch-content">
                                <strong>Bakım Modu</strong>
                                <small>Aktif olduğunda siteniz ziyaretçilere kapalı olur</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="switch-container">
                        <div class="switch-wrapper">
                            <label class="switch-toggle">
                                <input type="checkbox" name="registration_open" 
                                       id="registrationOpen" <?php echo $settings['registration_open'] === '1' ? 'checked' : ''; ?>>
                                <span class="switch-slider"></span>
                            </label>
                            <div class="switch-content">
                                <strong>Kayıt Açık</strong>
                                <small>Yeni kullanıcı kayıtlarını aç/kapa</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Canlı Destek -->
        <div class="settings-section">
            <div class="section-title">
                <div class="section-icon"><i class="bi bi-chat-dots"></i></div>
                <h6>Canlı Destek (Tawk.to)</h6>
                <?php if ($settings['tawkto_id']): ?>
                <span class="integration-badge active">
                    <i class="bi bi-check-circle"></i>Aktif
                </span>
                <?php else: ?>
                <span class="integration-badge inactive">
                    <i class="bi bi-x-circle"></i>Pasif
                </span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Tawk.to Property ID</label>
                <input type="text" name="tawkto_id" 
                       value="<?php echo htmlspecialchars($settings['tawkto_id']); ?>" 
                       placeholder="örn: 5f123456789abcdef">
                <small>
                    <i class="bi bi-info-circle me-1"></i>
                    Tawk.to hesabınızdan Property ID'nizi alın. 
                    <a href="https://www.tawk.to/" target="_blank" style="color: var(--primary);">Tawk.to'ya git</a>
                </small>
            </div>
        </div>
        
        <!-- İletişim Bilgileri -->
        <div class="settings-section">
            <div class="section-title">
                <div class="section-icon"><i class="bi bi-envelope"></i></div>
                <h6>İletişim Bilgileri</h6>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>E-posta Adresi</label>
                        <input type="email" name="contact_email" 
                               value="<?php echo htmlspecialchars($settings['contact_email']); ?>" 
                               placeholder="destek@example.com">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>WhatsApp Numarası</label>
                        <input type="text" name="whatsapp_number" 
                               value="<?php echo htmlspecialchars($settings['whatsapp_number']); ?>" 
                               placeholder="+90 5XX XXX XX XX">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Telegram Kullanıcı Adı</label>
                <input type="text" name="telegram_username" 
                       value="<?php echo htmlspecialchars($settings['telegram_username']); ?>" 
                       placeholder="@username">
                <small><i class="bi bi-info-circle me-1"></i>@ işareti ile birlikte yazın</small>
            </div>
        </div>
        
        <!-- Ödeme Ayarları -->
        <div class="settings-section">
            <div class="section-title">
                <div class="section-icon"><i class="bi bi-cash-coin"></i></div>
                <h6>Ödeme Ayarları</h6>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Minimum Yatırım (₺)</label>
                        <input type="number" name="min_deposit" 
                               value="<?php echo htmlspecialchars($settings['min_deposit']); ?>" 
                               min="0" step="1" placeholder="0">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Maksimum Yatırım (₺)</label>
                        <input type="number" name="max_deposit" 
                               value="<?php echo htmlspecialchars($settings['max_deposit']); ?>" 
                               min="0" step="1" placeholder="0">
                        <small><i class="bi bi-info-circle me-1"></i>0 = sınırsız</small>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Bonus Oranı (%)</label>
                        <input type="number" name="bonus_rate" 
                               value="<?php echo htmlspecialchars($settings['bonus_rate']); ?>" 
                               min="0" max="100" step="0.1" placeholder="0">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Çekim Limiti (₺)</label>
                <input type="number" name="withdrawal_limit" 
                       value="<?php echo htmlspecialchars($settings['withdrawal_limit']); ?>" 
                       min="0" step="1" placeholder="0">
                <small><i class="bi bi-info-circle me-1"></i>Günlük çekim limiti (0 = sınırsız)</small>
            </div>
        </div>
        
        <!-- Kaydet Butonu -->
        <div class="action-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-2"></i>Ayarları Kaydet
            </button>
            <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-secondary">
                İptal
            </a>
        </div>
    </form>
</div>

<?php require 'templates/footer.php'; ?>