<?php
$title = 'Yeni Ticket';
$user = $auth->user();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $subject = Security::clean($_POST['subject'] ?? '');
        $department = $_POST['department'] ?? 'general';
        $message = Security::clean($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            Helper::flash('error', 'Konu ve mesaj zorunludur');
        } else {
            $ticketId = $db->insert('tickets', [
                'user_id' => $user['id'],
                'subject' => $subject,
                'department' => $department,
                'status' => 'open',
                'priority' => $_POST['priority'] ?? 'medium',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->insert('ticket_replies', [
                'ticket_id' => $ticketId,
                'user_id' => $user['id'],
                'message' => $message,
                'is_staff' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Helper::flash('success', 'Ticket oluşturuldu');
            Helper::redirect('support');
        }
    }
}

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.02)); border: 2px solid rgba(99, 102, 241, 0.3);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); border-bottom: 1px solid rgba(99, 102, 241, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0; font-weight: 800;">
                        <i class="bi bi-plus-circle me-2"></i>
                        Yeni Destek Talebi
                    </h5>
                    <a href="<?php echo Helper::url('support'); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-arrow-left"></i>
                        Geri Dön
                    </a>
                </div>
            </div>
            
            <div class="card-body" style="padding: 32px 28px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-4">
                        <label style="display: block; margin-bottom: 12px; color: var(--text-primary); font-weight: 700; font-size: 14px;">
                            <i class="bi bi-chat-left-text me-2"></i>
                            Konu
                        </label>
                        <input 
                            type="text" 
                            name="subject" 
                            placeholder="Ticket konusunu yazın..."
                            required
                            style="width: 100%; padding: 14px 16px; background: rgba(26, 26, 46, 0.6); border: 2px solid rgba(99, 102, 241, 0.3); color: var(--text-primary); border-radius: 12px; font-size: 15px; transition: all 0.3s;"
                            onfocus="this.style.borderColor='rgba(99, 102, 241, 0.6)'; this.style.background='rgba(26, 26, 46, 1)'"
                            onblur="this.style.borderColor='rgba(99, 102, 241, 0.3)'; this.style.background='rgba(26, 26, 46, 0.6)'"
                        >
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label style="display: block; margin-bottom: 12px; color: var(--text-primary); font-weight: 700; font-size: 14px;">
                                <i class="bi bi-building me-2"></i>
                                Departman
                            </label>
                            <select 
                                name="department" 
                                style="width: 100%; padding: 14px 16px; background: rgba(26, 26, 46, 0.6); border: 2px solid rgba(99, 102, 241, 0.3); color: var(--text-primary); border-radius: 12px; cursor: pointer; font-size: 15px; transition: all 0.3s;"
                                onfocus="this.style.borderColor='rgba(99, 102, 241, 0.6)'; this.style.background='rgba(26, 26, 46, 1)'"
                                onblur="this.style.borderColor='rgba(99, 102, 241, 0.3)'; this.style.background='rgba(26, 26, 46, 0.6)'"
                            >
                                <option value="general">Genel</option>
                                <option value="technical">Teknik</option>
                                <option value="billing">Ödeme</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label style="display: block; margin-bottom: 12px; color: var(--text-primary); font-weight: 700; font-size: 14px;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Öncelik
                            </label>
                            <select 
                                name="priority" 
                                style="width: 100%; padding: 14px 16px; background: rgba(26, 26, 46, 0.6); border: 2px solid rgba(99, 102, 241, 0.3); color: var(--text-primary); border-radius: 12px; cursor: pointer; font-size: 15px; transition: all 0.3s;"
                                onfocus="this.style.borderColor='rgba(99, 102, 241, 0.6)'; this.style.background='rgba(26, 26, 46, 1)'"
                                onblur="this.style.borderColor='rgba(99, 102, 241, 0.3)'; this.style.background='rgba(26, 26, 46, 0.6)'"
                            >
                                <option value="low">Düşük</option>
                                <option value="medium" selected>Orta</option>
                                <option value="high">Yüksek</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label style="display: block; margin-bottom: 12px; color: var(--text-primary); font-weight: 700; font-size: 14px;">
                            <i class="bi bi-card-text me-2"></i>
                            Mesaj
                        </label>
                        <textarea 
                            name="message" 
                            rows="8" 
                            placeholder="Sorununuzu detaylı bir şekilde açıklayın..."
                            required
                            style="width: 100%; padding: 16px; background: rgba(26, 26, 46, 0.6); border: 2px solid rgba(99, 102, 241, 0.3); color: var(--text-primary); border-radius: 12px; resize: vertical; font-size: 15px; line-height: 1.6; transition: all 0.3s;"
                            onfocus="this.style.borderColor='rgba(99, 102, 241, 0.6)'; this.style.background='rgba(26, 26, 46, 1)'"
                            onblur="this.style.borderColor='rgba(99, 102, 241, 0.3)'; this.style.background='rgba(26, 26, 46, 0.6)'"
                        ></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary" style="padding: 14px 36px; font-weight: 700; font-size: 15px;">
                            <i class="bi bi-send-fill"></i>
                            Gönder
                        </button>
                        <a href="<?php echo Helper::url('support'); ?>" class="btn" style="padding: 14px 36px; background: rgba(255,255,255,0.05); color: var(--text-primary); font-weight: 600; font-size: 15px;">
                            <i class="bi bi-x"></i>
                            İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>
