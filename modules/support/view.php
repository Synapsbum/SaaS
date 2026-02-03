<?php
$title = 'Ticket Detay';
$user = $auth->user();
$db = Database::getInstance();

$ticketId = $id ?? 0;
$ticket = $db->fetch("SELECT * FROM tickets WHERE id = ? AND user_id = ?", [$ticketId, $user['id']]);

if (!$ticket) {
    Helper::flash('error', 'Ticket bulunamadı');
    Helper::redirect('support');
}

$replies = $db->fetchAll("
    SELECT tr.*, u.username, u.is_admin 
    FROM ticket_replies tr
    JOIN users u ON tr.user_id = u.id
    WHERE tr.ticket_id = ?
    ORDER BY tr.created_at ASC
", [$ticketId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $message = Security::clean($_POST['message'] ?? '');
        if (!empty($message)) {
            $db->insert('ticket_replies', [
                'ticket_id' => $ticketId,
                'user_id' => $user['id'],
                'message' => $message,
                'is_staff' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->update('tickets', ['status' => 'open', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            
            Helper::flash('success', 'Cevap gönderildi');
            Helper::redirect('support/view/' . $ticketId);
        }
    }
}

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.02)); border: 2px solid rgba(99, 102, 241, 0.3);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); border-bottom: 1px solid rgba(99, 102, 241, 0.3); padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h5 style="margin: 0 0 12px 0; display: flex; align-items: center; gap: 12px;">
                            <span class="badge badge-info" style="font-size: 14px; padding: 8px 16px;">
                                #<?php echo $ticket['id']; ?>
                            </span>
                            <span style="color: var(--text-primary); font-weight: 800;"><?php echo $ticket['subject']; ?></span>
                        </h5>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                            <span style="padding: 6px 12px; background: rgba(99, 102, 241, 0.15); border-radius: 8px; font-size: 12px; color: var(--primary-light); border: 1px solid rgba(99, 102, 241, 0.3);">
                                <i class="bi bi-building me-1"></i>
                                <?php echo ucfirst($ticket['department']); ?>
                            </span>
                            <?php if ($ticket['status'] == 'open'): ?>
                            <span class="badge badge-warning">
                                <i class="bi bi-hourglass-split"></i>
                                Açık
                            </span>
                            <?php elseif ($ticket['status'] == 'closed'): ?>
                            <span class="badge badge-danger">
                                <i class="bi bi-x-circle"></i>
                                Kapalı
                            </span>
                            <?php else: ?>
                            <span class="badge badge-success">
                                <i class="bi bi-check-circle"></i>
                                Cevaplandı
                            </span>
                            <?php endif; ?>
                            <span style="color: var(--text-muted); font-size: 13px;">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo Helper::date($ticket['created_at']); ?>
                            </span>
                        </div>
                    </div>
                    <a href="<?php echo Helper::url('support'); ?>" class="btn btn-primary" style="padding: 10px 20px;">
                        <i class="bi bi-arrow-left"></i>
                        Geri Dön
                    </a>
                </div>
            </div>
            
            <div class="card-body" style="padding: 32px 24px;">
                <div style="display: flex; flex-direction: column; gap: 24px; margin-bottom: 32px;">
                    <?php foreach ($replies as $reply): ?>
                    <div style="display: flex; gap: 16px; <?php echo $reply['is_admin'] ? 'flex-direction: row;' : 'flex-direction: row-reverse;'; ?>">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: <?php echo $reply['is_admin'] ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, var(--primary), var(--accent))'; ?>; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; flex-shrink: 0; font-size: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                            <?php if ($reply['is_admin']): ?>
                            <i class="bi bi-shield-fill"></i>
                            <?php else: ?>
                            <?php echo strtoupper(substr($reply['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; max-width: 70%;">
                            <div style="padding: 20px; background: <?php echo $reply['is_admin'] ? 'linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.1))' : 'rgba(26, 26, 46, 0.6)'; ?>; border-radius: 16px; border: 2px solid <?php echo $reply['is_admin'] ? 'rgba(239, 68, 68, 0.4)' : 'rgba(99, 102, 241, 0.3)'; ?>;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                    <span style="font-weight: 800; color: var(--text-primary); font-size: 15px;">
                                        <?php echo $reply['username']; ?>
                                    </span>
                                    <?php if ($reply['is_admin']): ?>
                                    <span style="padding: 4px 10px; background: #ef4444; border-radius: 12px; font-size: 11px; font-weight: 800; color: white; letter-spacing: 0.5px;">
                                        ADMIN
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p style="margin: 0; color: var(--text-primary); line-height: 1.7; white-space: pre-wrap; font-size: 15px;"><?php echo nl2br($reply['message']); ?></p>
                                <div style="margin-top: 12px; font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                    <i class="bi bi-clock"></i>
                                    <?php echo Helper::date($reply['created_at']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($ticket['status'] != 'closed'): ?>
                <form method="POST" style="padding: 28px; background: linear-gradient(135deg, rgba(26, 26, 46, 0.6), rgba(15, 15, 30, 0.4)); border-radius: 16px; border: 2px solid rgba(99, 102, 241, 0.3);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="reply" value="1">
                    
                    <label style="display: block; margin-bottom: 16px; color: var(--text-primary); font-weight: 700; font-size: 16px;">
                        <i class="bi bi-chat-left-text me-2"></i>
                        Cevabınızı Yazın
                    </label>
                    
                    <textarea 
                        name="message" 
                        rows="5" 
                        placeholder="Mesajınızı buraya yazın..."
                        required
                        style="width: 100%; padding: 16px; background: rgba(26, 26, 46, 0.8); border: 2px solid rgba(99, 102, 241, 0.3); color: var(--text-primary); border-radius: 12px; resize: vertical; margin-bottom: 20px; font-size: 15px; transition: all 0.3s;"
                        onfocus="this.style.borderColor='rgba(99, 102, 241, 0.6)'; this.style.background='rgba(26, 26, 46, 1)'"
                        onblur="this.style.borderColor='rgba(99, 102, 241, 0.3)'; this.style.background='rgba(26, 26, 46, 0.8)'"
                    ></textarea>
                    
                    <button type="submit" class="btn btn-primary" style="padding: 14px 32px; font-size: 16px; font-weight: 700;">
                        <i class="bi bi-send-fill"></i>
                        Cevap Gönder
                    </button>
                </form>
                <?php else: ?>
                <div style="padding: 28px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.1)); border-radius: 16px; border: 2px solid rgba(239, 68, 68, 0.4); text-align: center;">
                    <i class="bi bi-lock-fill" style="font-size: 56px; color: var(--danger); margin-bottom: 20px;"></i>
                    <h5 style="margin-bottom: 12px; color: var(--text-primary); font-weight: 800;">Ticket Kapalı</h5>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 15px;">Bu ticket kapatılmış durumda. Yeni bir ticket oluşturabilirsiniz.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>
