<?php
$title = 'Ticket Yönetimi';
$db = Database::getInstance();

// Ticket işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        if ($_POST['action'] === 'reply') {
            $db->insert('ticket_replies', [
                'ticket_id' => $_POST['ticket_id'],
                'user_id' => $auth->userId(),
                'message' => Security::clean($_POST['message']),
                'is_staff' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->update('tickets', [
                'status' => 'answered',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$_POST['ticket_id']]);
            
            Helper::flash('success', 'Cevap gönderildi');
        }
        
        if ($_POST['action'] === 'close') {
            $db->update('tickets', [
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$_POST['ticket_id']]);
            Helper::flash('success', 'Ticket kapatıldı');
        }
        
        if ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM ticket_replies WHERE ticket_id = ?", [$_POST['ticket_id']]);
            $db->query("DELETE FROM tickets WHERE id = ?", [$_POST['ticket_id']]);
            Helper::flash('success', 'Ticket silindi');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Ticket detay görüntüleme
$ticketId = $_GET['view'] ?? null;
if ($ticketId) {
    $ticket = $db->fetch("
        SELECT t.*, u.username, u.email, u.telegram_username 
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?
    ", [$ticketId]);
    
    $replies = $db->fetchAll("
        SELECT tr.*, u.username, u.is_admin 
        FROM ticket_replies tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.ticket_id = ?
        ORDER BY tr.created_at ASC
    ", [$ticketId]);
}

// Ticket listesi
$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT t.*, u.username FROM tickets t JOIN users u ON t.user_id = u.id";
$params = [];

if ($statusFilter != 'all') {
    $sql .= " WHERE t.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY t.created_at DESC";

$tickets = $db->fetchAll($sql, $params);

// İstatistikler
$stats = [
    'open' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'open'")['total'],
    'answered' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'answered'")['total'],
    'closed' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE status = 'closed'")['total']
];

require 'templates/header.php';
?>

<?php if ($ticketId && $ticket): ?>
<!-- Ticket Detay -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <div>
                    <h5 class="mb-0">#<?php echo $ticket['id']; ?> - <?php echo $ticket['subject']; ?></h5>
                    <small class="text-muted">
                        <?php echo $ticket['username']; ?> | 
                        <?php echo $ticket['email']; ?> | 
                        <?php echo $ticket['telegram_username'] ? '@' . $ticket['telegram_username'] : ''; ?>
                    </small>
                </div>
                <span class="badge bg-<?php echo $ticket['status'] == 'open' ? 'warning' : ($ticket['status'] == 'answered' ? 'success' : 'secondary'); ?>">
                    <?php echo $ticket['status']; ?>
                </span>
            </div>
            <div class="card-body">
                <p><strong>Departman:</strong> <?php echo ucfirst($ticket['department']); ?></p>
                <p><strong>Öncelik:</strong> <?php echo ucfirst($ticket['priority']); ?></p>
                <p><strong>Tarih:</strong> <?php echo Helper::date($ticket['created_at']); ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Mesajlar</div>
            <div class="card-body">
                <?php foreach ($replies as $reply): ?>
                <div class="mb-3 p-3 rounded <?php echo $reply['is_staff'] ? 'bg-primary bg-opacity-25' : 'bg-dark'; ?>">
                    <div class="d-flex justify-content-between mb-2">
                        <strong>
                            <?php echo $reply['username']; ?>
                            <?php if ($reply['is_admin']): ?>
                            <span class="badge bg-danger">Admin</span>
                            <?php endif; ?>
                        </strong>
                        <small class="text-muted"><?php echo Helper::date($reply['created_at']); ?></small>
                    </div>
                    <p class="mb-0"><?php echo nl2br($reply['message']); ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php if ($ticket['status'] != 'closed'): ?>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    
                    <div class="mb-3">
                        <textarea name="message" class="form-control" rows="4" placeholder="Cevabınızı yazın..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Gönder</button>
                    <button type="submit" name="action" value="close" class="btn btn-secondary">Kapat ve Gönder</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">İşlemler</div>
            <div class="card-body">
                <?php if ($ticket['status'] != 'closed'): ?>
                <form method="POST" class="mb-2">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <button type="submit" class="btn btn-warning w-100">Ticketı Kapat</button>
                </form>
                <?php endif; ?>
                
                <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <button type="submit" class="btn btn-danger w-100">Sil</button>
                </form>
                
                <hr>
                
                <a href="<?php echo Helper::url('admin/tickets'); ?>" class="btn btn-secondary w-100">Listeye Dön</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Ticket Listesi -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo $stats['open']; ?></h3>
            <small>Açık Ticket</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo $stats['answered']; ?></h3>
            <small>Cevaplanan</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <h3><?php echo $stats['closed']; ?></h3>
            <small>Kapalı</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Ticketler</h6>
        <div>
            <a href="?status=all" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Tümü</a>
            <a href="?status=open" class="btn btn-sm btn-outline-warning <?php echo $statusFilter == 'open' ? 'active' : ''; ?>">Açık</a>
            <a href="?status=answered" class="btn btn-sm btn-outline-success <?php echo $statusFilter == 'answered' ? 'active' : ''; ?>">Cevaplanan</a>
            <a href="?status=closed" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter == 'closed' ? 'active' : ''; ?>">Kapalı</a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-dark table-sm mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Konu</th>
                    <th>Kullanıcı</th>
                    <th>Departman</th>
                    <th>Durum</th>
                    <th>Tarih</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>#<?php echo $t['id']; ?></td>
                    <td><?php echo $t['subject']; ?></td>
                    <td><?php echo $t['username']; ?></td>
                    <td><?php echo ucfirst($t['department']); ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $t['status'] == 'open' ? 'warning' : 
                                 ($t['status'] == 'answered' ? 'success' : 'secondary'); 
                        ?>"><?php echo $t['status']; ?></span>
                    </td>
                    <td><?php echo Helper::date($t['created_at']); ?></td>
                    <td>
                        <a href="?view=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary">Görüntüle</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require 'templates/footer.php'; ?>