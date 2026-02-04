<?php
$title = 'Script İstekleri';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Telegram gönderim fonksiyonu (Admin ayarlarından token ve chat_id alacak)
function sendTelegramNotification($message) {
    $db = Database::getInstance();
    $botToken = $db->fetch("SELECT setting_value FROM admin_settings WHERE setting_key = 'telegram_bot_token'")['setting_value'] ?? '';
    $chatId = $db->fetch("SELECT setting_value FROM admin_settings WHERE setting_key = 'telegram_chat_id'")['setting_value'] ?? '';
    
    if (empty($botToken) || empty($chatId)) return false;
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Güvenlik hatası';
    } else {
        $action = $_POST['action'] ?? '';
        $requestId = intval($_POST['request_id'] ?? 0);
        
        switch ($action) {
            case 'update_status':
                $db->update('script_requests', [
                    'status' => $_POST['status'],
                    'admin_notes' => $_POST['admin_notes'] ?? ''
                ], 'id = ?', [$requestId]);
                $_SESSION['flash_success'] = 'Durum güncellendi';
                break;
                
            case 'send_telegram':
                $request = $db->fetch("SELECT sr.*, u.username, u.email FROM script_requests sr JOIN users u ON sr.user_id = u.id WHERE sr.id = ?", [$requestId]);
                if ($request) {
                    $message = "🆕 <b>Yeni Script İsteği</b>\n\n";
                    $message .= "👤 Kullanıcı: {$request['username']}\n";
                    $message .= "📧 Email: {$request['email']}\n";
                    $message .= "📦 Script: {$request['script_name']}\n";
                    $message .= "💰 Bütçe: {$request['budget']}\n";
                    $message .= "📝 Açıklama:\n{$request['description']}\n\n";
                    $message .= "📱 İletişim: {$request['contact_info']}";
                    
                    sendTelegramNotification($message);
                    $db->update('script_requests', ['telegram_sent' => 1], 'id = ?', [$requestId]);
                    $_SESSION['flash_success'] = 'Telegram\'a gönderildi';
                }
                break;
                
            case 'delete':
                $db->query("DELETE FROM script_requests WHERE id = ?", [$requestId]);
                $_SESSION['flash_success'] = 'İstek silindi';
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filtreleme
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT sr.*, u.username, u.email 
    FROM script_requests sr
    JOIN users u ON sr.user_id = u.id
";
if ($statusFilter != 'all') {
    $sql .= " WHERE sr.status = '{$statusFilter}'";
}
$sql .= " ORDER BY sr.created_at DESC LIMIT 100";

$requests = $db->fetchAll($sql);

// İstatistikler
$stats = [
    'pending' => $db->fetch("SELECT COUNT(*) as total FROM script_requests WHERE status = 'pending'")['total'],
    'reviewed' => $db->fetch("SELECT COUNT(*) as total FROM script_requests WHERE status = 'reviewed'")['total'],
    'completed' => $db->fetch("SELECT COUNT(*) as total FROM script_requests WHERE status = 'completed'")['total'],
    'total' => $db->fetch("SELECT COUNT(*) as total FROM script_requests")['total']
];

require 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Bekleyen</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-info"><?php echo $stats['reviewed']; ?></div>
            <div class="stat-label">İncelenen</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">Tamamlanan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Toplam</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap">
        <h5 class="mb-0">Script İstekleri</h5>
        <div class="btn-group mt-2 mt-md-0">
            <a href="?status=all" class="btn btn-sm btn-outline-light <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=pending" class="btn btn-sm btn-outline-warning <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">Bekleyen</a>
            <a href="?status=reviewed" class="btn btn-sm btn-outline-info <?php echo $statusFilter == 'reviewed' ? 'active' : ''; ?>">İncelenen</a>
            <a href="?status=completed" class="btn btn-sm btn-outline-success <?php echo $statusFilter == 'completed' ? 'active' : ''; ?>">Tamamlanan</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı</th>
                    <th>Script Adı</th>
                    <th>Bütçe</th>
                    <th>Durum</th>
                    <th>Telegram</th>
                    <th>Tarih</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td>#<?php echo $r['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($r['username']); ?></strong>
                        <div class="small text-muted"><?php echo $r['email']; ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($r['script_name']); ?></td>
                    <td><?php echo $r['budget'] ? htmlspecialchars($r['budget']) : '-'; ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $r['status'] == 'pending' ? 'warning' : 
                                 ($r['status'] == 'reviewed' ? 'info' : 
                                 ($r['status'] == 'completed' ? 'success' : 'secondary')); 
                        ?>">
                            <?php echo $r['status']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($r['telegram_sent']): ?>
                            <span class="text-success"><i class="bi bi-check-circle"></i> Gönderildi</span>
                        <?php else: ?>
                            <span class="text-muted"><i class="bi bi-circle"></i> Bekliyor</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d.m.Y', strtotime($r['created_at'])); ?></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $r['id']; ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if (!$r['telegram_sent']): ?>
                            <form method="POST" class="d-inline me-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="send_telegram">
                                <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-info">
                                    <i class="bi bi-telegram"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                
                <!-- View Modal -->
                <div class="modal fade" id="viewModal<?php echo $r['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">İstek Detayı #<?php echo $r['id']; ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-white-50">Kullanıcı</label>
                                        <p class="mb-0"><?php echo htmlspecialchars($r['username']); ?> (<?php echo $r['email']; ?>)</p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-white-50">Bütçe</label>
                                        <p class="mb-0"><?php echo $r['budget'] ?: '-'; ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-white-50">Script Adı</label>
                                    <h5><?php echo htmlspecialchars($r['script_name']); ?></h5>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-white-50">Açıklama</label>
                                    <div class="p-3 bg-dark rounded">
                                        <?php echo nl2br(htmlspecialchars($r['description'])); ?>
                                    </div>
                                </div>
                                
                                <?php if ($r['contact_info']): ?>
                                <div class="mb-3">
                                    <label class="form-label text-white-50">İletişim Bilgisi</label>
                                    <p><?php echo htmlspecialchars($r['contact_info']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <hr class="border-secondary">
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Durum Güncelle</label>
                                        <select name="status" class="form-select form-control-dark">
                                            <option value="pending" <?php echo $r['status'] == 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                                            <option value="reviewed" <?php echo $r['status'] == 'reviewed' ? 'selected' : ''; ?>>İnceleniyor</option>
                                            <option value="completed" <?php echo $r['status'] == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                            <option value="rejected" <?php echo $r['status'] == 'rejected' ? 'selected' : ''; ?>>Reddedildi</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notları</label>
                                        <textarea name="admin_notes" class="form-control form-control-dark" rows="3"><?php echo htmlspecialchars($r['admin_notes'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Güncelle</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'templates/footer.php'; ?>