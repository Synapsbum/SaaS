<?php
$title = 'Destek Talepleri';
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
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        
        switch ($action) {
            case 'reply':
                if ($ticketId && !empty($_POST['message'])) {
                    $db->insert('ticket_replies', [
                        'ticket_id' => $ticketId,
                        'user_id' => $auth->userId(),
                        'message' => $_POST['message'],
                        'is_staff' => 1,
                        'attachments' => null,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $db->query("UPDATE tickets SET status = 'answered', updated_at = NOW() WHERE id = ?", [$ticketId]);
                    
                    Helper::flash('success', 'Cevap gönderildi');
                }
                break;
                
            case 'close':
                $db->query("UPDATE tickets SET status = 'closed', closed_at = NOW() WHERE id = ?", [$ticketId]);
                Helper::flash('success', 'Talep kapatıldı');
                break;
                
            case 'open':
                $db->query("UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = ?", [$ticketId]);
                Helper::flash('success', 'Talep yeniden açıldı');
                break;
                
            case 'delete':
                $db->query("DELETE FROM ticket_replies WHERE ticket_id = ?", [$ticketId]);
                $db->query("DELETE FROM tickets WHERE id = ?", [$ticketId]);
                Helper::flash('success', 'Talep silindi');
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Talepleri çek - tickets tablosunu kullan (support_tickets değil)
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT t.*, u.username, u.email, u.is_premium
    FROM tickets t
    JOIN users u ON t.user_id = u.id
";
if ($statusFilter != 'all') {
    $sql .= " WHERE t.status = '{$statusFilter}'";
}
$sql .= " ORDER BY 
    CASE 
        WHEN t.status = 'open' THEN 1
        WHEN t.status = 'answered' THEN 2
        WHEN t.status = 'waiting' THEN 3
        ELSE 4
    END,
    t.updated_at DESC
    LIMIT 100";

$tickets = $db->fetchAll($sql);

// İstatistikler - tickets tablosundan
$stats = [
    'open' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'open'")['total'],
    'answered' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'answered'")['total'],
    'waiting' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'waiting'")['total'],
    'closed' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'closed'")['total']
];

// Aktif ticket detayını çek
$activeTicket = null;
$replies = [];
$firstMessage = null; // İlk mesaj için

if (isset($_GET['view']) && intval($_GET['view']) > 0) {
    $ticketId = intval($_GET['view']);
    $activeTicket = $db->fetch("
        SELECT t.*, u.username, u.email 
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ", [$ticketId]);
    
    if ($activeTicket) {
        // İlk mesaj ticket_replies tablosundan çekilir (en eski kayıt)
        $firstMessage = $db->fetch("
            SELECT r.*, u.username 
            FROM ticket_replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC
            LIMIT 1
        ", [$ticketId]);
        
        // Tüm cevapları çek (ilk mesaj hariç sonrakiler)
        $replies = $db->fetchAll("
            SELECT r.*, u.username, u.is_admin 
            FROM ticket_replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC
        ", [$ticketId]);
    }
}

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-danger"><?php echo $stats['open']; ?></div>
            <div class="stat-label">Açık</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?php echo $stats['answered']; ?></div>
            <div class="stat-label">Cevaplanmış</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-info"><?php echo $stats['waiting']; ?></div>
            <div class="stat-label">Müşteri Yanıtı Bekleniyor</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-value text-secondary"><?php echo $stats['closed']; ?></div>
            <div class="stat-label">Kapalı</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="<?php echo $activeTicket ? 'col-lg-8' : 'col-12'; ?>">
        <div class="admin-card">
            <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0">Destek Talepleri</h5>
                <div class="btn-group mt-2 mt-md-0">
                    <a href="?status=all" class="btn btn-sm btn-outline-light <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Tümü</a>
                    <a href="?status=open" class="btn btn-sm btn-outline-danger <?php echo $statusFilter == 'open' ? 'active' : ''; ?>">Açık</a>
                    <a href="?status=answered" class="btn btn-sm btn-outline-warning <?php echo $statusFilter == 'answered' ? 'active' : ''; ?>">Cevaplanan</a>
                    <a href="?status=waiting" class="btn btn-sm btn-outline-info <?php echo $statusFilter == 'waiting' ? 'active' : ''; ?>">Bekleyen</a>
                    <a href="?status=closed" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter == 'closed' ? 'active' : ''; ?>">Kapalı</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Konu</th>
                            <th>Departman</th>
                            <th>Öncelik</th>
                            <th>Son Güncelleme</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): 
                            // Okunmamış mesaj sayısını hesapla (son staff cevabından sonra gelen müşteri mesajları)
                            $unreadCount = 0;
                            if ($t['status'] == 'open' || $t['status'] == 'waiting') {
                                $lastStaffReply = $db->fetch("
                                    SELECT created_at FROM ticket_replies 
                                    WHERE ticket_id = ? AND is_staff = 1 
                                    ORDER BY created_at DESC LIMIT 1
                                ", [$t['id']]);
                                
                                if ($lastStaffReply) {
                                    $unreadCount = $db->fetch("
                                        SELECT COUNT(*) as total FROM ticket_replies 
                                        WHERE ticket_id = ? AND is_staff = 0 AND created_at > ?
                                    ", [$t['id'], $lastStaffReply['created_at']])['total'];
                                }
                            }
                        ?>
                        <tr class="<?php echo $unreadCount > 0 ? 'table-warning' : ''; ?>">
                            <td>#<?php echo $t['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($t['username']); ?></strong>
                                <?php if ($t['is_premium']): ?>
                                    <i class="bi bi-star-fill text-warning small"></i>
                                <?php endif; ?>
                                <div class="small text-muted"><?php echo $t['email']; ?></div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($t['subject']); ?></strong>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $unreadCount; ?> yeni</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($t['department']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $t['priority'] == 'urgent' ? 'danger' : ($t['priority'] == 'high' ? 'warning' : ($t['priority'] == 'medium' ? 'info' : 'secondary')); 
                                ?>">
                                    <?php echo $t['priority'] == 'urgent' ? 'Acil' : ($t['priority'] == 'high' ? 'Yüksek' : ($t['priority'] == 'medium' ? 'Orta' : 'Düşük')); ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($t['updated_at'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $t['status'] == 'open' ? 'danger' : 
                                         ($t['status'] == 'answered' ? 'warning' : 
                                         ($t['status'] == 'waiting' ? 'info' : 'secondary')); 
                                ?>">
                                    <?php echo $t['status'] == 'open' ? 'Açık' : ($t['status'] == 'answered' ? 'Cevaplandı' : ($t['status'] == 'waiting' ? 'Bekliyor' : 'Kapalı')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="?view=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary me-1">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if ($t['status'] != 'closed'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="close">
                                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary me-1" onclick="return confirm('Kapatmak istediğinize emin misiniz?')">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="open">
                                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success me-1">
                                            <i class="bi bi-unlock"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                Destek talebi bulunamadı
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($activeTicket): ?>
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Talep Detayı #<?php echo $activeTicket['id']; ?></h5>
            </div>
            <div class="admin-card-body">
                <div class="mb-3 pb-3 border-bottom border-secondary">
                    <strong>Konu:</strong> <?php echo htmlspecialchars($activeTicket['subject']); ?>
                </div>
                <div class="mb-3 pb-3 border-bottom border-secondary">
                    <strong>Kullanıcı:</strong> <?php echo htmlspecialchars($activeTicket['username']); ?>
                    <div class="small text-muted"><?php echo $activeTicket['email']; ?></div>
                </div>
                <div class="mb-3 pb-3 border-bottom border-secondary">
                    <strong>Oluşturulma:</strong> <?php echo date('d.m.Y H:i', strtotime($activeTicket['created_at'])); ?>
                </div>
                
                <div class="conversation mb-4" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($firstMessage): ?>
                    <div class="alert alert-secondary mb-3">
                        <strong><?php echo htmlspecialchars($firstMessage['username']); ?>:</strong><br>
                        <?php echo nl2br(htmlspecialchars($firstMessage['message'])); ?>
                        <div class="small text-muted mt-2"><?php echo date('d.m.Y H:i', strtotime($firstMessage['created_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // İlk mesajı atla, sadece cevapları göster
                    $skipFirst = true;
                    foreach ($replies as $r): 
                        if ($skipFirst) {
                            $skipFirst = false;
                            continue;
                        }
                    ?>
                        <?php if ($r['is_staff']): ?>
                        <div class="alert alert-primary mb-3 ms-4">
                            <strong>Destek Ekibi:</strong><br>
                            <?php echo nl2br(htmlspecialchars($r['message'])); ?>
                            <div class="small text-muted mt-2"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-secondary mb-3">
                            <strong><?php echo htmlspecialchars($r['username']); ?>:</strong><br>
                            <?php echo nl2br(htmlspecialchars($r['message'])); ?>
                            <div class="small text-muted mt-2"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($activeTicket['status'] != 'closed'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="ticket_id" value="<?php echo $activeTicket['id']; ?>">
                    
                    <div class="mb-3">
                        <textarea name="message" class="form-control form-control-dark" rows="4" placeholder="Cevabınızı yazın..." required></textarea>
                    </div>
                    <button type="submit" class="btn-admin btn-primary-custom w-100">
                        <i class="bi bi-send"></i> Gönder
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-secondary">
                    <i class="bi bi-lock-fill me-2"></i> Bu talep kapatılmıştır.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require 'templates/footer.php'; ?>