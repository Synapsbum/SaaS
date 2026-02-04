<?php
$title = 'Kullanıcı Yönetimi';
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
        $targetUserId = intval($_POST['user_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'add':
                $password = $_POST['password'] ?? 'user123';
                try {
                    $db->insert('users', [
                        'username' => $_POST['username'],
                        'password_hash' => Security::hashPassword($password),
                        'email' => $_POST['email'] ?: null,
                        'telegram_username' => $_POST['telegram'] ?: null,
                        'balance' => floatval($_POST['balance'] ?? 0),
                        'is_premium' => isset($_POST['is_premium']) ? 1 : 0,
                        'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    Helper::flash('success', 'Kullanıcı oluşturuldu. Şifre: ' . $password);
                } catch (Exception $e) {
                    Helper::flash('error', 'Kullanıcı adı veya email zaten kullanımda');
                }
                break;
                
            case 'edit':
                if ($targetUserId) {
                    $data = [
                        'username' => $_POST['username'],
                        'email' => $_POST['email'] ?: null,
                        'telegram_username' => $_POST['telegram'] ?: null,
                        'balance' => floatval($_POST['balance'] ?? 0),
                        'is_premium' => isset($_POST['is_premium']) ? 1 : 0,
                        'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
                        'status' => $_POST['status'] ?? 'active'
                    ];
                    if (!empty($_POST['password'])) {
                        $data['password_hash'] = Security::hashPassword($_POST['password']);
                    }
                    $db->update('users', $data, 'id = ?', [$targetUserId]);
                    Helper::flash('success', 'Kullanıcı güncellendi');
                }
                break;
                
            case 'balance':
                $amount = floatval($_POST['amount'] ?? 0);
                $type = $_POST['balance_type'] ?? 'add';
                if ($type === 'remove') $amount = -abs($amount);
                
                $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $targetUserId]);
                Helper::flash('success', 'Bakiye güncellendi');
                break;
                
            case 'delete':
                if ($targetUserId && $targetUserId != $auth->userId()) {
                    $db->query("DELETE FROM rentals WHERE user_id = ?", [$targetUserId]);
                    $db->query("DELETE FROM payments WHERE user_id = ?", [$targetUserId]);
                    $db->query("DELETE FROM users WHERE id = ?", [$targetUserId]);
                    Helper::flash('success', 'Kullanıcı silindi');
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Listeyi çek
$users = $db->fetchAll("
    SELECT u.*, 
        (SELECT COUNT(*) FROM rentals WHERE user_id = u.id) as rental_count,
        (SELECT SUM(price_paid) FROM rentals WHERE user_id = u.id) as total_spent
    FROM users u 
    ORDER BY u.created_at DESC 
    LIMIT 100
");

$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as total FROM users")['total'],
    'active' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE status = 'active'")['total'],
    'premium' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_premium = 1")['total']
];

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Toplam Kullanıcı</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-success"><?php echo $stats['active']; ?></div>
            <div class="stat-label">Aktif</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-warning"><?php echo $stats['premium']; ?></div>
            <div class="stat-label">Premium</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="mb-0">Kullanıcı Listesi</h5>
        <button class="btn-admin btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg"></i> Yeni Kullanıcı
        </button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı</th>
                    <th>İletişim</th>
                    <th>Bakiye</th>
                    <th>Kiralama</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?php echo $u['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($u['username'] ?? ''); ?></strong>
                        <?php if ($u['is_premium']): ?>
                            <span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i></span>
                        <?php endif; ?>
                        <?php if ($u['is_admin']): ?>
                            <span class="badge bg-danger ms-1">ADMIN</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['email']): ?>
                            <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($u['email'] ?? ''); ?></div>
                        <?php endif; ?>
                        <?php if ($u['telegram_username']): ?>
                            <div class="small text-muted"><i class="bi bi-telegram me-1"></i><?php echo htmlspecialchars($u['telegram_username'] ?? ''); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong class="<?php echo $u['balance'] > 0 ? 'text-success' : 'text-muted'; ?>">
                            <?php echo number_format($u['balance'], 2); ?> USDT
                        </strong>
                    </td>
                    <td>
                        <small>
                            <?php echo $u['rental_count']; ?> kiralama<br>
                            <?php echo number_format($u['total_spent'] ?? 0, 2); ?> USDT harcama
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $u['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo $u['status']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $u['id']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#balanceModal<?php echo $u['id']; ?>">
                                <i class="bi bi-cash"></i>
                            </button>
                            <?php if ($u['id'] != $auth->userId()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo $u['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Kullanıcı Düzenle #<?php echo $u['id']; ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kullanıcı Adı</label>
                                        <input type="text" name="username" class="form-control form-control-dark" value="<?php echo htmlspecialchars($u['username'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control form-control-dark" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Telegram</label>
                                        <input type="text" name="telegram" class="form-control form-control-dark" value="<?php echo htmlspecialchars($u['telegram_username'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Yeni Şifre (Boş = Değiştirme)</label>
                                        <input type="password" name="password" class="form-control form-control-dark">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Bakiye</label>
                                        <input type="number" name="balance" class="form-control form-control-dark" step="0.01" value="<?php echo $u['balance']; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Durum</label>
                                        <select name="status" class="form-select form-control-dark">
                                            <option value="active" <?php echo $u['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="banned" <?php echo $u['status'] == 'banned' ? 'selected' : ''; ?>>Banlı</option>
                                            <option value="suspended" <?php echo $u['status'] == 'suspended' ? 'selected' : ''; ?>>Askıya Alınmış</option>
                                        </select>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_admin" value="1" <?php echo $u['is_admin'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Admin Yetkisi</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_premium" value="1" <?php echo $u['is_premium'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Premium Üyelik</label>
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
                
                <!-- Balance Modal -->
                <div class="modal fade" id="balanceModal<?php echo $u['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Bakiye İşlemi - <?php echo htmlspecialchars($u['username'] ?? ''); ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="balance">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    
                                    <div class="text-center mb-4">
                                        <h4>Mevcut: <?php echo number_format($u['balance'], 2); ?> USDT</h4>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">İşlem</label>
                                        <select name="balance_type" class="form-select form-control-dark">
                                            <option value="add">Bakiye Ekle (+)</option>
                                            <option value="remove">Bakiye Çıkar (-)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Miktar (USDT)</label>
                                        <input type="number" name="amount" class="form-control form-control-dark" step="0.01" min="0.01" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-success">Uygula</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Kullanıcı Oluştur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı *</label>
                        <input type="text" name="username" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre (Boş = user123)</label>
                        <input type="password" name="password" class="form-control form-control-dark">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control form-control-dark">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telegram</label>
                        <input type="text" name="telegram" class="form-control form-control-dark">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Bakiyesi</label>
                        <input type="number" name="balance" class="form-control form-control-dark" step="0.01" value="0">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_admin" value="1">
                        <label class="form-check-label">Admin Yetkisi Ver</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_premium" value="1">
                        <label class="form-check-label">Premium Üyelik Ver</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>