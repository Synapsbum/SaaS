<?php
$title = 'Admin Panel';
$db = Database::getInstance();

// İstatistikler
$stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as total FROM users")['total'],
    'total_rentals' => $db->fetch("SELECT COUNT(*) as total FROM rentals")['total'],
    'total_revenue' => $db->fetch("SELECT SUM(price_paid) as total FROM rentals WHERE status != 'cancelled'")['total'] ?? 0,
    'pending_tickets' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'open'")['total']
];

require 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo $stats['total_users']; ?></h3>
            <small>Toplam Kullanıcı</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo $stats['total_rentals']; ?></h3>
            <small>Toplam Kiralama</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo Helper::money($stats['total_revenue']); ?></h3>
            <small>Toplam Gelir</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <h3><?php echo $stats['pending_tickets']; ?></h3>
            <small>Bekleyen Ticket</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>Hızlı Erişim</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="<?php echo Helper::url('admin/users'); ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-people"></i> Kullanıcı Yönetimi
                    </a>
                    <a href="<?php echo Helper::url('admin/scripts'); ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-box-seam"></i> Script Yönetimi
                    </a>
                    <a href="<?php echo Helper::url('admin/domains'); ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-globe"></i> Domain Havuzu
                    </a>
                    <a href="<?php echo Helper::url('admin/payments'); ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-cash-stack"></i> Ödeme Onayları
                    </a>
                    <a href="<?php echo Helper::url('admin/tickets'); ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-headset"></i> Ticket Yönetimi
                    </a>
                    <a href="<?php echo Helper::url('admin/coupons'); ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-ticket-perforated"></i> İndirim Kodları
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>