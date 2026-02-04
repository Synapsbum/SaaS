<?php
$title = 'Şans Çarkı Yönetimi';
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
        
        switch ($action) {
            case 'add_reward':
                $db->insert('wheel_rewards', [
                    'label' => $_POST['label'],
                    'reward_type' => $_POST['reward_type'],
                    'amount' => floatval($_POST['amount']),
                    'probability' => intval($_POST['probability']),
                    'color' => $_POST['color'] ?? '#6366f1',
                    'is_active' => 1
                ]);
                $_SESSION['flash_success'] = 'Ödül eklendi';
                break;
                
            case 'edit_reward':
                $db->update('wheel_rewards', [
                    'label' => $_POST['label'],
                    'reward_type' => $_POST['reward_type'],
                    'amount' => floatval($_POST['amount']),
                    'probability' => intval($_POST['probability']),
                    'color' => $_POST['color'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ], 'id = ?', [$_POST['reward_id']]);
                $_SESSION['flash_success'] = 'Ödül güncellendi';
                break;
                
            case 'delete_reward':
                $db->query("DELETE FROM wheel_rewards WHERE id = ?", [$_POST['reward_id']]);
                $_SESSION['flash_success'] = 'Ödül silindi';
                break;
                
            case 'update_settings':
                $db->query("REPLACE INTO admin_settings (setting_key, setting_value) VALUES ('wheel_enabled', ?)", [isset($_POST['wheel_enabled']) ? '1' : '0']);
                $db->query("REPLACE INTO admin_settings (setting_key, setting_value) VALUES ('wheel_daily_limit', ?)", [intval($_POST['daily_limit'])]);
                $_SESSION['flash_success'] = 'Ayarlar güncellendi';
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Ödülleri çek
$rewards = $db->fetchAll("SELECT * FROM wheel_rewards ORDER BY id ASC");

// Ayarlar
$wheelEnabled = $db->fetch("SELECT setting_value FROM admin_settings WHERE setting_key = 'wheel_enabled'")['setting_value'] ?? '1';
$dailyLimit = $db->fetch("SELECT setting_value FROM admin_settings WHERE setting_key = 'wheel_daily_limit'")['setting_value'] ?? '1';

// Son kazananlar
$recentWinners = $db->fetchAll("
    SELECT w.*, u.username, r.label 
    FROM wheel_spins w
    JOIN users u ON w.user_id = u.id
    JOIN wheel_rewards r ON w.reward_id = r.id
    ORDER BY w.spun_at DESC
    LIMIT 20
");

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Yeni Ödül Ekle</h5>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add_reward">
                    
                    <div class="mb-3">
                        <label class="form-label">Ödül Adı</label>
                        <input type="text" name="label" class="form-control form-control-dark" placeholder="örn: 10 USDT" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ödül Tipi</label>
                        <select name="reward_type" class="form-select form-control-dark">
                            <option value="usdt">USDT</option>
                            <option value="empty">Boş (Kazanamadı)</option>
                            <option value="bonus">Bonus Kredi</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Miktar (USDT veya Bonus)</label>
                        <input type="number" name="amount" class="form-control form-control-dark" step="0.01" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Olasılık (%)</label>
                        <input type="number" name="probability" class="form-control form-control-dark" min="1" max="100" value="10" required>
                        <div class="form-text text-muted">1-100 arası. Toplam 100 olmalı.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Renk</label>
                        <input type="color" name="color" class="form-control form-control-dark" value="#6366f1" style="height: 40px;">
                    </div>
                    
                    <button type="submit" class="btn-admin btn-primary-custom w-100">
                        <i class="bi bi-plus-lg"></i> Ekle
                    </button>
                </form>
            </div>
        </div>
        
        <div class="admin-card mt-4">
            <div class="admin-card-header">
                <h5 class="mb-0">Çark Ayarları</h5>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="wheel_enabled" value="1" <?php echo $wheelEnabled == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label">Çark Aktif</label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Günlük Çevirme Limiti</label>
                        <input type="number" name="daily_limit" class="form-control form-control-dark" value="<?php echo $dailyLimit; ?>" min="1">
                    </div>
                    
                    <button type="submit" class="btn-admin btn-warning-custom w-100">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h5 class="mb-0">Ödüller</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Renk</th>
                            <th>Ödül</th>
                            <th>Tip</th>
                            <th>Miktar</th>
                            <th>Olasılık</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalProb = 0;
                        foreach ($rewards as $r): 
                            $totalProb += $r['probability'];
                        ?>
                        <tr>
                            <td><div style="width: 30px; height: 30px; background: <?php echo $r['color']; ?>; border-radius: 50%;"></div></td>
                            <td><strong><?php echo htmlspecialchars($r['label']); ?></strong></td>
                            <td><?php echo $r['reward_type']; ?></td>
                            <td><?php echo number_format($r['amount'], 2); ?></td>
                            <td>%<?php echo $r['probability']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $r['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $r['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#editReward<?php echo $r['id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="delete_reward">
                                    <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editReward<?php echo $r['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Ödül Düzenle</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="edit_reward">
                                            <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Ödül Adı</label>
                                                <input type="text" name="label" class="form-control form-control-dark" value="<?php echo htmlspecialchars($r['label']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Miktar</label>
                                                <input type="number" name="amount" class="form-control form-control-dark" step="0.01" value="<?php echo $r['amount']; ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Olasılık (%)</label>
                                                <input type="number" name="probability" class="form-control form-control-dark" min="1" max="100" value="<?php echo $r['probability']; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Renk</label>
                                                <input type="color" name="color" class="form-control form-control-dark" value="<?php echo $r['color']; ?>" style="height: 40px;">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?php echo $r['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Aktif</label>
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($totalProb != 100): ?>
                <div class="alert alert-warning m-3">
                    <i class="bi bi-exclamation-triangle"></i> Toplam olasılık %100 olmalı! Şu an: %<?php echo $totalProb; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Son Kazananlar</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Ödül</th>
                            <th>Miktar</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentWinners as $w): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($w['username']); ?></td>
                            <td><?php echo htmlspecialchars($w['label']); ?></td>
                            <td class="text-success">+<?php echo number_format($w['amount_won'], 2); ?> USDT</td>
                            <td><?php echo date('d.m.Y H:i', strtotime($w['spun_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__FILE__) . '/templates/footer.php'; ?>