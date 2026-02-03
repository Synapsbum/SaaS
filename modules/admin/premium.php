<?php
$title = 'Premium Yönetimi';
$db = Database::getInstance();

// Premium kullanıcılar
$premiumUsers = $db->fetchAll("
    SELECT id, username, premium_until, balance, email, telegram_username,
           (SELECT SUM(price_paid) FROM rentals WHERE user_id = users.id) as total_spent
    FROM users 
    WHERE is_premium = 1 AND (premium_until IS NULL OR premium_until > NOW())
    ORDER BY premium_until ASC
");

// Süresi dolmak üzere olanlar (7 günden az)
$expiringSoon = $db->fetchAll("
    SELECT id, username, premium_until
    FROM users 
    WHERE is_premium = 1 AND premium_until BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY premium_until ASC
");

// İstatistikler
$stats = [
    'total_premium' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_premium = 1 AND (premium_until IS NULL OR premium_until > NOW())")['total'],
    'expiring_7days' => count($expiringSoon),
    'monthly_purchases' => $db->fetch("SELECT COUNT(*) as total FROM admin_logs WHERE action = 'premium_purchase' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'],
    'monthly_revenue' => $db->fetch("SELECT COUNT(*) * 300 as total FROM admin_logs WHERE action = 'premium_purchase' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0
];

// Manuel premium ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_premium') {
    $targetUserId = $_POST['user_id'];
    $duration = intval($_POST['duration'] ?? 30);
    
    $targetUser = $db->fetch("SELECT * FROM users WHERE id = ?", [$targetUserId]);
    if ($targetUser) {
        $currentPremium = $targetUser['premium_until'] && strtotime($targetUser['premium_until']) > time() 
            ? strtotime($targetUser['premium_until']) 
            : time();
        $newPremiumDate = date('Y-m-d H:i:s', strtotime("+$duration days", $currentPremium));
        
        $db->query("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?", [$newPremiumDate, $targetUserId]);
        
        // Log
        $db->insert('admin_logs', [
            'admin_id' => $auth->userId(),
            'action' => 'premium_manual_add',
            'target_type' => 'user',
            'target_id' => $targetUserId,
            'details' => json_encode(['duration' => $duration, 'by_admin' => true]),
            'ip_address' => Security::getIP(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Premium kaldırma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_premium') {
    $targetUserId = $_POST['user_id'];
    $db->query("UPDATE users SET is_premium = 0, premium_until = NULL WHERE id = ?", [$targetUserId]);
    
    $db->insert('admin_logs', [
        'admin_id' => $auth->userId(),
        'action' => 'premium_remove',
        'target_type' => 'user',
        'target_id' => $targetUserId,
        'ip_address' => Security::getIP(),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

require 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo $stats['total_premium']; ?></h3>
            <small>Aktif Premium</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo $stats['expiring_7days']; ?></h3>
            <small>7 Gün İçinde Biten</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo $stats['monthly_purchases']; ?></h3>
            <small>Son 30 Gün Satış</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo Helper::money($stats['monthly_revenue']); ?></h3>
            <small>Son 30 Gün Gelir</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6>Manuel Premium Ekle</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_premium">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı ID</label>
                        <input type="number" name="user_id" class="form-control" required placeholder="Kullanıcı ID">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Süre (Gün)</label>
                        <select name="duration" class="form-control">
                            <option value="7">7 Gün</option>
                            <option value="30" selected>30 Gün</option>
                            <option value="60">60 Gün</option>
                            <option value="90">90 Gün</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-star-fill"></i> Premium Ekle
                    </button>
                </form>
            </div>
        </div>
        
        <?php if ($expiringSoon): ?>
        <div class="card mt-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Süresi Dolmak Üzere</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($expiringSoon as $u): 
                        $daysLeft = ceil((strtotime($u['premium_until']) - time()) / 86400);
                    ?>
                    <li class="list-group-item bg-dark text-light d-flex justify-content-between align-items-center">
                        <?php echo $u['username']; ?>
                        <span class="badge bg-danger"><?php echo $daysLeft; ?> gün</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Premium Kullanıcılar</h6>
                <span class="badge bg-warning text-dark"><?php echo count($premiumUsers); ?> aktif</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>İletişim</th>
                                <th>Bitiş</th>
                                <th>Kalan</th>
                                <th>Harcama</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($premiumUsers as $u): 
                                $daysLeft = ceil((strtotime($u['premium_until']) - time()) / 86400);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $u['username']; ?></strong>
                                    <br><small class="text-muted">ID: <?php echo $u['id']; ?></small>
                                </td>
                                <td>
                                    <?php if ($u['telegram_username']): ?>
                                    <i class="bi bi-telegram text-info"></i> @<?php echo $u['telegram_username']; ?>
                                    <?php endif; ?>
                                    <?php if ($u['email']): ?>
                                    <br><small><?php echo $u['email']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($u['premium_until'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'success'); ?>">
                                        <?php echo $daysLeft; ?> gün
                                    </span>
                                </td>
                                <td><?php echo Helper::money($u['total_spent'] ?? 0); ?></td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Premium üyeliği kaldırmak istediğinize emin misiniz?');">
                                        <input type="hidden" name="action" value="remove_premium">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-x-circle"></i> Kaldır
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>