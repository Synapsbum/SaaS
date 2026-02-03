<?php
$title = 'Script Satın Al';
$user = $auth->user();
$db = Database::getInstance();

$scriptId = $id ?? 0;
$script = $db->fetch("SELECT * FROM scripts WHERE id = ? AND status = 'active'", [$scriptId]);

if (!$script) {
    Helper::flash('error', 'Script bulunamadı');
    Helper::redirect('scripts');
}

// Paketleri çek
$packages = $db->fetchAll("SELECT * FROM script_packages WHERE script_id = ? ORDER BY duration_days", [$scriptId]);

// İndirim kodu işlemi
$discount = 0;
$couponCode = $_POST['coupon'] ?? '';
if ($couponCode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $coupon = $db->fetch("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (valid_until IS NULL OR valid_until > NOW()) AND (max_uses IS NULL OR used_count < max_uses)", [$couponCode]);
    if ($coupon) {
        $discount = $coupon['discount_percent'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['package_id'])) {
    if (!Security::validateToken($_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $packageId = $_POST['package_id'];
        $package = $db->fetch("SELECT * FROM script_packages WHERE id = ? AND script_id = ?", [$packageId, $scriptId]);
        
        if ($package) {
            $price = $package['price_usdt'];
            if ($discount > 0) {
                $price = $price * (100 - $discount) / 100;
            }
            
            // Premium indirimi
            if ($user['is_premium']) {
                $price = $price * 0.85; // %15 indirim
            }
            
            if ($user['balance'] < $price) {
                Helper::flash('error', 'Yetersiz bakiye! Lütfen bakiye yükleyin.');
                Helper::redirect('payment');
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Bakiye düş
                    $db->query("UPDATE users SET balance = balance - ? WHERE id = ?", [$price, $user['id']]);
                    
                    // Kiralama oluştur
                    $rentalId = $db->insert('rentals', [
                        'user_id' => $user['id'],
                        'script_id' => $scriptId,
                        'package_id' => $packageId,
                        'status' => 'setup_domain',
                        'price_paid' => $price,
                        'duration_hours' => $package['duration_days'] * 24,
                        'purchased_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Kupon kullanımı
                    if ($discount > 0 && isset($coupon['id'])) {
                        $db->insert('coupon_usages', [
                            'coupon_id' => $coupon['id'],
                            'user_id' => $user['id'],
                            'rental_id' => $rentalId,
                            'discount_amount' => $package['price_usdt'] - $price
                        ]);
                        $db->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?", [$coupon['id']]);
                    }
                    
                    $db->commit();
                    
                    Helper::flash('success', 'Satın alma başarılı! Şimdi kurulumu tamamlayın.');
                    Helper::redirect('rental/setup/' . $rentalId);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    error_log("Purchase error: " . $e->getMessage());
                    Helper::flash('error', 'Satın alma sırasında hata oluştu');
                }
            }
        }
    }
}

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.02)); border: 2px solid rgba(99, 102, 241, 0.3);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); border-bottom: 1px solid rgba(99, 102, 241, 0.3);">
                <h5 style="margin: 0; font-weight: 800; color: var(--text-primary);">
                    <i class="bi bi-box-seam me-2"></i>
                    <?php echo $script['name']; ?> - Paket Seçimi
                </h5>
            </div>
            <div class="card-body" style="padding: 32px 28px;">
                <p style="color: var(--text-secondary); margin-bottom: 32px; font-size: 15px; line-height: 1.6;">
                    <?php echo $script['description']; ?>
                </p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <h6 style="margin-bottom: 24px; color: var(--text-primary); font-weight: 800; font-size: 16px;">
                        <i class="bi bi-box me-2"></i>
                        Kiralama Paketi Seçin
                    </h6>
                    
                    <div class="row g-3 mb-4">
                        <?php foreach ($packages as $pkg): 
                            $price = $pkg['price_usdt'];
                            if ($user['is_premium']) $price *= 0.85;
                        ?>
                        <div class="col-md-4">
                            <label class="form-check-label w-100" for="pkg<?php echo $pkg['id']; ?>" style="cursor: pointer; margin: 0;">
                                <input class="form-check-input" type="radio" name="package_id" value="<?php echo $pkg['id']; ?>" id="pkg<?php echo $pkg['id']; ?>" required style="position: absolute; opacity: 0; pointer-events: none;">
                                <div class="package-option" style="padding: 24px; background: rgba(26, 26, 46, 0.4); border-radius: 16px; border: 2px solid var(--border-color); transition: all 0.3s; text-align: center;">
                                    <div style="font-size: 48px; font-weight: 900; margin-bottom: 8px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                        <?php echo $pkg['duration_days']; ?>
                                    </div>
                                    <div style="font-size: 14px; color: var(--text-primary); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">GÜN</div>
                                    <div style="font-size: 24px; font-weight: 800; color: var(--success); margin-bottom: 4px;">
                                        <?php echo Helper::money($price); ?>
                                    </div>
                                    <?php if ($user['is_premium']): ?>
                                    <div style="font-size: 12px; text-decoration: line-through; color: var(--text-muted);">
                                        <?php echo Helper::money($pkg['price_usdt']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mb-4">
                        <label style="display: block; margin-bottom: 12px; color: var(--text-primary); font-weight: 700; font-size: 14px;">
                            <i class="bi bi-ticket-perforated me-2"></i>
                            İndirim Kodu (İsteğe Bağlı)
                        </label>
                        <div style="display: flex; gap: 12px;">
                            <input type="text" name="coupon" placeholder="KOD123" value="<?php echo $couponCode; ?>" style="flex: 1; padding: 14px 16px; background: rgba(26, 26, 46, 0.6); border: 2px solid rgba(99, 102, 241, 0.3); color: var(--text-primary); border-radius: 12px; font-size: 15px;">
                            <button type="submit" class="btn" style="padding: 14px 24px; background: rgba(99, 102, 241, 0.1); color: var(--primary-light); border: 2px solid rgba(99, 102, 241, 0.3); font-weight: 600;">
                                Uygula
                            </button>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div style="margin-top: 12px; padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; color: var(--success); font-weight: 600; display: flex; align-items: center; gap: 8px;">
                            <i class="bi bi-check-circle-fill"></i>
                            %<?php echo $discount; ?> indirim uygulandı!
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 2px solid rgba(99, 102, 241, 0.2); padding-top: 24px;">
                        <div>
                            <small style="color: var(--text-muted); display: block; margin-bottom: 6px;">Mevcut Bakiye:</small>
                            <div style="font-size: 24px; font-weight: 900; background: linear-gradient(135deg, var(--success), #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                <?php echo Helper::money($user['balance']); ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success" style="padding: 16px 40px; font-size: 18px; font-weight: 700;">
                            <i class="bi bi-cart-check-fill"></i>
                            Satın Al
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <?php if ($user['is_premium']): ?>
        <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); border: 2px solid rgba(16, 185, 129, 0.3);">
            <div class="card-body" style="padding: 32px 28px; text-align: center;">
                <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);">
                    <i class="bi bi-percent" style="font-size: 40px; color: white;"></i>
                </div>
                
                <div style="margin-bottom: 8px;">
                    <span style="display: inline-block; padding: 8px 20px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 24px; font-weight: 800; font-size: 12px; color: white; letter-spacing: 0.5px;">
                        PREMIUM AKTİF
                    </span>
                </div>
                
                <h3 style="font-size: 48px; font-weight: 900; margin: 20px 0; background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1;">
                    15%
                </h3>
                
                <p style="color: var(--text-primary); font-weight: 600; font-size: 15px; margin-bottom: 24px;">
                    İndirim Uygulanıyor
                </p>
                
                <div style="padding: 20px; background: rgba(16, 185, 129, 0.15); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 12px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 20px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600;">Tüm Scriptlerde Geçerli</span>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 20px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600;">Otomatik Uygulanır</span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(217, 119, 6, 0.02)); border: 2px solid rgba(245, 158, 11, 0.3);">
            <div class="card-body" style="padding: 32px 28px; text-align: center;">
                <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(245, 158, 11, 0.4);">
                    <i class="bi bi-star-fill" style="font-size: 40px; color: white;"></i>
                </div>
                
                <h4 style="margin-bottom: 16px; color: var(--text-primary); font-weight: 800; font-size: 20px;">
                    Premium Olun, %15 Kazanın
                </h4>
                
                <p style="color: var(--text-secondary); margin-bottom: 24px; font-size: 14px; line-height: 1.6;">
                    Premium üyeler tüm scriptlerde %15 indirim kazanır
                </p>
                
                <div style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--warning); font-size: 18px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">%15 İndirim</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--warning); font-size: 18px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">Telegram Destek</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--warning); font-size: 18px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">Öncelikli Hizmet</span>
                    </div>
                </div>
                
                <a href="<?php echo Helper::url('premium'); ?>" class="btn btn-warning w-100" style="padding: 14px; font-weight: 700; font-size: 15px;">
                    <i class="bi bi-star-fill"></i>
                    Premium'a Yükselt
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.form-check-input:checked ~ .package-option,
input[name="package_id"]:checked ~ .package-option {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(139, 92, 246, 0.15)) !important;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4) !important;
    transform: scale(1.05);
}

.package-option:hover {
    border-color: rgba(99, 102, 241, 0.5);
    transform: translateY(-4px);
}

label:has(input:checked) .package-option {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(139, 92, 246, 0.15)) !important;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4) !important;
    transform: scale(1.05);
}
</style>

<script>
// Paket seçimi ve görsel güncelleme
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="package_id"]');
    
    // İlk paketi seç
    if (radios.length > 0 && !document.querySelector('input[name="package_id"]:checked')) {
        radios[0].checked = true;
        updateSelection(radios[0]);
    }
    
    // Her radio için change event
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateSelection(this);
        });
        
        // Label tıklamasını dinle
        const label = radio.closest('label');
        if (label) {
            label.addEventListener('click', function(e) {
                // Radio'yu seç
                radio.checked = true;
                updateSelection(radio);
            });
        }
    });
    
    function updateSelection(selectedRadio) {
        // Tüm kartları normal hale getir
        document.querySelectorAll('.package-option').forEach(option => {
            option.style.borderColor = 'var(--border-color)';
            option.style.background = 'rgba(26, 26, 46, 0.4)';
            option.style.boxShadow = 'none';
            option.style.transform = 'scale(1)';
        });
        
        // Seçili kartı highlight et
        const selectedOption = selectedRadio.parentElement.querySelector('.package-option');
        if (selectedOption) {
            selectedOption.style.borderColor = 'var(--primary)';
            selectedOption.style.background = 'linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(139, 92, 246, 0.15))';
            selectedOption.style.boxShadow = '0 8px 24px rgba(99, 102, 241, 0.4)';
            selectedOption.style.transform = 'scale(1.05)';
        }
    }
});
</script>

<?php require 'templates/footer.php'; ?>
