<?php
$title = 'Script Yönetimi';
$user = $auth->user();
$db = Database::getInstance();

// ID'yi al (hem URL segment hem GET desteği)
$rentalId = isset($id) ? (int)$id : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($rentalId <= 0) {
    $_SESSION['error'] = 'Geçersiz kiralama ID';
    Helper::redirect('rental');
    exit;
}

// Kiralama bilgilerini çek
try {
    $rental = $db->fetch("
        SELECT r.*, s.name as script_name, s.slug, d.domain, u.username as owner_name
        FROM rentals r
        JOIN scripts s ON r.script_id = s.id
        JOIN users u ON r.user_id = u.id
        LEFT JOIN script_domains d ON r.domain_id = d.id
        WHERE r.id = ? AND r.user_id = ?
    ", [$rentalId, $user['id']]);
    
    if (!$rental) {
        $_SESSION['error'] = 'Kiralama bulunamadı veya erişim yetkiniz yok';
        Helper::redirect('rental');
        exit;
    }
    
} catch (Exception $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}

// Süre kontrolü
$isExpired = false;
$timeLeft = 'Süresiz';
if ($rental['expires_at']) {
    $diff = strtotime($rental['expires_at']) - time();
    if ($diff <= 0) {
        $isExpired = true;
        $timeLeft = 'Süre doldu';
        if ($rental['status'] == 'active') {
            $db->update('rentals', ['status' => 'expired'], 'id = ?', [$rentalId]);
            $rental['status'] = 'expired';
        }
    } else {
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $timeLeft = $days > 0 ? "$days gün $hours saat" : "$hours saat";
    }
}

// Analytics İstatistikleri
$stats = [
    'visitors_today' => 0,
    'visitors_total' => 0,
    'deposits_today' => 0,
    'deposits_total' => 0,
    'ibans' => 0,
    'wallets' => 0,
    'active_now' => 0
];

try {
    // Bugünkü ziyaretçi
    $stats['visitors_today'] = $db->fetch("
        SELECT COUNT(DISTINCT visitor_ip) as cnt 
        FROM rental_analytics 
        WHERE rental_id = ? AND visit_date = CURDATE()
    ", [$rentalId])['cnt'] ?? 0;
    
    // Toplam ziyaretçi
    $stats['visitors_total'] = $db->fetch("
        SELECT COUNT(DISTINCT visitor_ip) as cnt 
        FROM rental_analytics 
        WHERE rental_id = ?
    ", [$rentalId])['cnt'] ?? 0;
    
    // Bugünkü yatırım
    $stats['deposits_today'] = $db->fetch("
        SELECT SUM(amount_try) as total 
        FROM rental_deposits 
        WHERE rental_id = ? AND DATE(created_at) = CURDATE()
    ", [$rentalId])['total'] ?? 0;
    
    // Toplam yatırım
    $stats['deposits_total'] = $db->fetch("
        SELECT SUM(amount_try) as total 
        FROM rental_deposits 
        WHERE rental_id = ?
    ", [$rentalId])['total'] ?? 0;
    
    // IBAN sayısı
    $stats['ibans'] = $db->fetch("
        SELECT COUNT(*) as cnt FROM rental_ibans WHERE rental_id = ?
    ", [$rentalId])['cnt'] ?? 0;
    
    // Cüzdan sayısı
    $stats['wallets'] = $db->fetch("
        SELECT COUNT(*) as cnt FROM rental_crypto_wallets WHERE rental_id = ?
    ", [$rentalId])['cnt'] ?? 0;
    
    // Şu an aktif kullanıcı (son 5 dakika)
    $stats['active_now'] = $db->fetch("
        SELECT COUNT(DISTINCT session_id) as cnt
        FROM rental_active_sessions
        WHERE rental_id = ? AND last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ", [$rentalId])['cnt'] ?? 0;
    
} catch (Exception $e) {
    // Tablolar yoksa sessizce devam et
    error_log("Analytics error: " . $e->getMessage());
}

require 'templates/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
.manage-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.manage-header.expired {
    background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
}
.status-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}
.status-active { background: #10b981; color: white; }
.status-expired { background: #ef4444; color: white; }
.status-pending { background: #f59e0b; color: white; }

.stat-card {
    background: rgba(15, 15, 30, 0.9);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    transition: transform 0.3s;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    transform: translateY(-5px);
    border-color: #667eea;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}
.stat-icon {
    font-size: 35px;
    margin-bottom: 15px;
    color: #667eea;
}
.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: white;
    margin-bottom: 5px;
}
.stat-label {
    color: #a1a1aa;
    font-size: 14px;
}

.action-card {
    background: rgba(15, 15, 30, 0.8);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    text-decoration: none;
    color: white;
    display: block;
    transition: all 0.3s;
    height: 100%;
}
.action-card:hover {
    border-color: #667eea;
    background: rgba(15, 15, 30, 0.95);
    transform: translateY(-3px);
    color: white;
    text-decoration: none;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}
.action-card i {
    font-size: 40px;
    margin-bottom: 15px;
    display: block;
    color: #667eea;
}
.action-card h5 {
    margin-bottom: 10px;
    font-weight: 600;
    color: white;
}
.action-card p {
    color: #a1a1aa;
    font-size: 14px;
    margin: 0;
}

.domain-box {
    background: rgba(255,255,255,0.15);
    padding: 15px 20px;
    border-radius: 12px;
    margin-top: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid rgba(255,255,255,0.2);
}

.domain-box .btn-visit {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.domain-box .btn-visit:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.analytics-card {
    background: rgba(15, 15, 30, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
}
.analytics-card:hover {
    transform: translateY(-3px);
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
}
.analytics-card.active-users {
    border-color: rgba(16, 185, 129, 0.3);
}
.analytics-card.active-users:hover {
    border-color: rgba(16, 185, 129, 0.5);
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
}
.analytics-card.deposits {
    border-color: rgba(245, 158, 11, 0.3);
}
.analytics-card.deposits:hover {
    border-color: rgba(245, 158, 11, 0.5);
    box-shadow: 0 10px 30px rgba(245, 158, 11, 0.2);
}
.analytics-number {
    font-size: 32px;
    font-weight: 900;
    color: white;
    margin-bottom: 5px;
}
.analytics-label {
    font-size: 13px;
    color: #a1a1aa;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-card {
    background: rgba(15, 15, 30, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    overflow: hidden;
}

.info-card .card-header {
    background: rgba(10, 10, 25, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 20px 24px;
}

.info-card .card-header h5 {
    margin: 0;
    color: white;
    font-weight: 600;
}

.info-card .card-body {
    background: rgba(15, 15, 30, 0.9);
    padding: 24px;
    color: #e5e7eb;
}

.info-card .card-body p {
    color: #d1d5db;
    margin-bottom: 12px;
}

.info-card .card-body strong {
    color: white;
    font-weight: 600;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="manage-header <?php echo $isExpired ? 'expired' : ''; ?>">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-cog me-2"></i>
                    <?php echo htmlspecialchars($rental['script_name']); ?>
                </h2>
                <div class="mb-3">
                    <span class="status-badge status-<?php echo $rental['status']; ?>">
                        <?php echo ucfirst($rental['status']); ?>
                    </span>
                    <span class="ms-3 text-light opacity-75">
                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($rental['owner_name']); ?>
                    </span>
                </div>
                
                <?php if ($rental['domain']): ?>
                <div class="domain-box">
                    <div>
                        <i class="fas fa-globe me-2"></i>
                        <strong><?php echo htmlspecialchars($rental['domain']); ?></strong>
                    </div>
                    <a href="https://<?php echo htmlspecialchars($rental['domain']); ?>" target="_blank" class="btn-visit">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Siteye Git</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="text-light opacity-75 mb-1">Kalan Süre</div>
                <div class="h2 mb-0 <?php echo $isExpired ? 'text-danger' : ''; ?>">
                    <?php echo $timeLeft; ?>
                </div>
                <?php if ($rental['expires_at']): ?>
                <small class="opacity-75">
                    <?php echo date('d.m.Y H:i', strtotime($rental['expires_at'])); ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Analytics Dashboard -->
    <h4 class="mb-4" style="color: white;"><i class="fas fa-chart-line me-2 text-info"></i>Analytics & İstatistikler</h4>
    <div class="analytics-grid">
        <div class="analytics-card active-users">
            <div class="analytics-number text-success">
                <i class="fas fa-users me-2"></i><?php echo number_format($stats['active_now']); ?>
            </div>
            <div class="analytics-label">Şu An Aktif</div>
        </div>
        
        <div class="analytics-card">
            <div class="analytics-number text-info">
                <?php echo number_format($stats['visitors_today']); ?>
            </div>
            <div class="analytics-label">Bugünkü Ziyaretçi</div>
        </div>
        
        <div class="analytics-card">
            <div class="analytics-number text-primary">
                <?php echo number_format($stats['visitors_total']); ?>
            </div>
            <div class="analytics-label">Toplam Ziyaretçi</div>
        </div>
        
        <div class="analytics-card deposits">
            <div class="analytics-number text-warning">
                ₺<?php echo number_format($stats['deposits_today'], 0, ',', '.'); ?>
            </div>
            <div class="analytics-label">Bugünkü Yatırım</div>
        </div>
        
        <div class="analytics-card deposits">
            <div class="analytics-number text-warning">
                ₺<?php echo number_format($stats['deposits_total'], 0, ',', '.'); ?>
            </div>
            <div class="analytics-label">Toplam Yatırım</div>
        </div>
        
        <div class="analytics-card">
            <div class="analytics-number text-secondary">
                <?php echo $stats['ibans'] + $stats['wallets']; ?>
            </div>
            <div class="analytics-label">Ödeme Yöntemi</div>
        </div>
    </div>

    <!-- Hızlı İşlemler -->
    <h4 class="mb-4" style="color: white;"><i class="fas fa-bolt me-2 text-warning"></i>Hızlı İşlemler</h4>
    <div class="row">
        <div class="col-md-4 mb-4">
            <a href="<?php echo Helper::url('rental/manage/' . $rentalId . '/ibans'); ?>" class="action-card">
                <i class="fas fa-university text-primary"></i>
                <h5>IBAN Yönetimi</h5>
                <p>Hesap bilgilerini ekle/düzenle (<?php echo $stats['ibans']; ?> aktif)</p>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="<?php echo Helper::url('rental/manage/' . $rentalId . '/wallets'); ?>" class="action-card">
                <i class="fas fa-wallet text-success"></i>
                <h5>Kripto Cüzdan</h5>
                <p>USDT/TRX/BTC adresleri (<?php echo $stats['wallets']; ?> aktif)</p>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="<?php echo Helper::url('rental/manage/' . $rentalId . '/settings'); ?>" class="action-card">
                <i class="fas fa-cogs text-warning"></i>
                <h5>Site Ayarları</h5>
                <p>Tawk.to, limitler, bakım modu</p>
            </a>
        </div>
    </div>

    <!-- Bilgi Kartı -->
    <div class="info-card mt-4">
        <div class="card-header">
            <h5><i class="fas fa-info-circle me-2"></i>Bilgi</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2"><strong>Kiralama ID:</strong> #<?php echo $rental['id']; ?></p>
                    <p class="mb-2"><strong>Script:</strong> <?php echo htmlspecialchars($rental['script_name']); ?></p>
                    <p class="mb-0"><strong>Başlangıç:</strong> <?php echo $rental['activated_at'] ? date('d.m.Y', strtotime($rental['activated_at'])) : '-'; ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-2"><strong>Ödenen:</strong> <?php echo Helper::money($rental['price_paid']); ?></p>
                    <p class="mb-2"><strong>Süre:</strong> <?php echo number_format($rental['duration_hours'] / 24, 1); ?> gün</p>
                    <p class="mb-0"><strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($rental['updated_at'] ?? $rental['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>