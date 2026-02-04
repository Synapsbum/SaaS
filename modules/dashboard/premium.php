<?php
$title = 'Premium Üyelik';
$user = $auth->user();
$db = Database::getInstance();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası';
    } else {
        $price = 300;
        $duration = 30;
        
        if ($user['balance'] < $price) {
            $error = 'Yetersiz bakiye! Premium satın almak için bakiye yükleyin.';
        } else {
            try {
                $db->beginTransaction();
                $db->query("UPDATE users SET balance = balance - ? WHERE id = ?", [$price, $user['id']]);
                
                $currentPremium = $user['premium_until'] && strtotime($user['premium_until']) > time() 
                    ? strtotime($user['premium_until']) 
                    : time();
                
                $newPremiumDate = date('Y-m-d H:i:s', strtotime("+$duration days", $currentPremium));
                $db->query("UPDATE users SET is_premium = 1, premium_until = ? WHERE id = ?", [$newPremiumDate, $user['id']]);
                
                $db->insert('admin_logs', [
                    'admin_id' => $user['id'],
                    'action' => 'premium_purchase',
                    'target_type' => 'user',
                    'target_id' => $user['id'],
                    'details' => json_encode(['price' => $price, 'duration' => $duration]),
                    'ip_address' => Security::getIP(),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $db->commit();
                $success = 'Premium üyeliğiniz aktif edildi! ' . date('d.m.Y', strtotime($newPremiumDate)) . ' tarihine kadar geçerli.';
                $user = $auth->user();
            } catch (Exception $e) {
                $db->rollback();
                $error = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

$benefits = [
    ['icon' => 'percent', 'title' => 'Tüm scriptlerde %15 indirim', 'desc' => 'Her script kiralamasında otomatik indirim'],
    ['icon' => 'headset', 'title' => 'Öncelikli destek hizmeti', 'desc' => 'Talepleriniz öncelikli yanıtlanır'],
    ['icon' => 'telegram', 'title' => 'Telegram anında destek', 'desc' => '7/24 Telegram üzerinden destek'],
    ['icon' => 'tag', 'title' => 'Özel indirim kodları', 'desc' => 'Premium üyelere özel kampanyalar'],
    ['icon' => 'eye-slash', 'title' => 'Yüksek hızlı Offshore Server', 'desc' => '10 Gbit/s hızlı sunuculara kurulum'],
    ['icon' => 'lightning', 'title' => 'Yeni scriptlere erken erişim', 'desc' => 'Yeni özellikleri ilk siz deneyin']
];

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row justify-content-center">
    <div class="col-lg-10">
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span><?php echo $success; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card" style="border: 3px solid #f59e0b; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.05)); box-shadow: 0 12px 40px rgba(245, 158, 11, 0.3);">
                    <div class="card-body" style="padding: 40px 32px;">
                        <div style="text-align: center; margin-bottom: 32px;">
                            <div style="width: 120px; height: 120px; margin: 0 auto 24px; background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 32px rgba(245, 158, 11, 0.5);">
                                <i class="bi bi-star-fill" style="font-size: 56px; color: #000;"></i>
                            </div>
                            <h2 style="font-size: 42px; font-weight: 900; margin-bottom: 8px; background: linear-gradient(135deg, #f59e0b, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -1px;">
                                PREMIUM
                            </h2>
                            <p style="color: var(--text-secondary); font-size: 16px; margin: 0;">30 Günlük Premium Üyelik</p>
                        </div>

                        <div style="text-align: center; padding: 32px 24px; background: rgba(245, 158, 11, 0.15); border-radius: 16px; margin-bottom: 32px; border: 2px solid rgba(245, 158, 11, 0.3);">
                            <div style="font-size: 72px; font-weight: 900; margin-bottom: 8px; background: linear-gradient(135deg, #f59e0b, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1;">
                                300
                            </div>
                            <div style="font-size: 24px; font-weight: 700; color: #f59e0b;">USDT</div>
                            <div style="font-size: 14px; color: var(--text-muted); margin-top: 8px;">tek seferlik ödeme</div>
                        </div>
                        
                        <?php if ($user['is_premium'] && strtotime($user['premium_until']) > time()): ?>
                        <div style="text-align: center; padding: 24px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1)); border-radius: 12px; border: 2px solid #10b981; margin-bottom: 20px;">
                            <div class="premium-active-badge" style="margin-bottom: 16px;">
                                <i class="bi bi-check-circle-fill"></i>
                                PREMIUM AKTİF
                            </div>
                            <div style="font-size: 13px; color: rgba(255,255,255,0.6); margin-bottom: 8px;">Bitiş Tarihi</div>
                            <div style="font-size: 20px; font-weight: 800; color: #10b981;">
                                <i class="bi bi-calendar-check"></i>
                                <?php echo date('d.m.Y H:i', strtotime($user['premium_until'])); ?>
                            </div>
                        </div>
                        
                        <?php if (strtotime($user['premium_until']) - time() < 7 * 86400): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" class="btn btn-warning w-100" style="padding: 16px; font-size: 18px; font-weight: 700;">
                                <i class="bi bi-arrow-repeat"></i>
                                Süreyi Uzat
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div style="padding: 20px; background: rgba(26, 26, 46, 0.6); border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: var(--text-secondary); font-size: 14px;">Mevcut Bakiyeniz</span>
                                    <span style="font-size: 20px; font-weight: 800; background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                        <?php echo Helper::money($user['balance']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($user['balance'] < 300): ?>
                            <div style="padding: 16px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; border-left: 4px solid #f59e0b; margin-bottom: 16px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <i class="bi bi-exclamation-circle" style="font-size: 24px; color: #f59e0b;"></i>
                                    <span style="color: #f59e0b; font-weight: 600;">Yetersiz bakiye</span>
                                </div>
                            </div>
                            <a href="<?php echo Helper::url('payment'); ?>" class="btn btn-success w-100" style="padding: 16px; font-size: 18px; font-weight: 700;">
                                <i class="bi bi-plus-circle"></i>
                                Bakiye Yükle
                            </a>
                            <?php else: ?>
                            <button type="submit" class="btn btn-warning w-100" style="padding: 16px; font-size: 18px; font-weight: 700;" onclick="return confirm('300 USDT karşılığında Premium üyelik almak istiyor musunuz?')">
                                <i class="bi bi-star-fill"></i>
                                Premium Satın Al
                            </button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-gift me-2"></i>Premium Avantajlar</h5>
                    </div>
                    <div class="card-body" style="padding: 32px 24px;">
                        <div style="display: grid; gap: 20px;">
                            <?php foreach ($benefits as $benefit): ?>
                            <div style="padding: 20px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.02)); border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2); transition: all 0.3s;">
                                <div style="display: flex; align-items: flex-start; gap: 16px;">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1, #ec4899); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="bi bi-<?php echo $benefit['icon']; ?>" style="font-size: 24px; color: white;"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <h6 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: var(--text-primary);">
                                            <?php echo $benefit['title']; ?>
                                        </h6>
                                        <p style="margin: 0; font-size: 14px; color: var(--text-secondary);">
                                            <?php echo $benefit['desc']; ?>
                                        </p>
                                    </div>
                                    <i class="bi bi-check-circle-fill" style="font-size: 24px; color: #10b981;"></i>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); border-bottom: 1px solid rgba(99, 102, 241, 0.3);">
                        <h5 style="margin: 0;"><i class="bi bi-bar-chart me-2"></i>Normal vs Premium</h5>
                    </div>
                    <div class="card-body" style="padding: 24px;">
                        <div style="display: grid; gap: 16px;">
                            <div style="padding: 20px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; align-items: center;">
                                <div style="font-weight: 700; color: var(--text-primary);">
                                    <i class="bi bi-percent me-2"></i>
                                    Script İndirimi
                                </div>
                                <div style="text-align: center; color: var(--text-muted);">-</div>
                                <div style="text-align: center;">
                                    <span class="badge badge-warning" style="font-size: 14px; padding: 8px 16px;">%15</span>
                                </div>
                            </div>
                            
                            <div style="padding: 20px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; align-items: center;">
                                <div style="font-weight: 700; color: var(--text-primary);">
                                    <i class="bi bi-headset me-2"></i>
                                    Destek
                                </div>
                                <div style="text-align: center; color: var(--text-muted);">Ticket</div>
                                <div style="text-align: center; color: #f59e0b; font-weight: 700;">Ticket + Telegram</div>
                            </div>
                            
                            <div style="padding: 20px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; align-items: center;">
                                <div style="font-weight: 700; color: var(--text-primary);">
                                    <i class="bi bi-clock me-2"></i>
                                    Yanıt Süresi
                                </div>
                                <div style="text-align: center; color: var(--text-muted);">24 saat</div>
                                <div style="text-align: center;">
                                    <span class="badge badge-success" style="font-size: 14px; padding: 8px 16px;">Anında</span>
                                </div>
                            </div>
                            
                            <div style="padding: 20px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; align-items: center;">
                                <div style="font-weight: 700; color: var(--text-primary);">
                                    <i class="bi bi-tag me-2"></i>
                                    Özel Kodlar
                                </div>
                                <div style="text-align: center;">
                                    <i class="bi bi-x-lg" style="font-size: 24px; color: #ef4444;"></i>
                                </div>
                                <div style="text-align: center;">
                                    <i class="bi bi-check-lg" style="font-size: 24px; color: #10b981;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>
