<?php
$title = 'Dashboard';
$user = $auth->user();
$db = Database::getInstance();

$stats = [
    'my_scripts' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE user_id = ?", [$user['id']])['total'],
    'active_rentals' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE user_id = ? AND status = 'active'", [$user['id']])['total'],
    'total_spent' => $db->fetch("SELECT SUM(price_paid) as total FROM rentals WHERE user_id = ?", [$user['id']])['total'] ?? 0
];

$recentRentals = $db->fetchAll("
    SELECT r.*, s.name as script_name, s.slug 
    FROM rentals r 
    JOIN scripts s ON r.script_id = s.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 5
", [$user['id']]);

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card">
            <h3><?php echo $stats['my_scripts']; ?></h3>
            <small>Toplam Script</small>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card">
            <h3><?php echo $stats['active_rentals']; ?></h3>
            <small>Aktif Kiralama</small>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card">
            <h3><?php echo Helper::money($user['balance']); ?></h3>
            <small>Bakiye</small>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card">
            <h3><?php echo Helper::money($stats['total_spent']); ?></h3>
            <small>Toplam Harcama</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Son Kiralamalarım</h5>
                <a href="<?php echo Helper::url('rental'); ?>" class="btn btn-primary btn-sm">
                    Tümünü Gör
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if ($recentRentals): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Script</th>
                                <th>Durum</th>
                                <th>Süre</th>
                                <th>Fiyat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRentals as $rental): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-box-seam me-2"></i>
                                    <?php echo $rental['script_name']; ?>
                                </td>
                                <td><?php echo Helper::statusBadge($rental['status']); ?></td>
                                <td>
                                    <?php if ($rental['status'] == 'active'): ?>
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo Helper::remaining($rental['expires_at']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo Helper::money($rental['price_paid']); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 48px; color: var(--text-muted);"></i>
                    <p class="text-muted mt-3">Henüz kiralama yapmadınız.</p>
                    <a href="<?php echo Helper::url('scripts'); ?>" class="btn btn-primary mt-2">
                        <i class="bi bi-search"></i>
                        Script Keşfet
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <a href="<?php echo Helper::url('payment'); ?>" class="btn btn-success w-100 mb-2">
                    <i class="bi bi-plus-circle"></i>
                    Bakiye Yükle
                </a>
                <a href="<?php echo Helper::url('scripts'); ?>" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-cart"></i>
                    Script Satın Al
                </a>
                <a href="<?php echo Helper::url('support'); ?>" class="btn btn-info w-100 mb-2">
                    <i class="bi bi-ticket"></i>
                    Destek Talebi
                </a>
                <?php if (!$user['is_premium']): ?>
                <a href="<?php echo Helper::url('premium'); ?>" class="btn btn-warning w-100">
                    <i class="bi bi-star-fill"></i>
                    Premium Ol
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!$user['is_premium']): ?>
        <div class="card" style="border: 2px solid var(--warning); background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(251, 191, 36, 0.05));">
            <div class="card-body text-center">
                <div style="font-size: 48px; margin-bottom: 16px;">
                    <i class="bi bi-star-fill" style="color: var(--warning);"></i>
                </div>
                <h5 style="margin-bottom: 12px;">Premium Olun</h5>
                <p class="text-muted" style="font-size: 14px;">Tüm scriptlerde %15 indirim ve özel destek!</p>
                <div style="margin: 20px 0;">
                    <h4 style="margin: 0;">
                        <span style="background: linear-gradient(135deg, var(--warning), #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            300 USDT
                        </span>
                        <small style="font-size: 16px; color: var(--text-muted);"> / 30 gün</small>
                    </h4>
                </div>
                <a href="<?php echo Helper::url('premium'); ?>" class="btn btn-warning w-100">
                    <i class="bi bi-star-fill"></i>
                    Premium Satın Al
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="border: 2px solid #10b981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);">
            <div class="card-body" style="padding: 32px 24px; text-align: center;">
                <div style="width: 100px; height: 100px; margin: 0 auto 20px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);">
                    <i class="bi bi-star-fill" style="font-size: 48px; color: white;"></i>
                </div>
                <div class="premium-active-badge" style="margin-bottom: 20px;">
                    <i class="bi bi-check-circle-fill"></i>
                    PREMIUM AKTİF
                </div>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">Premium üyeliğinizden yararlanıyorsunuz</p>
                <div style="padding: 20px; background: rgba(16, 185, 129, 0.15); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3); margin-bottom: 20px;">
                    <div style="font-size: 12px; color: rgba(255,255,255,0.6); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Bitiş Tarihi</div>
                    <div style="font-size: 20px; font-weight: 800; color: #10b981; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="bi bi-calendar-check"></i>
                        <?php echo date('d.m.Y', strtotime($user['premium_until'])); ?>
                    </div>
                </div>
                <a href="<?php echo Helper::url('premium'); ?>" class="btn btn-success w-100">
                    <i class="bi bi-info-circle"></i>
                    Detayları Gör
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'templates/footer.php'; ?>
