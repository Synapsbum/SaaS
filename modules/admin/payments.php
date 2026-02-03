<?php
$title = 'Ödeme Onayları';
$db = Database::getInstance();

// Manuel ödeme onaylama
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        if ($_POST['action'] === 'approve') {
            $paymentId = $_POST['payment_id'];
            $payment = $db->fetch("SELECT * FROM payments WHERE id = ? AND status = 'pending'", [$paymentId]);
            
            if ($payment) {
                try {
                    $db->beginTransaction();
                    
                    $db->update('payments', [
                        'status' => 'paid',
                        'paid_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$paymentId]);
                    
                    $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$payment['amount_usdt'], $payment['user_id']]);
                    
                    $db->commit();
                    Helper::flash('success', 'Ödeme onaylandı');
                } catch (Exception $e) {
                    $db->rollback();
                    Helper::flash('error', 'Hata oluştu');
                }
            }
        }
        
        if ($_POST['action'] === 'reject') {
            $db->update('payments', ['status' => 'cancelled'], 'id = ?', [$_POST['payment_id']]);
            Helper::flash('success', 'Ödeme reddedildi');
        }
        
        if ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM payments WHERE id = ?", [$_POST['payment_id']]);
            Helper::flash('success', 'Ödeme silindi');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filtreleme
$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT p.*, u.username FROM payments p JOIN users u ON p.user_id = u.id";
$params = [];

if ($statusFilter != 'all') {
    $sql .= " WHERE p.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY p.created_at DESC";

$payments = $db->fetchAll($sql, $params);

// İstatistikler
$stats = [
    'total_pending' => $db->fetch("SELECT SUM(amount_usdt) as total FROM payments WHERE status = 'pending'")['total'] ?? 0,
    'total_paid' => $db->fetch("SELECT SUM(amount_usdt) as total FROM payments WHERE status = 'paid' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0,
    'count_pending' => $db->fetch("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")['total']
];

require 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo Helper::money($stats['total_pending']); ?></h3>
            <small>Bekleyen Ödeme</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo $stats['count_pending']; ?></h3>
            <small>Bekleyen İşlem</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo Helper::money($stats['total_paid']); ?></h3>
            <small>Son 30 Gün</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Ödemeler</h6>
        <div>
            <a href="?status=all" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">Bekleyen</a>
            <a href="?status=paid" class="btn btn-sm btn-outline-success <?php echo $statusFilter == 'paid' ? 'active' : ''; ?>">Onaylanan</a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-dark table-sm mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı</th>
                    <th>Miktar</th>
                    <th>UUID</th>
                    <th>Durum</th>
                    <th>Tarih</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo $p['username']; ?></td>
                    <td><?php echo Helper::money($p['amount_usdt']); ?></td>
                    <td><small class="text-muted"><?php echo substr($p['cryptomus_uuid'] ?? '-', 0, 20); ?>...</small></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $p['status'] == 'paid' ? 'success' : 
                                 ($p['status'] == 'pending' ? 'warning' : 'danger'); 
                        ?>"><?php echo $p['status']; ?></span>
                    </td>
                    <td><?php echo Helper::date($p['created_at']); ?></td>
                    <td>
                        <?php if ($p['status'] == 'pending'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">Onayla</button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Reddet</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Sil</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'templates/footer.php'; ?>