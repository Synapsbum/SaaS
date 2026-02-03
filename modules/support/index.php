<?php
$title = 'Destek';
$user = $auth->user();
$db = Database::getInstance();

$tickets = $db->fetchAll("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC", [$user['id']]);

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 style="margin: 0;"><i class="bi bi-ticket-perforated me-2"></i>Destek Taleplerim</h5>
                <a href="<?php echo Helper::url('support/new'); ?>" class="btn btn-primary" style="padding: 10px 24px; font-weight: 600;">
                    <i class="bi bi-plus-circle"></i>
                    Yeni Ticket
                </a>
            </div>
            <div class="card-body">
                <?php if ($tickets): ?>
                <div class="table-responsive" style="background: transparent;">
                    <table class="table" style="background: transparent;">
                        <thead>
                            <tr style="background: rgba(26, 26, 46, 0.6);">
                                <th style="color: var(--text-primary); padding: 16px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="bi bi-chat-left-text me-2"></i>Konu
                                </th>
                                <th style="color: var(--text-primary); padding: 16px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="bi bi-building me-2"></i>Departman
                                </th>
                                <th style="color: var(--text-primary); padding: 16px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="bi bi-check-circle me-2"></i>Durum
                                </th>
                                <th style="color: var(--text-primary); padding: 16px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class="bi bi-calendar me-2"></i>Tarih
                                </th>
                            </tr>
                        </thead>
                        <tbody style="background: transparent;">
                            <?php foreach ($tickets as $ticket): ?>
                            <tr style="cursor: pointer; background: rgba(26, 26, 46, 0.3); border-bottom: 2px solid var(--border-color); transition: all 0.3s ease;" 
                                onclick="window.location='<?php echo Helper::url('support/view/' . $ticket['id']); ?>'" 
                                onmouseover="this.style.background='rgba(99, 102, 241, 0.08)'; this.style.borderColor='rgba(99, 102, 241, 0.4)'; this.style.transform='translateX(4px)'" 
                                onmouseout="this.style.background='rgba(26, 26, 46, 0.3)'; this.style.borderColor='var(--border-color)'; this.style.transform='translateX(0)'">
                                <td style="padding: 20px; background: transparent !important;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; color: white; box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3); transition: all 0.3s;">
                                            #<?php echo $ticket['id']; ?>
                                        </div>
                                        <strong style="color: var(--text-primary); font-size: 15px;"><?php echo $ticket['subject']; ?></strong>
                                    </div>
                                </td>
                                <td style="padding: 20px; background: transparent !important;">
                                    <span style="padding: 6px 14px; background: rgba(99, 102, 241, 0.15); border-radius: 8px; font-size: 13px; color: var(--primary-light); font-weight: 600; border: 1px solid rgba(99, 102, 241, 0.3);">
                                        <?php echo ucfirst($ticket['department']); ?>
                                    </span>
                                </td>
                                <td style="padding: 20px; background: transparent !important;">
                                    <span class="badge badge-<?php echo $ticket['status'] == 'open' ? 'warning' : ($ticket['status'] == 'closed' ? 'danger' : 'success'); ?>" style="padding: 8px 16px; font-size: 13px;">
                                        <?php if ($ticket['status'] == 'open'): ?>
                                        <i class="bi bi-hourglass-split"></i> Açık
                                        <?php elseif ($ticket['status'] == 'closed'): ?>
                                        <i class="bi bi-x-circle"></i> Kapalı
                                        <?php else: ?>
                                        <i class="bi bi-check-circle"></i> Cevaplandı
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td style="padding: 20px; background: transparent !important;">
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 14px;">
                                        <?php echo Helper::date($ticket['created_at']); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h4>Henüz Ticket Yok</h4>
                    <p>Yardıma mı ihtiyacınız var? İlk destek talebinizi oluşturun!</p>
                    <a href="<?php echo Helper::url('support/new'); ?>" class="btn btn-primary" style="padding: 14px 32px; font-weight: 700;">
                        <i class="bi bi-plus-circle"></i>
                        Yeni Ticket Oluştur
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <?php if ($user['is_premium']): ?>
        <div class="card" style="border: 2px solid #10b981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border-bottom: 1px solid rgba(16, 185, 129, 0.3);">
                <h5 style="margin: 0; color: #10b981; font-weight: 800;">
                    <i class="bi bi-star-fill me-2"></i>Premium Destek
                </h5>
            </div>
            <div class="card-body">
                <div style="text-align: center; padding: 24px 0;">
                    <div style="width: 96px; height: 96px; margin: 0 auto 24px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);">
                        <i class="bi bi-telegram" style="font-size: 48px; color: white;"></i>
                    </div>
                    <h5 style="margin-bottom: 12px; color: var(--text-primary); font-weight: 800; font-size: 18px;">Telegram Desteği</h5>
                    <p style="color: var(--text-secondary); margin-bottom: 24px; font-size: 14px;">Premium üyelerimiz için özel Telegram desteği</p>
                    <a href="https://t.me/<?php echo $user['telegram_username'] ?: 'support'; ?>" 
                       target="_blank" 
                       class="btn btn-success w-100"
                       style="padding: 14px; font-weight: 700; font-size: 15px;">
                        <i class="bi bi-telegram"></i>
                        Telegram'da Aç
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="border: 2px solid rgba(245, 158, 11, 0.3); background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(217, 119, 6, 0.02));">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05)); border-bottom: 1px solid rgba(245, 158, 11, 0.2);">
                <h5 style="margin: 0; font-weight: 800;">
                    <i class="bi bi-info-circle me-2"></i>Destek Bilgi
                </h5>
            </div>
            <div class="card-body">
                <div style="padding: 24px 0;">
                    <div style="text-align: center; margin-bottom: 28px;">
                        <i class="bi bi-star-fill" style="font-size: 56px; color: var(--warning);"></i>
                    </div>
                    <h5 style="margin-bottom: 16px; text-align: center; font-weight: 800; font-size: 18px;">Premium Olun</h5>
                    <p style="color: var(--text-secondary); text-align: center; margin-bottom: 24px; font-size: 14px;">Premium üyeler Telegram üzerinden hızlı destek alabilir</p>
                    <ul style="list-style: none; padding: 0; margin-bottom: 24px;">
                        <li style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: var(--text-secondary); font-size: 14px;">
                            <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 18px;"></i>
                            Telegram desteği
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: var(--text-secondary); font-size: 14px;">
                            <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 18px;"></i>
                            Öncelikli yanıt
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: var(--text-secondary); font-size: 14px;">
                            <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 18px;"></i>
                            7/24 destek
                        </li>
                    </ul>
                    <a href="<?php echo Helper::url('premium'); ?>" class="btn btn-warning w-100" style="padding: 14px; font-weight: 700; font-size: 15px;">
                        <i class="bi bi-star-fill"></i>
                        Premium'a Yükselt
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header">
                <h5 style="margin: 0; font-weight: 800;">
                    <i class="bi bi-question-circle me-2"></i>Sık Sorulanlar
                </h5>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="padding: 18px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; border-left: 4px solid var(--primary);">
                        <strong style="display: block; margin-bottom: 10px; color: var(--text-primary); font-size: 15px;">Yanıt süresi ne kadar?</strong>
                        <p style="margin: 0; font-size: 14px; color: var(--text-secondary); line-height: 1.6;">Genellikle 24 saat içinde yanıt veriyoruz.</p>
                    </div>
                    <div style="padding: 18px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; border-left: 4px solid var(--info);">
                        <strong style="display: block; margin-bottom: 10px; color: var(--text-primary); font-size: 15px;">Ticket nasıl oluşturulur?</strong>
                        <p style="margin: 0; font-size: 14px; color: var(--text-secondary); line-height: 1.6;">"Yeni Ticket" butonuna tıklayın ve formunu doldurun.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>
