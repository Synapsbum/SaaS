<?php
$title = 'Script Ayarları';
$user = $auth->user();
$db = Database::getInstance();

$rentalId = (int)$id;

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
            // Tawk.to ID
            $tawktoId = trim($_POST['tawkto_id'] ?? '');
            saveSetting($db, $rentalId, 'tawkto_id', $tawktoId);
            
            // Çekim limiti
            $withdrawalLimit = trim($_POST['withdrawal_limit'] ?? '');
            saveSetting($db, $rentalId, 'withdrawal_limit', $withdrawalLimit);
            
            // Site başlığı
            $siteTitle = trim($_POST['site_title'] ?? '');
            saveSetting($db, $rentalId, 'site_title', $siteTitle);
            
            // Site açıklaması
            $siteDescription = trim($_POST['site_description'] ?? '');
            saveSetting($db, $rentalId, 'site_description', $siteDescription);
            
            // Email adresi
            $contactEmail = trim($_POST['contact_email'] ?? '');
            saveSetting($db, $rentalId, 'contact_email', $contactEmail);
            
            // WhatsApp numarası
            $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');
            saveSetting($db, $rentalId, 'whatsapp_number', $whatsappNumber);
            
            // Telegram kullanıcı adı
            $telegramUsername = trim($_POST['telegram_username'] ?? '');
            saveSetting($db, $rentalId, 'telegram_username', $telegramUsername);
            
            // Minimum yatırım tutarı
            $minDeposit = trim($_POST['min_deposit'] ?? '0');
            saveSetting($db, $rentalId, 'min_deposit', $minDeposit);
            
            // Maksimum yatırım tutarı
            $maxDeposit = trim($_POST['max_deposit'] ?? '0');
            saveSetting($db, $rentalId, 'max_deposit', $maxDeposit);
            
            // Bonus oranı
            $bonusRate = trim($_POST['bonus_rate'] ?? '0');
            saveSetting($db, $rentalId, 'bonus_rate', $bonusRate);
            
            // Bakım modu
            $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
            saveSetting($db, $rentalId, 'maintenance_mode', $maintenanceMode);
            
            // Kayıt açık/kapalı
            $registrationOpen = isset($_POST['registration_open']) ? '1' : '0';
            saveSetting($db, $rentalId, 'registration_open', $registrationOpen);
            
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
    'site_title' => getSetting($db, $rentalId, 'site_title'),
    'site_description' => getSetting($db, $rentalId, 'site_description'),
    'contact_email' => getSetting($db, $rentalId, 'contact_email'),
    'whatsapp_number' => getSetting($db, $rentalId, 'whatsapp_number'),
    'telegram_username' => getSetting($db, $rentalId, 'telegram_username'),
    'min_deposit' => getSetting($db, $rentalId, 'min_deposit', '0'),
    'max_deposit' => getSetting($db, $rentalId, 'max_deposit', '0'),
    'bonus_rate' => getSetting($db, $rentalId, 'bonus_rate', '0'),
    'maintenance_mode' => getSetting($db, $rentalId, 'maintenance_mode', '0'),
    'registration_open' => getSetting($db, $rentalId, 'registration_open', '1')
];

require 'templates/header_new.php';
?>

<style>
.settings-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
}

.settings-section h6 {
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(99, 102, 241, 0.1);
}

.setting-row {
    margin-bottom: 20px;
}

.setting-row:last-child {
    margin-bottom: 0;
}

.form-switch {
    padding-left: 2.5rem;
}

.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
}

.help-text {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 5px;
}

.code-snippet {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    margin-top: 10px;
}

.integration-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.integration-badge.inactive {
    background: linear-gradient(135deg, #6b7280, #4b5563);
}
</style>

<div class="mb-4">
    <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-2"></i>Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-gear me-2"></i>Script Ayarları</h5>
        <div>
            <span style="color: var(--text-muted); font-size: 14px;">
                <?php echo htmlspecialchars($rental['script_name']); ?> - <?php echo htmlspecialchars($rental['domain']); ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            
            <!-- Genel Ayarlar -->
            <div class="settings-section">
                <h6><i class="bi bi-globe me-2"></i>Genel Ayarlar</h6>
                
                <div class="setting-row">
                    <label class="form-label">Site Başlığı</label>
                    <input type="text" name="site_title" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['site_title']); ?>" 
                           placeholder="örn: Betasus Bahis">
                    <div class="help-text">Sitenizin başlığı (SEO için önemli)</div>
                </div>
                
                <div class="setting-row">
                    <label class="form-label">Site Açıklaması</label>
                    <textarea name="site_description" class="form-control" rows="3" 
                              placeholder="Siteniz hakkında kısa açıklama"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                    <div class="help-text">Sitenizin kısa açıklaması (SEO için önemli)</div>
                </div>
                
                <div class="setting-row">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                               id="maintenanceMode" <?php echo $settings['maintenance_mode'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="maintenanceMode">
                            <strong>Bakım Modu</strong>
                            <div class="help-text">Aktif olduğunda siteniz ziyaretçilere kapalı olur</div>
                        </label>
                    </div>
                </div>
                
                <div class="setting-row">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="registration_open" 
                               id="registrationOpen" <?php echo $settings['registration_open'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="registrationOpen">
                            <strong>Kayıt Açık</strong>
                            <div class="help-text">Yeni kullanıcı kayıtlarını aç/kapa</div>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Canlı Destek -->
            <div class="settings-section">
                <h6>
                    <i class="bi bi-chat-dots me-2"></i>Canlı Destek (Tawk.to)
                    <?php if ($settings['tawkto_id']): ?>
                    <span class="integration-badge">
                        <i class="bi bi-check-circle"></i>Aktif
                    </span>
                    <?php else: ?>
                    <span class="integration-badge inactive">
                        <i class="bi bi-x-circle"></i>Pasif
                    </span>
                    <?php endif; ?>
                </h6>
                
                <div class="setting-row">
                    <label class="form-label">Tawk.to Property ID</label>
                    <input type="text" name="tawkto_id" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['tawkto_id']); ?>" 
                           placeholder="örn: 5f123456789abcdef">
                    <div class="help-text">
                        <i class="bi bi-info-circle me-1"></i>
                        Tawk.to hesabınızdan Property ID'nizi alın. 
                        <a href="https://www.tawk.to/" target="_blank">Tawk.to'ya git</a>
                    </div>
                </div>
            </div>
            
            <!-- İletişim Bilgileri -->
            <div class="settings-section">
                <h6><i class="bi bi-envelope me-2"></i>İletişim Bilgileri</h6>
                
                <div class="row">
                    <div class="col-md-6 setting-row">
                        <label class="form-label">E-posta Adresi</label>
                        <input type="email" name="contact_email" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['contact_email']); ?>" 
                               placeholder="destek@example.com">
                    </div>
                    
                    <div class="col-md-6 setting-row">
                        <label class="form-label">WhatsApp Numarası</label>
                        <input type="text" name="whatsapp_number" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['whatsapp_number']); ?>" 
                               placeholder="+90 5XX XXX XX XX">
                    </div>
                </div>
                
                <div class="setting-row">
                    <label class="form-label">Telegram Kullanıcı Adı</label>
                    <input type="text" name="telegram_username" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['telegram_username']); ?>" 
                           placeholder="@username">
                    <div class="help-text">@ işareti ile birlikte yazın</div>
                </div>
            </div>
            
            <!-- Ödeme Ayarları -->
            <div class="settings-section">
                <h6><i class="bi bi-cash-coin me-2"></i>Ödeme Ayarları</h6>
                
                <div class="row">
                    <div class="col-md-4 setting-row">
                        <label class="form-label">Minimum Yatırım (₺)</label>
                        <input type="number" name="min_deposit" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['min_deposit']); ?>" 
                               min="0" step="1" placeholder="0">
                    </div>
                    
                    <div class="col-md-4 setting-row">
                        <label class="form-label">Maksimum Yatırım (₺)</label>
                        <input type="number" name="max_deposit" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['max_deposit']); ?>" 
                               min="0" step="1" placeholder="0">
                        <div class="help-text">0 = sınırsız</div>
                    </div>
                    
                    <div class="col-md-4 setting-row">
                        <label class="form-label">Bonus Oranı (%)</label>
                        <input type="number" name="bonus_rate" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['bonus_rate']); ?>" 
                               min="0" max="100" step="0.1" placeholder="0">
                    </div>
                </div>
                
                <div class="setting-row">
                    <label class="form-label">Çekim Limiti (₺)</label>
                    <input type="number" name="withdrawal_limit" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['withdrawal_limit']); ?>" 
                           min="0" step="1" placeholder="0">
                    <div class="help-text">Günlük çekim limiti (0 = sınırsız)</div>
                </div>
            </div>
            
            <!-- Analytics Entegrasyonu -->
            <div class="settings-section">
                <h6>
                    <i class="bi bi-bar-chart me-2"></i>Analytics Entegrasyonu
                    <span class="integration-badge">
                        <i class="bi bi-check-circle"></i>Otomatik Aktif
                    </span>
                </h6>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Analytics sistemi otomatik olarak entegre edilmiştir. Aşağıdaki kodu sitenizin <code>&lt;head&gt;</code> bölümüne ekleyin:
                </div>
                
                <div class="code-snippet">
&lt;!-- ScriptMarket Analytics --&gt;<br>
&lt;script&gt;<br>
&nbsp;&nbsp;window.ANALYTICS_API_URL = 'https://yoursite.com/api/analytics/track';<br>
&nbsp;&nbsp;window.RENTAL_ID = <?php echo $rentalId; ?>;<br>
&lt;/script&gt;<br>
&lt;script src="https://yoursite.com/assets/js/analytics-tracker.js"&gt;&lt;/script&gt;
                </div>
                
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="copyAnalyticsCode()">
                    <i class="bi bi-clipboard me-1"></i>Kodu Kopyala
                </button>
            </div>
            
            <!-- Kaydet Butonu -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-2"></i>Ayarları Kaydet
                </button>
                <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-secondary btn-lg">
                    İptal
                </a>
            </div>
        </form>
        
    </div>
</div>

<script>
function copyAnalyticsCode() {
    const code = `<!-- ScriptMarket Analytics -->
<script>
  window.ANALYTICS_API_URL = 'https://yoursite.com/api/analytics/track';
  window.RENTAL_ID = <?php echo $rentalId; ?>;
<\/script>
<script src="https://yoursite.com/assets/js/analytics-tracker.js"><\/script>`;
    
    navigator.clipboard.writeText(code).then(() => {
        alert('Analytics kodu kopyalandı! Sitenizin <head> bölümüne yapıştırın.');
    });
}
</script>

<?php require 'templates/footer_new.php'; ?>
