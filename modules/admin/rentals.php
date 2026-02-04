<?php
$title = 'Kiralama Yönetimi';
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
        $rentalId = intval($_POST['rental_id'] ?? 0);
        
        switch ($action) {
            case 'update_status':
                $db->query("UPDATE rentals SET status = ?, expires_at = ? WHERE id = ?", [
                    $_POST['status'],
                    $_POST['expires_at'],
                    $rentalId
                ]);
                $_SESSION['flash_success'] = 'Kiralama durumu güncellendi';
                break;
                
            case 'terminate':
                $db->query("UPDATE rentals SET status = 'cancelled' WHERE id = ?", [$rentalId]);
                
                // Domain'i serbest bırak - current_rental_id kullan
                $rental = $db->fetch("SELECT domain_id FROM rentals WHERE id = ?", [$rentalId]);
                if ($rental && $rental['domain_id']) {
                    $db->query("UPDATE script_domains SET status = 'available', current_user_id = NULL WHERE id = ?", [$rental['domain_id']]);
                }
                
                $_SESSION['flash_success'] = 'Kiralama sonlandırıldı';
                break;
                
            case 'extend':
                $days = intval($_POST['days'] ?? 30);
                $rental = $db->fetch("SELECT expires_at FROM rentals WHERE id = ?", [$rentalId]);
                if ($rental) {
                    $currentExpiry = strtotime($rental['expires_at']);
                    if ($currentExpiry < time()) $currentExpiry = time();
                    $newExpiry = date('Y-m-d H:i:s', strtotime("+{$days} days", $currentExpiry));
                    
                    $db->query("UPDATE rentals SET expires_at = ? WHERE id = ?", [$newExpiry, $rentalId]);
                    $_SESSION['flash_success'] = 'Kiralama ' . $days . ' gün uzatıldı';
                }
                break;
                
            case 'delete':
                $rental = $db->fetch("SELECT domain_id FROM rentals WHERE id = ?", [$rentalId]);
                if ($rental && $rental['domain_id']) {
                    $db->query("UPDATE script_domains SET status = 'available', current_user_id = NULL WHERE id = ?", [$rental['domain_id']]);
                }
                $db->query("DELETE FROM rentals WHERE id = ?", [$rentalId]);
                $_SESSION['flash_success'] = 'Kiralama silindi';
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Kiralamaları çek
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT r.*, u.username, u.email, s.name as script_name, d.domain
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
";
if ($statusFilter != 'all') {
    $sql .= " WHERE r.status = '{$statusFilter}'";
}
$sql .= " ORDER BY r.created_at DESC LIMIT 100";

$rentals = $db->fetchAll($sql);

// İstatistikler
$stats = [
    'active' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE status = 'active'")['total'],
    'pending' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE status = 'pending'")['total'],
    'expired' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE status = 'expired' OR (status = 'active' AND expires_at < NOW())")['total'],
    'cancelled' => $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE status = 'cancelled'")['total']
];

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?php echo $stats['active']; ?></div>
            <div class="stat-label">Aktif Kiralama</div>
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
            <div class="stat-value text-secondary"><?php echo $stats['expired']; ?></div>
            <div class="stat-label">Süresi Dolmuş</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-danger"><?php echo $stats['cancelled']; ?></div>
            <div class="stat-label">İptal Edilmiş</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap">
        <h5 class="mb-0">Kiralama Listesi</h5>
        <div class="btn-group mt-2 mt-md-0">
            <a href="?status=all" class="btn btn-sm btn-outline-light <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=active" class="btn btn-sm btn-outline-success <?php echo $statusFilter == 'active' ? 'active' : ''; ?>">Aktif</a>
            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">Bekleyen</a>
            <a href="?status=expired" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter == 'expired' ? 'active' : ''; ?>">Süresi Dolmuş</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı</th>
                    <th>Script</th>
                    <th>Domain</th>
                    <th>Bitiş</th>
                    <th>Fiyat</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $r): 
                    $isExpired = strtotime($r['expires_at']) < time() && $r['status'] == 'active';
                ?>
                <tr>
                    <td>#<?php echo $r['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($r['username'] ?? ''); ?></strong>
                        <div class="small text-muted"><?php echo $r['email']; ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($r['script_name']); ?></td>
                    <td>
                        <?php if ($r['domain']): ?>
                            <code class="text-info"><?php echo htmlspecialchars($r['domain']); ?></code>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isExpired): ?>
                            <span class="text-danger">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <?php echo date('d.m.Y', strtotime($r['expires_at'])); ?>
                            </span>
                        <?php else: ?>
                            <?php echo date('d.m.Y H:i', strtotime($r['expires_at'])); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($r['price_paid'], 2); ?> USDT</td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $r['status'] == 'active' ? ($isExpired ? 'danger' : 'success') : 
                                 ($r['status'] == 'pending' ? 'warning' : 
                                 ($r['status'] == 'cancelled' ? 'dark' : 'secondary')); 
                        ?>">
                            <?php echo $isExpired ? 'Süresi Dolmuş' : $r['status']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <?php if ($r['status'] == 'active' && !$isExpired): ?>
                            <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo $r['id']; ?>">
                                <i class="bi bi-calendar-plus"></i>
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            
                            <?php if ($r['status'] == 'active'): ?>
                            <button class="btn btn-sm btn-danger me-1" data-bs-toggle="modal" data-bs-target="#terminateModal<?php echo $r['id']; ?>">
                                <i class="bi bi-x-octagon"></i>
                            </button>
                            <?php endif; ?>
                            
                            <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="rental_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo $r['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Kiralama Düzenle #<?php echo $r['id']; ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="rental_id" value="<?php echo $r['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Durum</label>
                                        <select name="status" class="form-select form-control-dark">
                                            <option value="active" <?php echo $r['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="pending" <?php echo $r['status'] == 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                                            <option value="expired" <?php echo $r['status'] == 'expired' ? 'selected' : ''; ?>>Süresi Dolmuş</option>
                                            <option value="cancelled" <?php echo $r['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal Edilmiş</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Bitiş Tarihi</label>
                                        <input type="datetime-local" name="expires_at" class="form-control form-control-dark" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($r['expires_at'])); ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-primary">Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Extend Modal -->
                <div class="modal fade" id="extendModal<?php echo $r['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Süre Uzat #<?php echo $r['id']; ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="extend">
                                    <input type="hidden" name="rental_id" value="<?php echo $r['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mevcut Bitiş</label>
                                        <input type="text" class="form-control form-control-dark" value="<?php echo date('d.m.Y H:i', strtotime($r['expires_at'])); ?>" disabled>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kaç Gün Eklenecek?</label>
                                        <input type="number" name="days" class="form-control form-control-dark" value="30" min="1" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-success">Süreyi Uzat</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Terminate Modal -->
                <div class="modal fade" id="terminateModal<?php echo $r['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Kiralama İptal #<?php echo $r['id']; ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="terminate">
                                    <input type="hidden" name="rental_id" value="<?php echo $r['id']; ?>">
                                    
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Bu işlem geri alınamaz! Domain serbest bırakılacak.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-danger">Sonlandır</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($rentals)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        Kiralama bulunamadı
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__FILE__) . '/templates/footer.php'; ?>