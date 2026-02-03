<?php
$title = 'Bakiye Yükle';
$user = $auth->user();
$db = Database::getInstance();

require_once __DIR__ . '/Cryptomus.php';

$cryptomus = new CryptomusPayment();
$error = '';
$paymentUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    if (!Security::validateToken($_POST['csrf_token'])) {
        $error = 'Güvenlik hatası';
    } else {
        $amount = floatval($_POST['amount']);
        if ($amount < 10) {
            $error = 'Minimum 10 USDT yükleyebilirsiniz';
        } else {
            $result = $cryptomus->createPayment(
                $user['id'], 
                $amount,
                Helper::url('payment/success'),
                Helper::url('payment/callback')
            );
            
            if ($result['success']) {
                $paymentUrl = $result['payment_url'];
                header('Location: ' . $paymentUrl);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Son ödemeler
$payments = $db->fetchAll("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$user['id']]);

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <!-- Ana Bakiye Kartı -->
        <div class="card mb-4" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3);">
            <div class="card-body text-center" style="padding: 40px 24px;">
                <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: linear-gradient(135deg, var(--success), #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);">
                    <i class="bi bi-wallet2" style="font-size: 36px; color: white;"></i>
                </div>
                <h2 style="font-size: 48px; font-weight: 800; margin-bottom: 8px; background: linear-gradient(135deg, var(--success), #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <?php echo Helper::money($user['balance']); ?>
                </h2>
                <p style="color: var(--text-secondary); font-size: 16px; margin: 0;">Mevcut Bakiyeniz</p>
            </div>
        </div>

        <!-- Bakiye Yükleme Kartı -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle me-2"></i>USDT Bakiye Yükle</h5>
            </div>
            <div class="card-body" style="padding: 32px 24px;">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-4">
                        <label style="display: block; margin-bottom: 12px; color: var(--text-secondary); font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Yüklenecek Miktar
                        </label>
                        <div style="position: relative;">
                            <input 
                                type="number" 
                                name="amount" 
                                class="form-control" 
                                placeholder="100" 
                                min="10" 
                                step="0.01" 
                                required
                                style="padding: 16px 80px 16px 20px; font-size: 24px; font-weight: 700; background: rgba(26, 26, 46, 0.6); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-primary); text-align: center;">
                            <span style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800; font-size: 18px;">
                                USDT
                            </span>
                        </div>
                        <div style="margin-top: 8px; color: var(--text-muted); font-size: 13px; display: flex; align-items: center; gap: 6px;">
                            <i class="bi bi-info-circle"></i>
                            Minimum yükleme: 10 USDT
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100" style="padding: 16px; font-size: 18px; font-weight: 700;">
                        <i class="bi bi-currency-dollar"></i>
                        Ödemeye Geç
                    </button>
                </form>
                
                <div style="margin-top: 32px; padding: 20px; background: rgba(26, 26, 46, 0.4); border-radius: 12px; border: 1px solid rgba(6, 182, 212, 0.2);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(8, 145, 178, 0.1)); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-shield-check" style="font-size: 24px; color: var(--info);"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px; font-size: 14px;">Güvenli Ödeme</div>
                            <div style="color: var(--text-secondary); font-size: 13px;">Ödemeler Cryptomus güvencesiyle USDT (TRC20) olarak işlenir</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son İşlemler Kartı -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="bi bi-clock-history me-2"></i>Son İşlemler</h5>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if ($payments): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="bi bi-calendar me-2"></i>Tarih</th>
                                <th><i class="bi bi-cash me-2"></i>Miktar</th>
                                <th><i class="bi bi-check-circle me-2"></i>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo date('d.m.Y', strtotime($pay['created_at'])); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo date('H:i', strtotime($pay['created_at'])); ?></div>
                                </td>
                                <td>
                                    <strong style="font-size: 16px; background: linear-gradient(135deg, var(--success), #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                        <?php echo Helper::money($pay['amount_usdt']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($pay['status'] == 'paid'): ?>
                                    <span class="badge badge-success">
                                        <i class="bi bi-check-circle-fill"></i>
                                        Ödendi
                                    </span>
                                    <?php elseif ($pay['status'] == 'pending'): ?>
                                    <span class="badge badge-warning">
                                        <i class="bi bi-clock"></i>
                                        Bekliyor
                                    </span>
                                    <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="bi bi-x-circle"></i>
                                        Başarısız
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding: 60px 20px;">
                    <div class="empty-icon" style="width: 100px; height: 100px; margin: 0 auto 20px;">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <h4 style="font-size: 20px;">Henüz İşlem Yok</h4>
                    <p>İlk bakiye yüklemenizi yapın ve işlemlerinizi burada görün</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--success) !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
    background: rgba(26, 26, 46, 0.8) !important;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 0.5;
}
</style>

<?php require 'templates/footer.php'; ?>
