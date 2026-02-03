<?php
$title = 'Kullanıcı Yönetimi';
$db = Database::getInstance();

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $targetUserId = $_POST['user_id'] ?? 0;
        
        // Kullanıcı ekle
        if ($_POST['action'] === 'add') {
            $password = $_POST['password'] ?? 'user123';
            $hash = Security::hashPassword($password);
            
            try {
                $db->insert('users', [
                    'username' => $_POST['username'],
                    'password_hash' => $hash,
                    'email' => $_POST['email'] ?: null,
                    'telegram_username' => $_POST['telegram'] ?: null,
                    'balance' => $_POST['balance'] ?? 0,
                    'is_premium' => $_POST['is_premium'] ?? 0,
                    'is_admin' => $_POST['is_admin'] ?? 0,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                Helper::flash('success', 'Kullanıcı oluşturuldu. Şifre: ' . $password);
            } catch (Exception $e) {
                Helper::flash('error', 'Kullanıcı adı veya email zaten kullanımda');
            }
        }
        
        // Kullanıcı düzenle
        if ($_POST['action'] === 'edit') {
            $updateData = [
                'username' => $_POST['username'],
                'email' => $_POST['email'] ?: null,
                'telegram_username' => $_POST['telegram'] ?: null,
                'balance' => $_POST['balance'],
                'is_premium' => $_POST['is_premium'] ?? 0,
                'is_admin' => $_POST['is_admin'] ?? 0,
                'status' => $_POST['status']
            ];
            
            // Şifre değiştirilecekse
            if (!empty($_POST['password'])) {
                $updateData['password_hash'] = Security::hashPassword($_POST['password']);
            }
            
            $db->update('users', $updateData, 'id = ?', [$targetUserId]);
            Helper::flash('success', 'Kullanıcı güncellendi');
        }
        
        // Bakiye ekle/çıkar
        if ($_POST['action'] === 'balance') {
            $amount = floatval($_POST['amount']);
            $type = $_POST['balance_type']; // add veya remove
            
            if ($type === 'remove') {
                $amount = -abs($amount);
            }
            
            $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $targetUserId]);
            
            // Log kaydı
            $db->insert('admin_logs', [
                'admin_id' => $auth->userId(),
                'action' => 'balance_' . $type,
                'target_type' => 'user',
                'target_id' => $targetUserId,
                'details' => json_encode(['amount' => abs($amount)]),
                'ip_address' => Security::getIP(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Helper::flash('success', 'Bakiye güncellendi: ' . Helper::money(abs($amount)) . ' ' . ($type === 'add' ? 'eklendi' : 'çıkarıldı'));
        }
        
        // Durum değiştir (aktif/pasif/ban)
        if ($_POST['action'] === 'status') {
            $newStatus = $_POST['status'];
            $db->update('users', ['status' => $newStatus], 'id = ?', [$targetUserId]);
            Helper::flash('success', 'Durum güncellendi: ' . $newStatus);
        }
        
        // Admin yap/kaldır
        if ($_POST['action'] === 'toggle_admin') {
            $user = $db->fetch("SELECT is_admin FROM users WHERE id = ?", [$targetUserId]);
            $newStatus = $user['is_admin'] ? 0 : 1;
            $db->update('users', ['is_admin' => $newStatus], 'id = ?', [$targetUserId]);
            Helper::flash('success', 'Admin durumu güncellendi');
        }
        
        // Premium ver/kaldır
        if ($_POST['action'] === 'toggle_premium') {
            $user = $db->fetch("SELECT is_premium, premium_until FROM users WHERE id = ?", [$targetUserId]);
            if ($user['is_premium']) {
                $db->update('users', ['is_premium' => 0, 'premium_until' => null], 'id = ?', [$targetUserId]);
                Helper::flash('success', 'Premium kaldırıldı');
            } else {
                $days = intval($_POST['premium_days'] ?? 30);
                $until = date('Y-m-d H:i:s', strtotime("+$days days"));
                $db->update('users', ['is_premium' => 1, 'premium_until' => $until], 'id = ?', [$targetUserId]);
                Helper::flash('success', "Premium $days gün eklendi");
            }
        }
        
        // Kullanıcı sil (soft delete yerine hard delete)
        if ($_POST['action'] === 'delete') {
            // Önce ilişkili verileri sil veya devret
            $db->query("DELETE FROM rentals WHERE user_id = ?", [$targetUserId]);
            $db->query("DELETE FROM payments WHERE user_id = ?", [$targetUserId]);
            $db->query("DELETE FROM tickets WHERE user_id = ?", [$targetUserId]);
            $db->query("DELETE FROM user_ibans WHERE user_id = ?", [$targetUserId]);
            $db->query("DELETE FROM user_notifications WHERE user_id = ?", [$targetUserId]);
            $db->query("DELETE FROM users WHERE id = ?", [$targetUserId]);
            
            Helper::flash('success', 'Kullanıcı ve tüm verileri silindi');
        }
        
        // Giriş engeli (lock)
        if ($_POST['action'] === 'lock') {
            $minutes = intval($_POST['lock_minutes'] ?? 60);
            $lockedUntil = date('Y-m-d H:i:s', strtotime("+$minutes minutes"));
            $db->update('users', [
                'locked_until' => $lockedUntil,
                'failed_logins' => 99
            ], 'id = ?', [$targetUserId]);
            Helper::flash('success', "Kullanıcı $minutes dakika kilitlendi");
        }
        
        // Kilidi aç
        if ($_POST['action'] === 'unlock') {
            $db->update('users', [
                'locked_until' => null,
                'failed_logins' => 0
            ], 'id = ?', [$targetUserId]);
            Helper::flash('success', 'Kilit kaldırıldı');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filtreleme ve arama
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM rentals WHERE user_id = u.id) as rental_count,
        (SELECT SUM(price_paid) FROM rentals WHERE user_id = u.id) as total_spent,
        (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as ticket_count
        FROM users u WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.telegram_username LIKE ?)";
    $params = array_fill(0, 3, "%$search%");
}

if ($statusFilter != 'all') {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY " . match($sortBy) {
    'newest' => 'u.created_at DESC',
    'oldest' => 'u.created_at ASC',
    'balance_high' => 'u.balance DESC',
    'balance_low' => 'u.balance ASC',
    'spent_high' => 'total_spent DESC',
    default => 'u.created_at DESC'
};

$users = $db->fetchAll($sql, $params);

// İstatistikler
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as total FROM users")['total'],
    'active' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE status = 'active'")['total'],
    'banned' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE status = 'banned'")['total'],
    'premium' => $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_premium = 1")['total'],
    'total_balance' => $db->fetch("SELECT SUM(balance) as total FROM users")['total'] ?? 0
];

require 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-2">
        <div class="card stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <small>Toplam</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card">
            <h3><?php echo $stats['active']; ?></h3>
            <small>Aktif</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card">
            <h3><?php echo $stats['banned']; ?></h3>
            <small>Banlı</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card">
            <h3><?php echo $stats['premium']; ?></h3>
            <small>Premium</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo Helper::money($stats['total_balance']); ?></h3>
            <small>Toplam Bakiye</small>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus"></i> Yeni Kullanıcı
        </button>
    </div>
    <div class="col-md-9">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Ara..." value="<?php echo $search; ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control">
                    <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Tüm Durumlar</option>
                    <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="banned" <?php echo $statusFilter == 'banned' ? 'selected' : ''; ?>>Banlı</option>
                    <option value="suspended" <?php echo $statusFilter == 'suspended' ? 'selected' : ''; ?>>Askıya Alınmış</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="sort" class="form-control">
                    <option value="newest" <?php echo $sortBy == 'newest' ? 'selected' : ''; ?>>En Yeni</option>
                    <option value="oldest" <?php echo $sortBy == 'oldest' ? 'selected' : ''; ?>>En Eski</option>
                    <option value="balance_high" <?php echo $sortBy == 'balance_high' ? 'selected' : ''; ?>>Bakiye (Yüksek)</option>
                    <option value="spent_high" <?php echo $sortBy == 'spent_high' ? 'selected' : ''; ?>>Harcama (Yüksek)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-sm mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>İletişim</th>
                        <th>Bakiye</th>
                        <th>İstatistik</th>
                        <th>Durum</th>
                        <th>Yetki</th>
                        <th>Tarih</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $isLocked = $u['locked_until'] && strtotime($u['locked_until']) > time();
                    ?>
                    <tr class="<?php echo $u['status'] != 'active' ? 'table-danger' : ''; ?> <?php echo $isLocked ? 'table-warning' : ''; ?>">
                        <td><?php echo $u['id']; ?></td>
                        <td>
                            <strong><?php echo $u['username']; ?></strong>
                            <?php if ($u['is_premium']): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i></span>
                            <?php endif; ?>
                            <?php if ($isLocked): ?>
                            <span class="badge bg-danger"><i class="bi bi-lock-fill"></i></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['email']): ?>
                            <div><small><i class="bi bi-envelope"></i> <?php echo $u['email']; ?></small></div>
                            <?php endif; ?>
                            <?php if ($u['telegram_username']): ?>
                            <div><small><i class="bi bi-telegram"></i> @<?php echo $u['telegram_username']; ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="<?php echo $u['balance'] > 0 ? 'text-success' : 'text-muted'; ?>">
                                <?php echo Helper::money($u['balance']); ?>
                            </strong>
                        </td>
                        <td>
                            <small>
                                <div><?php echo $u['rental_count']; ?> kiralama</div>
                                <div><?php echo Helper::money($u['total_spent'] ?? 0); ?> harcama</div>
                                <div><?php echo $u['ticket_count']; ?> ticket</div>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $u['status'] == 'active' ? 'success' : 
                                     ($u['status'] == 'banned' ? 'danger' : 'warning'); 
                            ?>"><?php echo $u['status']; ?></span>
                        </td>
                        <td>
                            <?php if ($u['is_admin']): ?>
                            <span class="badge bg-danger">ADMIN</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <div>Kayıt: <?php echo Helper::date($u['created_at'], 'd.m.Y'); ?></div>
                                <?php if ($u['last_login']): ?>
                                <div>Son giriş: <?php echo Helper::date($u['last_login'], 'd.m.Y H:i'); ?></div>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $u['id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#balanceModal<?php echo $u['id']; ?>">
                                    <i class="bi bi-cash"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <?php echo $u['is_admin'] ? 'Admin Kaldır' : 'Admin Yap'; ?>
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#premiumModal<?php echo $u['id']; ?>">
                                            <?php echo $u['is_premium'] ? 'Premium Kaldır' : 'Premium Ver'; ?>
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <?php if ($u['status'] != 'banned'): ?>
                                            <input type="hidden" name="status" value="banned">
                                            <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Banlamak istediğinize emin misiniz?')">
                                                <i class="bi bi-ban"></i> Banla
                                            </button>
                                            <?php else: ?>
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="dropdown-item text-success">
                                                <i class="bi bi-check-circle"></i> Banı Kaldır
                                            </button>
                                            <?php endif; ?>
                                        </form>
                                    </li>
                                    <li>
                                        <?php if ($isLocked): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="dropdown-item text-success">
                                                <i class="bi bi-unlock"></i> Kilidi Aç
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lockModal<?php echo $u['id']; ?>">
                                            <i class="bi bi-lock"></i> Kilitle
                                        </button>
                                        <?php endif; ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('KULLANICI VE TÜM VERİLERİ SİLİNECEK! Emin misiniz?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash"></i> Sil
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Düzenle Modal -->
                    <div class="modal fade" id="editModal<?php echo $u['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark">
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
                                            <input type="text" name="username" class="form-control" value="<?php echo $u['username']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo $u['email']; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Telegram</label>
                                            <div class="input-group">
                                                <span class="input-group-text">@</span>
                                                <input type="text" name="telegram" class="form-control" value="<?php echo $u['telegram_username']; ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Yeni Şifre (Boş = Değiştirme)</label>
                                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Bakiye</label>
                                            <input type="number" name="balance" class="form-control" step="0.01" value="<?php echo $u['balance']; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Durum</label>
                                            <select name="status" class="form-control">
                                                <option value="active" <?php echo $u['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                                <option value="suspended" <?php echo $u['status'] == 'suspended' ? 'selected' : ''; ?>>Askıya Alınmış</option>
                                                <option value="banned" <?php echo $u['status'] == 'banned' ? 'selected' : ''; ?>>Banlı</option>
                                            </select>
                                        </div>
                                        <div class="form-check">
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
                    
                    <!-- Bakiye Modal -->
                    <div class="modal fade" id="balanceModal<?php echo $u['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark">
                                <div class="modal-header">
                                    <h5 class="modal-title">Bakiye İşlemi - <?php echo $u['username']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="balance">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        
                                        <div class="text-center mb-3">
                                            <h4>Mevcut: <?php echo Helper::money($u['balance']); ?></h4>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">İşlem</label>
                                            <select name="balance_type" class="form-control">
                                                <option value="add">Bakiye Ekle (+)</option>
                                                <option value="remove">Bakiye Çıkar (-)</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Miktar (USDT)</label>
                                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
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
                    
                    <!-- Premium Modal -->
                    <div class="modal fade" id="premiumModal<?php echo $u['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark">
                                <div class="modal-header">
                                    <h5 class="modal-title">Premium İşlemi - <?php echo $u['username']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="toggle_premium">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        
                                        <?php if ($u['is_premium']): ?>
                                        <p>Premium üyeliği kaldırmak istediğinize emin misiniz?</p>
                                        <p>Bitiş: <?php echo $u['premium_until'] ? Helper::date($u['premium_until']) : 'Sınırsız'; ?></p>
                                        <?php else: ?>
                                        <div class="mb-3">
                                            <label class="form-label">Premium Süre (Gün)</label>
                                            <input type="number" name="premium_days" class="form-control" value="30" min="1" required>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                        <button type="submit" class="btn btn-warning">
                                            <?php echo $u['is_premium'] ? 'Premium Kaldır' : 'Premium Ekle'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kilit Modal -->
                    <div class="modal fade" id="lockModal<?php echo $u['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark">
                                <div class="modal-header">
                                    <h5 class="modal-title">Kullanıcı Kilitle - <?php echo $u['username']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="lock">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Kilit Süresi (Dakika)</label>
                                            <input type="number" name="lock_minutes" class="form-control" value="60" min="5" required>
                                        </div>
                                        <p class="text-muted">Kullanıcı belirtilen süre boyunca giriş yapamaz.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                        <button type="submit" class="btn btn-danger">Kilitle</button>
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
</div>

<!-- Yeni Kullanıcı Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
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
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre (Boş = user123)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telegram</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" name="telegram" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Bakiyesi</label>
                        <input type="number" name="balance" class="form-control" step="0.01" value="0">
                    </div>
                    <div class="form-check">
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