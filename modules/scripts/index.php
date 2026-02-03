<?php
$title = 'Scriptler';
$user = $auth->user();
$db = Database::getInstance();

// Kullanıcının tüm kiralamalarını çek
$userRentals = $db->fetchAll("
    SELECT r.*, s.id as script_id, s.name as script_name, s.slug, 
           s.description, s.image, s.status as script_status,
           d.domain, r.expires_at, r.status as rental_status, 
           r.price_paid, r.purchased_at, r.activated_at
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.user_id = ? AND r.status != 'cancelled'
    ORDER BY r.purchased_at DESC
", [$user['id']]);

// Kiralanmış scriptleri diziye çevir
$rentalByScript = [];
foreach ($userRentals as $rental) {
    if (!isset($rentalByScript[$rental['script_id']])) {
        $rentalByScript[$rental['script_id']] = $rental;
    }
}

// TÜM aktif scriptleri çek (herkes için görünür)
$scripts = $db->fetchAll("
    SELECT s.*, 
           (SELECT COUNT(*) FROM script_packages WHERE script_id = s.id) as package_count,
           (SELECT COUNT(*) FROM script_domains WHERE script_id = s.id AND status = 'available') as available_domains,
           (SELECT MIN(price_usdt) FROM script_packages WHERE script_id = s.id) as min_price
    FROM scripts s 
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
");

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="row mb-4">
    <div class="col">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <div style="width: 4px; height: 40px; background: linear-gradient(180deg, var(--primary), var(--accent)); border-radius: 4px;"></div>
            <h1 style="margin: 0; font-weight: 900; font-size: 36px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                Mevcut Scriptler
            </h1>
        </div>
        <p style="color: var(--text-secondary); font-size: 16px; margin-left: 16px;">İhtiyacınıza uygun scripti seçin ve hemen kiralamaya başlayın.</p>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
    <?php foreach ($scripts as $script): 
        // Bu kullanıcı bu scripti satın aldı mı?
        $isRented = isset($rentalByScript[$script['id']]);
        $rental = $rentalByScript[$script['id']] ?? null;
    ?>
    <div class="col">
        <?php if ($isRented): 
            // SATIN ALINMIŞ SCRIPT - Sadece bu kullanıcı için özel gösterim
            $usernamePrefix = substr($user['username'], 0, 4);
            $maskedUsername = $usernamePrefix . str_repeat('•', max(0, strlen($user['username']) - 4));
            
            $statusClass = '';
            $statusText = '';
            $statusIcon = '';
            $dateText = '';
            $dateLabel = '';
            
            if ($rental['rental_status'] === 'active' && $rental['expires_at']) {
                $statusClass = 'success';
                $statusText = 'AKTİF';
                $statusIcon = 'check-circle-fill';
                $daysLeft = ceil((strtotime($rental['expires_at']) - time()) / 86400);
                $dateLabel = 'Kalan Süre';
                $dateText = $daysLeft . ' gün';
            } elseif (in_array($rental['rental_status'], ['setup_domain', 'setup_config', 'setup_deploy', 'pending'])) {
                $statusClass = 'warning';
                $statusText = 'KURULUMDA';
                $statusIcon = 'hourglass-split';
                $dateLabel = 'Satın Alım';
                $dateText = date('d.m.Y', strtotime($rental['purchased_at']));
            } elseif ($rental['rental_status'] === 'expired') {
                $statusClass = 'danger';
                $statusText = 'SÜRESİ DOLDU';
                $statusIcon = 'x-circle';
                $dateLabel = 'Bitiş';
                $dateText = date('d.m.Y', strtotime($rental['expires_at']));
            } else {
                $statusClass = 'info';
                $statusText = 'BEKLİYOR';
                $statusIcon = 'clock';
                $dateLabel = 'Satın Alım';
                $dateText = date('d.m.Y', strtotime($rental['purchased_at']));
            }
        ?>
        <!-- SATIN ALINMIŞ SCRIPT KARTI -->
        <div class="card" style="height: 100%; border: 3px solid var(--<?php echo $statusClass; ?>); background: linear-gradient(135deg, rgba(var(--<?php echo $statusClass; ?>-rgb, 16, 185, 129), 0.1), rgba(var(--<?php echo $statusClass; ?>-rgb, 5, 150, 105), 0.05)); position: relative; overflow: hidden;">
            <!-- Üst Sağ Status Badge -->
            <div style="position: absolute; top: 16px; right: 16px; z-index: 10;">
                <div style="background: var(--<?php echo $statusClass; ?>); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 12px; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                    <i class="bi bi-<?php echo $statusIcon; ?>"></i>
                    <?php echo $statusText; ?>
                </div>
            </div>
            
            <!-- Üst Sol "SATIN ALINDI" Badge -->
            <div style="position: absolute; top: 16px; left: 16px; z-index: 10;">
                <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 11px; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">
                    <i class="bi bi-check-circle-fill"></i>
                    SATIN ALINDI
                </div>
            </div>
            
            <div style="height: 200px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                <?php if ($script['image']): ?>
                <img src="<?php echo $script['image']; ?>" alt="<?php echo $script['name']; ?>" style="max-width: 90%; max-height: 90%; object-fit: contain; position: relative; z-index: 1; filter: brightness(0.8);">
                <?php else: ?>
                <i class="bi bi-box-seam" style="font-size: 80px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; opacity: 0.6;"></i>
                <?php endif; ?>
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(180deg, transparent, rgba(0,0,0,0.3));"></div>
            </div>
            
            <div class="card-body" style="padding: 24px;">
                <h5 style="font-size: 20px; font-weight: 800; margin-bottom: 16px; color: var(--text-primary);">
                    <?php echo $script['name']; ?>
                </h5>
                
                <!-- Maskelenmiş Kullanıcı -->
                <div style="padding: 16px; background: rgba(26, 26, 46, 0.6); border-radius: 12px; margin-bottom: 16px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Sahip</div>
                    <div style="font-family: 'Courier New', monospace; color: var(--warning); font-weight: 800; font-size: 16px;">
                        <?php echo $maskedUsername; ?>
                    </div>
                </div>
                
                <!-- Süre Bilgileri -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div style="padding: 16px; background: rgba(26, 26, 46, 0.4); border-radius: 10px; text-align: center; border: 1px solid var(--border-color);">
                        <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 6px;"><?php echo $dateLabel; ?></div>
                        <div style="font-size: 16px; font-weight: 800; color: var(--<?php echo $statusClass; ?>);">
                            <?php echo $dateText; ?>
                        </div>
                    </div>
                    <?php if ($rental['expires_at']): ?>
                    <div style="padding: 16px; background: rgba(26, 26, 46, 0.4); border-radius: 10px; text-align: center; border: 1px solid var(--border-color);">
                        <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 6px;">Bitiş</div>
                        <div style="font-size: 16px; font-weight: 800; color: var(--text-primary);">
                            <?php echo date('d.m.Y', strtotime($rental['expires_at'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Domain -->
                <?php if ($rental['domain']): ?>
                <div style="padding: 12px 16px; background: rgba(6, 182, 212, 0.1); border-radius: 10px; margin-bottom: 16px; border: 1px solid rgba(6, 182, 212, 0.3);">
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">DOMAIN</div>
                    <code style="font-size: 13px; color: var(--info); word-break: break-all;"><?php echo $rental['domain']; ?></code>
                </div>
                <?php endif; ?>
                
                <!-- Aksiyon Butonları -->
                <?php if (in_array($rental['rental_status'], ['setup_domain', 'setup_config', 'setup_deploy', 'pending'])): ?>
                <a href="<?php echo Helper::url('rental/setup/' . $rental['id']); ?>" class="btn btn-warning w-100">
                    <i class="bi bi-play-fill"></i>
                    Kuruluma Devam
                </a>
                <?php elseif ($rental['rental_status'] === 'active'): ?>
                <a href="<?php echo Helper::url('rental'); ?>" class="btn btn-success w-100">
                    <i class="bi bi-eye"></i>
                    Yönet
                </a>
                <?php else: ?>
                <a href="<?php echo Helper::url('scripts/buy/' . $script['id']); ?>" class="btn btn-primary w-100">
                    <i class="bi bi-arrow-repeat"></i>
                    Yeniden Kirala
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- NORMAL SATIN ALINABİLİR SCRIPT KARTI (Tüm kullanıcılar için görünür) -->
        <div class="card" style="height: 100%; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-8px) scale(1.02)'" onmouseout="this.style.transform='translateY(0) scale(1)'">
            <div style="height: 200px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                <?php if ($script['image']): ?>
                <img src="<?php echo $script['image']; ?>" alt="<?php echo $script['name']; ?>" style="max-width: 90%; max-height: 90%; object-fit: contain;">
                <?php else: ?>
                <i class="bi bi-box-seam" style="font-size: 80px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                <?php endif; ?>
            </div>
            
            <div class="card-body" style="padding: 24px;">
                <h5 style="font-size: 20px; font-weight: 800; margin-bottom: 12px; color: var(--text-primary);">
                    <?php echo $script['name']; ?>
                </h5>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 20px; line-height: 1.6;">
                    <?php echo Helper::excerpt($script['description'], 100); ?>
                </p>
                
                <!-- Script Bilgileri -->
                <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                    <div style="padding: 8px 16px; background: rgba(6, 182, 212, 0.1); border-radius: 20px; border: 1px solid rgba(6, 182, 212, 0.3); font-size: 13px;">
                        <i class="bi bi-globe" style="color: var(--info);"></i>
                        <strong style="margin-left: 6px; color: var(--info);"><?php echo $script['available_domains']; ?></strong>
                        <span style="margin-left: 4px; color: var(--text-muted);">Domain</span>
                    </div>
                    <div style="padding: 8px 16px; background: rgba(16, 185, 129, 0.1); border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 13px;">
                        <i class="bi bi-box" style="color: var(--success);"></i>
                        <strong style="margin-left: 6px; color: var(--success);"><?php echo $script['package_count']; ?></strong>
                        <span style="margin-left: 4px; color: var(--text-muted);">Paket</span>
                    </div>
                </div>
                
                <!-- Fiyat -->
                <?php if ($script['min_price']): ?>
                <div style="padding: 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); border-radius: 12px; margin-bottom: 20px; text-align: center; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">Başlangıç Fiyatı</div>
                    <div style="font-size: 24px; font-weight: 900; color: var(--success);">
                        <?php echo number_format($script['min_price'], 2); ?> USDT
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Satın Al Butonu -->
                <a href="<?php echo Helper::url('scripts/buy/' . $script['id']); ?>" class="btn btn-primary w-100" style="padding: 14px;">
                    <i class="bi bi-cart-plus"></i>
                    Satın Al
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($scripts)): ?>
<div class="empty-state">
    <div class="empty-icon">
        <i class="bi bi-box-seam"></i>
    </div>
    <h4>Henüz Script Yok</h4>
    <p>Yakında harika scriptler eklenecek!</p>
</div>
<?php endif; ?>

<?php require 'templates/footer.php'; ?>
