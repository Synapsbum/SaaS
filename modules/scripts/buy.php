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

// İndirim kodu kontrolü (AJAX veya ayrı form ile)
$discount = 0;
$couponCode = '';
$coupon = null;

// Sadece kupon kontrolü için ayrı POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_coupon'])) {
    if (!Security::validateToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Güvenlik hatası']);
        exit;
    }
    
    $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $coupon = $db->fetch("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (valid_until IS NULL OR valid_until > NOW()) AND (max_uses IS NULL OR used_count < max_uses)", [$couponCode]);
    
    if ($coupon) {
        echo json_encode(['success' => true, 'discount' => $coupon['discount_percent'], 'code' => $couponCode]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz kupon kodu']);
    }
    exit;
}

// Ana satın alma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase']) && isset($_POST['package_id'])) {
    if (!Security::validateToken($_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $packageId = intval($_POST['package_id']);
        $package = $db->fetch("SELECT * FROM script_packages WHERE id = ? AND script_id = ?", [$packageId, $scriptId]);
        
        if (!$package) {
            Helper::flash('error', 'Paket bulunamadı');
        } else {
            $price = $package['price_usdt'];
            
            // Kupon indirimi
            $couponCode = $_POST['applied_coupon'] ?? '';
            if ($couponCode) {
                $coupon = $db->fetch("SELECT * FROM coupons WHERE code = ?", [$couponCode]);
                if ($coupon) {
                    $price = $price * (100 - $coupon['discount_percent']) / 100;
                }
            }
            
            // Premium indirimi
            if ($user['is_premium']) {
                $price = $price * 0.85;
            }
            
            // Bakiye kontrolü
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
                    if ($coupon && $couponCode) {
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

<!-- Ultra Modern Header -->
<div class="row mb-4">
    <div class="col">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <div style="width: 4px; height: 40px; background: linear-gradient(180deg, var(--primary), var(--accent)); border-radius: 4px;"></div>
            <h1 style="margin: 0; font-weight: 900; font-size: 36px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                <?php echo $script['name']; ?>
            </h1>
        </div>
        <p style="color: var(--text-secondary); font-size: 16px; margin-left: 16px;">
            Paketinizi seçin ve hemen başlayın
        </p>
    </div>
</div>

<div class="row">
    <!-- Sol: Ana Form -->
    <div class="col-lg-8 mb-4">
        <div class="card" style="border: 2px solid rgba(99, 102, 241, 0.3); background: linear-gradient(135deg, rgba(26, 26, 46, 0.8), rgba(15, 15, 30, 0.6)); overflow: hidden;">
            <!-- Script Açıklaması -->
            <div style="padding: 28px; background: rgba(26, 26, 46, 0.6); border-bottom: 2px solid rgba(99, 102, 241, 0.2); border-radius: 16px 16px 0 0;">
                <h5 style="margin: 0 0 12px 0; font-weight: 800; color: var(--text-primary); font-size: 18px;">
                    <i class="bi bi-info-circle me-2"></i>
                    Script Hakkında
                </h5>
                <p style="color: var(--text-secondary); margin: 0; font-size: 15px; line-height: 1.6;">
                    <?php echo $script['description']; ?>
                </p>
            </div>
            
            <div class="card-body" style="padding: 32px 28px; background: rgba(15, 15, 30, 0.4);">
                <form method="POST" id="purchaseForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="purchase" value="1">
                    <input type="hidden" name="applied_coupon" id="appliedCoupon" value="">
                    
                    <!-- Paket Seçimi -->
                    <div style="margin-bottom: 32px; padding: 24px; background: rgba(26, 26, 46, 0.5); border-radius: 16px; border: 2px solid rgba(99, 102, 241, 0.2);">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
                            <i class="bi bi-box" style="color: var(--primary); font-size: 20px;"></i>
                            <h6 style="margin: 0; color: var(--text-primary); font-weight: 800; font-size: 16px;">
                                Kiralama Süresi Seçin
                            </h6>
                            <span style="color: var(--danger); font-size: 18px;">*</span>
                        </div>
                        
                        <div class="row g-3">
                            <?php foreach ($packages as $pkg): 
                                $originalPrice = $pkg['price_usdt'];
                                $displayPrice = $originalPrice;
                                
                                if ($user['is_premium']) {
                                    $displayPrice = $displayPrice * 0.85;
                                }
                            ?>
                            <div class="col-md-4">
                                <label class="w-100" for="pkg<?php echo $pkg['id']; ?>" style="cursor: pointer; margin: 0;">
                                    <input class="d-none" type="radio" name="package_id" value="<?php echo $pkg['id']; ?>" id="pkg<?php echo $pkg['id']; ?>" required data-price="<?php echo $displayPrice; ?>" data-original="<?php echo $originalPrice; ?>">
                                    <div class="package-option" style="padding: 28px 20px; background: rgba(26, 26, 46, 0.7); border-radius: 16px; border: 2px solid var(--border-color); transition: all 0.3s; text-align: center; position: relative;">
                                        <div style="font-size: 56px; font-weight: 900; margin-bottom: 8px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1;">
                                            <?php echo $pkg['duration_days']; ?>
                                        </div>
                                        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">GÜN</div>
                                        <div style="height: 2px; background: linear-gradient(90deg, transparent, var(--border-color), transparent); margin-bottom: 16px;"></div>
                                        <div class="price-display" style="font-size: 26px; font-weight: 900; color: var(--success); margin-bottom: 4px;">
                                            <?php echo number_format($displayPrice, 2); ?> USDT
                                        </div>
                                        <?php if ($user['is_premium']): ?>
                                        <div style="font-size: 12px; text-decoration: line-through; color: var(--text-muted);">
                                            <?php echo number_format($originalPrice, 2); ?> USDT
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Kupon Kodu -->
                    <div style="margin-bottom: 32px; padding: 24px; background: rgba(26, 26, 46, 0.7); border-radius: 16px; border: 2px solid rgba(99, 102, 241, 0.3);">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                            <i class="bi bi-ticket-perforated" style="color: var(--primary); font-size: 20px;"></i>
                            <label style="margin: 0; color: var(--text-primary); font-weight: 700; font-size: 15px;">
                                İndirim Kodu
                            </label>
                            <span style="padding: 4px 10px; background: rgba(99, 102, 241, 0.1); border-radius: 12px; font-size: 11px; color: var(--primary); font-weight: 600;">İSTEĞE BAĞLI</span>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <input type="text" id="couponInput" placeholder="KOD123" style="flex: 1; padding: 14px 16px; background: rgba(15, 15, 30, 0.9); border: 2px solid rgba(99, 102, 241, 0.4); color: var(--text-primary); border-radius: 12px; font-size: 15px; text-transform: uppercase; font-weight: 600;">
                            <button type="button" id="applyCouponBtn" class="btn" style="padding: 14px 28px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1)); color: var(--primary-light); border: 2px solid rgba(99, 102, 241, 0.4); font-weight: 700; font-size: 14px;">
                                <i class="bi bi-check2 me-1"></i>
                                Uygula
                            </button>
                        </div>
                        <div id="couponMessage" style="margin-top: 12px; display: none;"></div>
                        <div id="couponSuccess" style="margin-top: 12px; padding: 14px 16px; background: rgba(16, 185, 129, 0.15); border-radius: 10px; color: var(--success); font-weight: 700; display: none; align-items: center; gap: 10px; border: 1px solid rgba(16, 185, 129, 0.3);">
                            <i class="bi bi-check-circle-fill" style="font-size: 20px;"></i>
                            <span id="discountText"></span>
                        </div>
                    </div>
                    
                    <!-- Özet ve Satın Al -->
                    <div style="padding: 28px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.08)); border-radius: 16px; border: 2px solid rgba(99, 102, 241, 0.4);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                            <div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Mevcut Bakiye</div>
                                <div id="userBalance" style="font-size: 32px; font-weight: 900; background: linear-gradient(135deg, var(--success), #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                    <?php echo number_format($user['balance'], 2); ?> USDT
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Ödenecek Tutar</div>
                                <div id="totalPrice" style="font-size: 38px; font-weight: 900; color: var(--text-primary); margin-bottom: 16px; line-height: 1;">
                                    <?php echo count($packages) > 0 ? number_format($packages[0]['price_usdt'] * ($user['is_premium'] ? 0.85 : 1), 2) : '0.00'; ?> USDT
                                </div>
                                <button type="submit" class="btn btn-success" style="padding: 16px 40px; font-size: 17px; font-weight: 800; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);">
                                    <i class="bi bi-cart-check-fill me-2"></i>
                                    Satın Al
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sağ: Premium Kart -->
    <div class="col-lg-4 mb-4">
        <?php if ($user['is_premium']): ?>
        <!-- Premium Aktif -->
        <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); border: 2px solid rgba(16, 185, 129, 0.4);">
            <div class="card-body" style="padding: 36px 28px; text-align: center;">
                <div style="width: 100px; height: 100px; margin: 0 auto 28px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 32px rgba(16, 185, 129, 0.5); position: relative;">
                    <i class="bi bi-percent" style="font-size: 48px; color: white;"></i>
                    <div style="position: absolute; top: -8px; right: -8px; width: 32px; height: 32px; background: linear-gradient(135deg, #fbbf24, #f59e0b); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(251, 191, 36, 0.5);">
                        <i class="bi bi-star-fill" style="font-size: 14px; color: white;"></i>
                    </div>
                </div>
                
                <div style="padding: 10px 24px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 24px; font-weight: 900; font-size: 13px; color: white; letter-spacing: 1px; display: inline-block; margin-bottom: 24px;">
                    PREMIUM AKTİF
                </div>
                
                <div style="font-size: 72px; font-weight: 900; margin-bottom: 12px; background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1;">
                    15%
                </div>
                
                <p style="color: var(--text-primary); font-weight: 700; font-size: 16px; margin-bottom: 28px;">
                    İndirim Uygulanıyor
                </p>
                
                <div style="padding: 20px; background: rgba(16, 185, 129, 0.15); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 22px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">Tüm Scriptlerde Geçerli</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 22px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">Otomatik Uygulanır</span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Premium Öneri -->
        <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05)); border: 2px solid rgba(245, 158, 11, 0.4);">
            <div class="card-body" style="padding: 36px 28px; text-align: center;">
                <div style="width: 100px; height: 100px; margin: 0 auto 28px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 32px rgba(245, 158, 11, 0.5);">
                    <i class="bi bi-star-fill" style="font-size: 48px; color: white;"></i>
                </div>
                
                <h4 style="margin-bottom: 16px; color: var(--text-primary); font-weight: 900; font-size: 22px;">
                    Premium Olun
                </h4>
                <p style="color: var(--text-secondary); margin-bottom: 28px; font-size: 15px; line-height: 1.6;">
                    Tüm scriptlerde %15 indirim kazanın
                </p>
                
                <div style="margin-bottom: 28px;">
                    <div style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(245, 158, 11, 0.1); border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <i class="bi bi-check-circle-fill" style="color: var(--warning); font-size: 20px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">%15 İndirim</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(245, 158, 11, 0.1); border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <i class="bi bi-check-circle-fill" style="color: var(--warning); font-size: 20px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">Telegram Destek</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(245, 158, 11, 0.1); border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <i class="bi bi-check-circle-fill" style="color: var(--warning); font-size: 20px;"></i>
                        <span style="color: var(--text-primary); font-weight: 600; font-size: 14px;">Öncelikli Hizmet</span>
                    </div>
                </div>
                
                <a href="<?php echo Helper::url('premium'); ?>" class="btn btn-warning w-100" style="padding: 16px; font-weight: 800; font-size: 16px; box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3);">
                    <i class="bi bi-star-fill me-2"></i>
                    Premium'a Yükselt
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.package-option {
    transition: all 0.3s ease;
}

.package-option:hover {
    border-color: rgba(99, 102, 241, 0.6) !important;
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.2);
}

.package-option.selected {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(139, 92, 246, 0.15)) !important;
    box-shadow: 0 12px 36px rgba(99, 102, 241, 0.4) !important;
    transform: translateY(-6px) scale(1.03);
}

.package-option.selected .price-display {
    color: var(--primary) !important;
    font-size: 28px !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedPackage = null;
    let couponDiscount = 0;
    let basePrice = 0;
    const isPremium = <?php echo $user['is_premium'] ? 'true' : 'false'; ?>;
    const userBalance = <?php echo $user['balance']; ?>;
    
    // İlk paketi seç
    const firstRadio = document.querySelector('input[name="package_id"]');
    if (firstRadio) {
        firstRadio.checked = true;
        selectPackage(firstRadio);
    }
    
    // Paket seçimi
    document.querySelectorAll('input[name="package_id"]').forEach(radio => {
        radio.addEventListener('change', function() {
            selectPackage(this);
        });
        
        // Label click
        radio.closest('label').addEventListener('click', function() {
            radio.checked = true;
            selectPackage(radio);
        });
    });
    
    function selectPackage(radio) {
        selectedPackage = radio;
        basePrice = parseFloat(radio.dataset.price);
        
        // Görsel güncelle
        document.querySelectorAll('.package-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        radio.closest('label').querySelector('.package-option').classList.add('selected');
        
        // Fiyat güncelle
        updateTotalPrice();
    }
    
    function updateTotalPrice() {
        if (!selectedPackage) return;
        
        let total = basePrice;
        
        // Kupon indirimi
        if (couponDiscount > 0) {
            total = total * (100 - couponDiscount) / 100;
        }
        
        // Göster
        document.getElementById('totalPrice').textContent = total.toFixed(2) + ' USDT';
        
        // Bakiye kontrolü
        const balanceEl = document.getElementById('userBalance');
        if (userBalance < total) {
            balanceEl.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            balanceEl.style.webkitBackgroundClip = 'text';
            balanceEl.style.webkitTextFillColor = 'transparent';
        } else {
            balanceEl.style.background = 'linear-gradient(135deg, var(--success), #059669)';
            balanceEl.style.webkitBackgroundClip = 'text';
            balanceEl.style.webkitTextFillColor = 'transparent';
        }
    }
    
    // Kupon uygulama - AJAX
    document.getElementById('applyCouponBtn').addEventListener('click', function() {
        const code = document.getElementById('couponInput').value.trim().toUpperCase();
        if (!code) {
            alert('Lütfen bir kupon kodu girin');
            return;
        }
        
        const btn = this;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Kontrol...';
        btn.disabled = true;
        
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        formData.append('check_coupon', '1');
        formData.append('coupon_code', code);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            
            const msgEl = document.getElementById('couponMessage');
            const successEl = document.getElementById('couponSuccess');
            
            if (data.success) {
                couponDiscount = data.discount;
                document.getElementById('appliedCoupon').value = data.code;
                
                msgEl.style.display = 'none';
                successEl.style.display = 'flex';
                document.getElementById('discountText').textContent = '%' + data.discount + ' indirim uygulandı! (' + data.code + ')';
                
                updateTotalPrice();
            } else {
                couponDiscount = 0;
                document.getElementById('appliedCoupon').value = '';
                
                successEl.style.display = 'none';
                msgEl.style.display = 'block';
                msgEl.innerHTML = '<div style="padding: 14px 16px; background: rgba(239, 68, 68, 0.15); border-radius: 10px; color: var(--danger); font-weight: 700; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(239, 68, 68, 0.3);"><i class="bi bi-x-circle-fill" style="font-size: 20px;"></i>' + data.message + '</div>';
            }
        })
        .catch(err => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            console.error(err);
        });
    });
    
    // Form submit kontrolü
    document.getElementById('purchaseForm').addEventListener('submit', function(e) {
        const selected = document.querySelector('input[name="package_id"]:checked');
        if (!selected) {
            e.preventDefault();
            alert('Lütfen bir paket seçin');
            return false;
        }
        
        // Bakiye kontrolü
        const totalPrice = parseFloat(document.getElementById('totalPrice').textContent);
        if (userBalance < totalPrice) {
            e.preventDefault();
            alert('Yetersiz bakiye! Lütfen bakiye yükleyin.');
            window.location.href = '<?php echo Helper::url('payment'); ?>';
            return false;
        }
    });
});
</script>

<?php require 'templates/footer.php'; ?>
