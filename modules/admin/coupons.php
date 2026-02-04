<?php
$title = 'İndirim Kodları';
$db = Database::getInstance();

// CSRF token kontrolü
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        // Ekleme
        if ($_POST['action'] === 'add') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount = intval($_POST['discount_percent'] ?? 0);
            $maxUses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
            $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
            
            if ($code && $discount > 0 && $discount <= 100) {
                try {
                    $db->insert('coupons', [
                        'code' => $code,
                        'discount_percent' => $discount,
                        'max_uses' => $maxUses,
                        'valid_until' => $validUntil,
                        'is_active' => 1,
                        'created_by' => $auth->userId(),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    Helper::flash('success', 'Kupon başarıyla oluşturuldu');
                } catch (Exception $e) {
                    Helper::flash('error', 'Bu kod zaten kullanımda');
                }
            } else {
                Helper::flash('error', 'Geçersiz veri');
            }
        }
        
        // Toggle
        if ($_POST['action'] === 'toggle' && !empty($_POST['coupon_id'])) {
            $coupon = $db->fetch("SELECT is_active FROM coupons WHERE id = ?", [$_POST['coupon_id']]);
            if ($coupon) {
                $newStatus = $coupon['is_active'] ? 0 : 1;
                $db->update('coupons', ['is_active' => $newStatus], 'id = ?', [$_POST['coupon_id']]);
                Helper::flash('success', 'Durum güncellendi');
            }
        }
        
        // Silme
        if ($_POST['action'] === 'delete' && !empty($_POST['coupon_id'])) {
            $db->query("DELETE FROM coupons WHERE id = ?", [$_POST['coupon_id']]);
            Helper::flash('success', 'Kupon silindi');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Listeyi çek
$coupons = $db->fetchAll("
    SELECT c.*, u.username as creator,
           (SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = c.id) as usage_count
    FROM coupons c
    JOIN users u ON c.created_by = u.id
    ORDER BY c.created_at DESC
");

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Yeni Kupon Oluştur</h5>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Kupon Kodu</label>
                        <input type="text" name="code" class="form-control form-control-dark" placeholder="INDIRIM20" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">İndirim (%)</label>
                        <input type="number" name="discount_percent" class="form-control form-control-dark" min="1" max="100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Max Kullanım (Boş = Sınırsız)</label>
                        <input type="number" name="max_uses" class="form-control form-control-dark" min="1">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-white-50">Son Kullanma Tarihi (Boş = Sınırsız)</label>
                        <input type="datetime-local" name="valid_until" class="form-control form-control-dark">
                    </div>
                    
                    <button type="submit" class="btn-admin btn-primary-custom w-100">
                        <i class="bi bi-plus-lg"></i> Oluştur
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Mevcut Kuponlar</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
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
                            <td>
                                <code class="fs-6 text-warning"><?php echo htmlspecialchars($c['code']); ?></code>
                            </td>
                            <td>
                                <span class="badge bg-success fs-6">%<?php echo $c['discount_percent']; ?></span>
                            </td>
                            <td>
                                <?php echo $c['usage_count']; ?> / <?php echo $c['max_uses'] ?: '∞'; ?>
                            </td>
                            <td>
                                <?php if ($c['valid_until']): ?>
                                    <?php echo strtotime($c['valid_until']) > time() ? date('d.m.Y H:i', strtotime($c['valid_until'])) : '<span class="text-danger">Süresi Doldu</span>'; ?>
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
                                <div class="btn-group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning me-2">
                                            <?php echo $c['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                Henüz kupon oluşturulmamış
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>