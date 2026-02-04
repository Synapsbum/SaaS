<?php
$title = 'Ödeme Yönetimi';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $action = $_POST['action'] ?? '';
        $paymentId = intval($_POST['payment_id'] ?? 0);
        
        switch ($action) {
            case 'approve':
                $payment = $db->fetch("SELECT * FROM payments WHERE id = ?", [$paymentId]);
                if ($payment && $payment['status'] == 'pending') {
                    $db->beginTransaction();
                    try {
                        // Bakiye ekle - amount_usdt kullan
                        $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$payment['amount_usdt'], $payment['user_id']]);
                        
                        // Ödeme durumunu güncelle
                        $db->query("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?", [$paymentId]);
                        
                        $db->commit();
                        Helper::flash('success', 'Ödeme onaylandı ve bakiye eklendi');
                    } catch (Exception $e) {
                        $db->rollBack();
                        Helper::flash('error', 'İşlem hatası: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'reject':
                $db->query("UPDATE payments SET status = 'cancelled' WHERE id = ?", [$paymentId]);
                Helper::flash('success', 'Ödeme reddedildi');
                break;
                
            case 'delete':
                $db->query("DELETE FROM payments WHERE id = ? AND status != 'paid'", [$paymentId]);
                Helper::flash('success', 'Ödeme kaydı silindi');
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Ödemeleri çek - amount_usdt kullan
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT p.*, u.username, u.email
    FROM payments p
    JOIN users u ON p.user_id = u.id
";
if ($statusFilter != 'all') {
    $sql .= " WHERE p.status = '{$statusFilter}'";
}
$sql .= " ORDER BY p.created_at DESC LIMIT 100";

$payments = $db->fetchAll($sql);

// İstatistikler - amount_usdt kullan
$stats = [
    'total_today' => $db->fetch("SELECT SUM(amount_usdt) as total FROM payments WHERE status = 'paid' AND DATE(paid_at) = CURDATE()")['total'] ?? 0,
    'pending' => $db->fetch("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")['total'],
    'completed_month' => $db->fetch("SELECT SUM(amount_usdt) as total FROM payments WHERE status = 'paid' AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0,
    'total_count' => $db->fetch("SELECT COUNT(*) as total FROM payments")['total']
];

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?php echo number_format($stats['total_today'], 2); ?></div>
            <div class="stat-label">Bugünkü Onay (USDT)</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Bekleyen</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-info"><?php echo number_format($stats['completed_month'], 2); ?></div>
            <div class="stat-label">30 Günlük (USDT)</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_count']; ?></div>
            <div class="stat-label">Toplam İşlem</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap">
        <h5 class="mb-0">Ödeme Listesi</h5>
        <div class="btn-group mt-2 mt-md-0">
            <a href="?status=all" class="btn btn-sm btn-outline-light <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">Bekleyen</a>
            <a href="?status=paid" class="btn btn-sm btn-outline-success <?php echo $statusFilter == 'paid' ? 'active' : ''; ?>">Onaylanan</a>
            <a href="?status=expired" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter == 'expired' ? 'active' : ''; ?>">Süresi Dolmuş</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı</th>
                    <th>Miktar</th>
                    <th>Metod</th>
                    <th>Order ID</th>
                    <th>Tarih</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td>#<?php echo $p['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($p['username'] ?? ''); ?></strong>
                        <div class="small text-muted"><?php echo $p['email']; ?></div>
                    </td>
                    <td><strong class="text-success"><?php echo number_format($p['amount_usdt'], 2); ?> USDT</strong></td>
                    <td>
                        <span class="badge bg-info">Crypto</span>
                    </td>
                    <td>
                        <code class="small"><?php echo htmlspecialchars($p['cryptomus_order_id'] ?? '-'); ?></code>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($p['created_at'])); ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $p['status'] == 'paid' ? 'success' : 
                                 ($p['status'] == 'pending' ? 'warning' : 'secondary'); 
                        ?>">
                            <?php echo $p['status'] == 'paid' ? 'Onaylandı' : ($p['status'] == 'pending' ? 'Bekliyor' : 'İptal'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($p['status'] == 'pending'): ?>
                        <div class="btn-group">
                            <form method="POST" class="d-inline me-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Ödemeyi onaylayıp bakiye eklemek istediğinize emin misiniz?')">
                                    <i class="bi bi-check-lg"></i> Onayla
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-x-lg"></i> Reddet
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu kaydı silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        Ödeme kaydı bulunamadı
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__FILE__) . '/templates/footer.php'; ?>