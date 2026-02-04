<?php

$title = 'Dashboard';
$db = Database::getInstance();

// İstatistikler - tickets tablosunu kullan
$stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as total FROM users")['total'],
    'active_rentals' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE status = 'active'")['total'],
    'total_scripts' => $db->fetch("SELECT COUNT(*) as total FROM scripts WHERE status = 'active'")['total'],
    'monthly_revenue' => $db->fetch("SELECT SUM(price_paid) as total FROM rentals WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0,
    'pending_payments' => $db->fetch("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")['total'],
    'open_tickets' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'open'")['total']
];

// Son kiralamalar
$recentRentals = $db->fetchAll("
    SELECT r.*, u.username, s.name as script_name, d.domain
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    ORDER BY r.created_at DESC
    LIMIT 10
");

// Son kayıtlar
$recentUsers = $db->fetchAll("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 8
");

// Script popülerlikleri
$scriptStats = $db->fetchAll("
    SELECT s.id, s.name, 
        (SELECT COUNT(*) FROM rentals WHERE script_id = s.id AND status = 'active') as rental_count,
        (SELECT COUNT(*) FROM rentals WHERE script_id = s.id AND status = 'active') * sp.price_usdt as monthly_income
    FROM scripts s
    LEFT JOIN script_packages sp ON sp.script_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY rental_count DESC
    LIMIT 6
");

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="bi bi-people fs-1 text-primary mb-2"></i>
            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Toplam Kullanıcı</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="bi bi-key fs-1 text-success mb-2"></i>
            <div class="stat-value text-success"><?php echo $stats['active_rentals']; ?></div>
            <div class="stat-label">Aktif Kiralama</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="bi bi-box-seam fs-1 text-warning mb-2"></i>
            <div class="stat-value text-warning"><?php echo $stats['total_scripts']; ?></div>
            <div class="stat-label">Aktif Script</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <i class="bi bi-currency-dollar fs-1 text-info mb-2"></i>
            <div class="stat-value text-info"><?php echo number_format($stats['monthly_revenue'], 2); ?></div>
            <div class="stat-label">30 Günlük Gelir (USDT)</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="admin-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Son Kiralamalar</h5>
                <a href="<?php echo Helper::url('admin/rentals'); ?>" class="btn btn-sm btn-outline-light">Tümünü Gör</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Script</th>
                            <th>Domain</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRentals as $r): ?>
                        <tr>
                            <td>#<?php echo $r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['username']); ?></td>
                            <td><?php echo htmlspecialchars($r['script_name']); ?></td>
                            <td><?php echo $r['domain'] ? htmlspecialchars($r['domain']) : '<span class="text-muted">-</span>'; ?></td>
                            <td><?php echo number_format($r['price_paid'], 2); ?> USDT</td>
                            <td>
                                <span class="badge bg-<?php echo $r['status'] == 'active' ? 'success' : ($r['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                    <?php echo $r['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentRentals)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Henüz kiralama yok</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h5 class="mb-0">Script Performansı</h5>
            </div>
            <div class="admin-card-body">
                <?php foreach ($scriptStats as $s): ?>
                <div class="mb-3 pb-3 border-bottom border-secondary">
                    <div class="d-flex justify-content-between mb-1">
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                        <span class="text-success">+<?php echo number_format($s['monthly_income'], 2); ?> USDT</span>
                    </div>
                    <div class="progress" style="height: 8px; background: rgba(255,255,255,0.1);">
                        <div class="progress-bar bg-<?php echo $s['rental_count'] > 5 ? 'success' : 'primary'; ?>" 
                             style="width: <?php echo min(100, ($s['rental_count'] / max(1, $stats['active_rentals'])) * 100); ?>%">
                        </div>
                    </div>
                    <small class="text-muted"><?php echo $s['rental_count']; ?> aktif kiralama</small>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($scriptStats)): ?>
                <div class="text-center py-4 text-muted">Henüz veri yok</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Son Kayıtlar</h5>
            </div>
            <div class="admin-card-body">
                <?php foreach ($recentUsers as $u): ?>
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom border-secondary">
                    <div class="flex-shrink-0">
                        <div class="bg-<?php echo $u['is_admin'] ? 'danger' : 'primary'; ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-person text-white"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-0"><?php echo htmlspecialchars($u['username']); ?> 
                            <?php if ($u['is_premium']): ?>
                                <i class="bi bi-star-fill text-warning small"></i>
                            <?php endif; ?>
                        </h6>
                        <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($u['created_at'])); ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?php echo $u['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo $u['status']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Hızlı İşlemler</h5>
            </div>
            <div class="admin-card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?php echo Helper::url('admin/users'); ?>" class="btn btn-outline-light w-100 py-3">
                            <i class="bi bi-person-plus fs-3 mb-2 d-block"></i>
                            Kullanıcı Ekle
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo Helper::url('admin/scripts'); ?>" class="btn btn-outline-light w-100 py-3">
                            <i class="bi bi-plus-circle fs-3 mb-2 d-block"></i>
                            Script Ekle
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo Helper::url('admin/coupons'); ?>" class="btn btn-outline-light w-100 py-3">
                            <i class="bi bi-ticket-perforated fs-3 mb-2 d-block"></i>
                            Kupon Oluştur
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo Helper::url('admin/domains'); ?>" class="btn btn-outline-light w-100 py-3">
                            <i class="bi bi-globe fs-3 mb-2 d-block"></i>
                            Domain Ekle
                        </a>
                    </div>
                </div>
                
                <?php if ($stats['pending_payments'] > 0): ?>
                <div class="alert alert-warning mt-4 mb-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <div>
                        <strong><?php echo $stats['pending_payments']; ?></strong> onay bekleyen ödeme var
                        <a href="<?php echo Helper::url('admin/payments'); ?>" class="alert-link ms-2">İncele</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['open_tickets'] > 0): ?>
                <div class="alert alert-info mt-3 mb-0 d-flex align-items-center">
                    <i class="bi bi-headset me-2"></i>
                    <div>
                        <strong><?php echo $stats['open_tickets']; ?></strong> açık destek talebi var
                        <a href="<?php echo Helper::url('admin/tickets'); ?>" class="alert-link ms-2">İncele</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>