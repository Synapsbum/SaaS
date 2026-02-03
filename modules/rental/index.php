<?php
$title = 'Kiralamalarım';
$user = $auth->user();
$db = Database::getInstance();

$rentals = $db->fetchAll("
    SELECT r.*, s.name as script_name, s.slug, d.domain
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
", [$user['id']]);

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-key me-2"></i>Script Kiralamalarım</h5>
        <a href="<?php echo Helper::url('scripts'); ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i>
            Yeni Kiralama
        </a>
    </div>
    <div class="card-body">
        <?php if ($rentals): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="bi bi-box-seam me-2"></i>Script</th>
                        <th><i class="bi bi-globe me-2"></i>Domain</th>
                        <th><i class="bi bi-check-circle me-2"></i>Durum</th>
                        <th><i class="bi bi-clock me-2"></i>Kalan Süre</th>
                        <th><i class="bi bi-gear me-2"></i>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rentals as $rental): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                                    <?php echo strtoupper(substr($rental['script_name'], 0, 1)); ?>
                                </div>
                                <strong><?php echo $rental['script_name']; ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if ($rental['domain']): ?>
                            <code style="background: rgba(99, 102, 241, 0.1); padding: 4px 8px; border-radius: 6px; color: var(--primary-light);">
                                <?php echo $rental['domain']; ?>
                            </code>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo Helper::statusBadge($rental['status']); ?></td>
                        <td>
                            <?php if ($rental['status'] == 'active' && $rental['expires_at']): ?>
                                <span style="color: var(--warning); font-weight: 600;">
                                    <i class="bi bi-clock-fill me-1"></i>
                                    <?php echo Helper::remaining($rental['expires_at']); ?>
                                </span>
                            <?php elseif (in_array($rental['status'], ['setup_domain', 'setup_config', 'setup_deploy'])): ?>
                                <span style="color: var(--info); font-weight: 600;">
                                    <i class="bi bi-hourglass-split me-1"></i>
                                    Kurulumda
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($rental['status'], ['setup_domain', 'setup_config', 'setup_deploy'])): ?>
                            <a href="<?php echo Helper::url('rental/setup/' . $rental['id']); ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-play-fill"></i>
                                Kuruluma Devam
                            </a>
                            <?php elseif ($rental['status'] == 'pending'): ?>
                            <a href="<?php echo Helper::url('rental/setup/' . $rental['id']); ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-rocket"></i>
                                Kurulum Başlat
                            </a>
                            <?php else: ?>
                            <button class="btn btn-sm" style="background: rgba(255,255,255,0.05); color: var(--text-muted); cursor: not-allowed;" disabled>
                                <i class="bi bi-lock"></i>
                                Yönet
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi bi-inbox"></i>
            </div>
            <h4>Henüz Kiralama Yok</h4>
            <p>Script keşfedin ve hemen kiralamaya başlayın!</p>
            <a href="<?php echo Helper::url('scripts'); ?>" class="btn btn-primary">
                <i class="bi bi-search"></i>
                Script Keşfet
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'templates/footer.php'; ?>
