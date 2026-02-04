<?php
$title = 'Premium Yönetimi';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Güvenlik hatası';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_settings':
                $keys = ['premium_price_monthly', 'premium_price_yearly', 'premium_discount_rate', 'premium_features'];
                foreach ($keys as $key) {
                    if (isset($_POST[$key])) {
                        $db->query("DELETE FROM admin_settings WHERE setting_key = ?", [$key]);
                        $db->insert('admin_settings', [
                            'setting_key' => $key,
                            'setting_value' => $_POST[$key],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                $_SESSION['flash_success'] = 'Premium ayarları güncellendi';
                break;
                
            case 'give_premium':
                $userId = intval($_POST['user_id'] ?? 0);
                $duration = intval($_POST['duration'] ?? 30);
                if ($userId) {
                    $expires = $duration == 99999 ? null : date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                    $db->update('users', [
                        'is_premium' => 1,
                        'premium_until' => $expires
                    ], 'id = ?', [$userId]);
                    $_SESSION['flash_success'] = 'Premium üyelik verildi';
                }
                break;
                
            case 'remove_premium':
                $userId = intval($_POST['user_id'] ?? 0);
                if ($userId) {
                    $db->update('users', [
                        'is_premium' => 0,
                        'premium_until' => null
                    ], 'id = ?', [$userId]);
                    $_SESSION['flash_success'] = 'Premium üyelik kaldırıldı';
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Ayarları çek
$settingsRows = $db->fetchAll("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'premium_%'");
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Premium kullanıcıları çek
$premiumUsers = $db->fetchAll("
    SELECT u.*, 
        (SELECT COUNT(*) FROM rentals WHERE user_id = u.id) as rental_count,
        (SELECT SUM(price_paid) FROM rentals WHERE user_id = u.id) as total_spent
    FROM users u
    WHERE u.is_premium = 1
    ORDER BY u.created_at DESC
    LIMIT 50
");

// Normal kullanıcılar
$regularUsers = $db->fetchAll("
    SELECT id, username, email FROM users 
    WHERE is_premium = 0 AND status = 'active'
    ORDER BY username ASC
");

// İstatistikler
$stats = [
    'total_premium' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_premium = 1")['total'],
    'active_premium' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_premium = 1 AND (premium_until > NOW() OR premium_until IS NULL)")['total'],
    'expired_premium' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_premium = 1 AND premium_until < NOW()")['total'],
    'monthly_revenue' => 0 // Payments tablosunda ayrım yok
];

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?php echo $stats['total_premium']; ?></div>
            <div class="stat-label">Toplam Premium</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?php echo $stats['active_premium']; ?></div>
            <div class="stat-label">Aktif Premium</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-danger"><?php echo $stats['expired_premium']; ?></div>
            <div class="stat-label">Süresi Dolmuş</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-info">-</div>
            <div class="stat-label">Aylık Gelir</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sol Taraf: Ayarlar ve Premium Ver -->
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Premium Ayarları</h5>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Aylık Fiyat (USDT)</label>
                        <input type="number" name="premium_price_monthly" class="form-control form-control-dark" step="0.01" 
                               value="<?php echo $settings['premium_price_monthly'] ?? 10; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Yıllık Fiyat (USDT)</label>
                        <input type="number" name="premium_price_yearly" class="form-control form-control-dark" step="0.01" 
                               value="<?php echo $settings['premium_price_yearly'] ?? 100; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">İndirim Oranı (%)</label>
                        <input type="number" name="premium_discount_rate" class="form-control form-control-dark" 
                               value="<?php echo $settings['premium_discount_rate'] ?? 10; ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-white-50">Özellikler</label>
                        <textarea name="premium_features" class="form-control form-control-dark" rows="3"><?php echo htmlspecialchars($settings['premium_features'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-admin btn-primary-custom w-100">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </form>
            </div>
        </div>
        
        <div class="admin-card mt-4">
            <div class="admin-card-header">
                <h5 class="mb-0">Premium Ver</h5>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="give_premium">
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Kullanıcı</label>
                        <select name="user_id" class="form-select form-control-dark" required>
                            <option value="">Seçin...</option>
                            <?php foreach ($regularUsers as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-white-50">Süre</label>
                        <select name="duration" class="form-select form-control-dark">
                            <option value="30">1 Ay</option>
                            <option value="90">3 Ay</option>
                            <option value="180">6 Ay</option>
                            <option value="365">1 Yıl</option>
                            <option value="99999">Sınırsız</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-admin btn-success w-100">
                        <i class="bi bi-star-fill"></i> Premium Ver
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sağ Taraf: Premium Kullanıcı Listesi -->
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Premium Kullanıcılar (<?php echo count($premiumUsers); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Email</th>
                            <th>Bitiş Tarihi</th>
                            <th>Kiralama</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($premiumUsers as $u): 
                            $isExpired = $u['premium_until'] && strtotime($u['premium_until']) < time();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                <?php if ($u['is_admin']): ?>
                                    <span class="badge bg-danger ms-1">ADMIN</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($u['premium_until']): ?>
                                    <?php echo $isExpired ? '<span class="text-danger">' . date('d.m.Y', strtotime($u['premium_until'])) . '</span>' : date('d.m.Y', strtotime($u['premium_until'])); ?>
                                <?php else: ?>
                                    <span class="text-success">Sınırsız</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $u['rental_count']; ?> kiralama<br>
                                <small class="text-muted"><?php echo number_format($u['total_spent'] ?? 0, 2); ?> USDT</small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $isExpired ? 'danger' : 'success'; ?>">
                                    <?php echo $isExpired ? 'Süresi Doldu' : 'Aktif'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Premium üyeliği kaldırmak istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="remove_premium">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-star"></i> Kaldır
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($premiumUsers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-star fs-1 d-block mb-3"></i>
                                Henüz premium kullanıcı yok
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__FILE__) . '/templates/footer.php'; ?>