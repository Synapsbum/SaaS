<?php
$title = 'İndirim Kodları';
$db = Database::getInstance();

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        if ($_POST['action'] === 'add') {
            $db->insert('coupons', [
                'code' => strtoupper($_POST['code']),
                'discount_percent' => $_POST['discount_percent'],
                'max_uses' => $_POST['max_uses'] ?: null,
                'valid_until' => $_POST['valid_until'] ?: null,
                'is_active' => 1,
                'created_by' => $auth->userId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            Helper::flash('success', 'Kupon oluşturuldu');
        }
        
        if ($_POST['action'] === 'toggle') {
            $coupon = $db->fetch("SELECT is_active FROM coupons WHERE id = ?", [$_POST['coupon_id']]);
            $newStatus = $coupon['is_active'] ? 0 : 1;
            $db->update('coupons', ['is_active' => $newStatus], 'id = ?', [$_POST['coupon_id']]);
            Helper::flash('success', 'Durum güncellendi');
        }
        
        if ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM coupons WHERE id = ?", [$_POST['coupon_id']]);
            Helper::flash('success', 'Kupon silindi');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$coupons = $db->fetchAll("
    SELECT c.*, u.username as creator,
           (SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = c.id) as usage_count
    FROM coupons c
    JOIN users u ON c.created_by = u.id
    ORDER BY c.created_at DESC
");

require 'templates/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6>Yeni Kupon Oluştur</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Kod</label>
                        <input type="text" name="code" class="form-control text-uppercase" placeholder="INDIRIM20" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İndirim (%)</label>
                        <input type="number" name="discount_percent" class="form-control" min="1" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Kullanım (Boş = Sınırsız)</label>
                        <input type="number" name="max_uses" class="form-control" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Son Kullanma Tarihi (Boş = Sınırsız)</label>
                        <input type="datetime-local" name="valid_until" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6>Mevcut Kuponlar</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Kod</th>
                            <th>İndirim</th>
                            <th>Kullanım</th>
                            <th>Geçerlilik</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td><code class="text-warning"><?php echo $c['code']; ?></code></td>
                            <td>%<?php echo $c['discount_percent']; ?></td>
                            <td><?php echo $c['usage_count']; ?> / <?php echo $c['max_uses'] ?: '∞'; ?></td>
                            <td>
                                <?php if ($c['valid_until']): ?>
                                    <?php echo strtotime($c['valid_until']) > time() ? Helper::date($c['valid_until']) : '<span class="text-danger">Süresi Doldu</span>'; ?>
                                <?php else: ?>
                                    <span class="text-muted">Sınırsız</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $c['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $c['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <?php echo $c['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
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

<?php require 'templates/footer.php'; ?>