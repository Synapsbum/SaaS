<?php
$title = 'Scriptler';
$user = $auth->user();
$db = Database::getInstance();

// TÜM aktif kiralamaları çek (HERKESE GÖRÜNÜR)
$allRentals = $db->fetchAll("
    SELECT r.*, s.id as script_id, s.name as script_name, u.username, u.is_hidden,
           d.domain, r.expires_at, r.status as rental_status
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    JOIN users u ON r.user_id = u.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.status IN ('active', 'setup_domain', 'setup_config', 'setup_deploy')
    AND (r.expires_at IS NULL OR r.expires_at > NOW())
    ORDER BY r.purchased_at DESC
");

// Script başına son kiralama
$rentalByScript = [];
foreach ($allRentals as $rental) {
    if (!isset($rentalByScript[$rental['script_id']])) {
        // Gizli kullanıcı kontrolü
        $displayName = $rental['is_hidden'] ? 'Gizli Tokatçı' : $rental['username'];
        
        $rental['display_owner'] = $displayName;
        $rentalByScript[$rental['script_id']] = $rental;
    }
}

// Kullanıcının kendi kiralamaları
$myRentals = $db->fetchAll("
    SELECT r.*, s.name as script_name, s.slug, d.domain
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.user_id = ? AND r.status IN ('active', 'setup_domain', 'setup_config', 'setup_deploy', 'pending')
    ORDER BY r.purchased_at DESC
", [$user['id']]);

// Kategori filtreleme
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : null;
$params = [];
$sql = "SELECT s.*, c.name as category_name, c.slug as category_slug,
        (SELECT COUNT(*) FROM script_packages WHERE script_id = s.id) as package_count
        FROM scripts s
        LEFT JOIN script_categories c ON s.category_id = c.id
        WHERE s.status = 'active'";

if ($categoryFilter) {
    $sql .= " AND s.category_id = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY s.created_at DESC";
$scripts = $db->fetchAll($sql, $params);

// Kategorileri çek
$categories = $db->fetchAll("SELECT * FROM script_categories WHERE status = 1 ORDER BY name");

// İstatistikler
$stats = [
    'total_scripts' => $db->fetch("SELECT COUNT(*) as total FROM scripts WHERE status = 'active'")['total'],
    'total_rented' => count($rentalByScript),
    'my_active' => count($myRentals)
];

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<!-- Ultra Modern Header -->
<div class="row mb-5">
    <div class="col-lg-8 mb-4 mb-lg-0">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <div style="width: 4px; height: 48px; background: linear-gradient(180deg, var(--primary), var(--accent)); border-radius: 4px;"></div>
            <h1 style="margin: 0; font-weight: 900; font-size: 42px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                Script Market
            </h1>
        </div>
        <p style="color: var(--text-secondary); font-size: 17px; line-height: 1.6; margin-left: 16px;">
            Profesyonel yazılım çözümleri ile işletmenizi güçlendirin. Kiralama modeli ile esnek ve maliyet-etkin çözümler.
        </p>
    </div>
    
    <div class="col-lg-4">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
            <div style="padding: 20px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); border-radius: 16px; text-align: center; border: 2px solid rgba(99, 102, 241, 0.3);">
                <div style="font-size: 32px; font-weight: 900; margin-bottom: 4px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <?php echo $stats['total_scripts']; ?>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Script</div>
            </div>
            <div style="padding: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); border-radius: 16px; text-align: center; border: 2px solid rgba(16, 185, 129, 0.3);">
                <div style="font-size: 32px; font-weight: 900; margin-bottom: 4px; color: var(--success);">
                    <?php echo $stats['total_rented']; ?>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Satıldı</div>
            </div>
            <div style="padding: 20px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(8, 145, 178, 0.05)); border-radius: 16px; text-align: center; border: 2px solid rgba(6, 182, 212, 0.3);">
                <div style="font-size: 32px; font-weight: 900; margin-bottom: 4px; color: var(--info);">
                    <?php echo $stats['my_active']; ?>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Benim</div>
            </div>
        </div>
    </div>
</div>

<!-- Modern Kategori Filtreleri -->
<div style="display: flex; gap: 12px; margin-bottom: 32px; overflow-x: auto; padding-bottom: 8px;">
    <a href="?" style="padding: 12px 28px; background: <?php echo !$categoryFilter ? 'linear-gradient(135deg, var(--primary), var(--accent))' : 'rgba(26, 26, 46, 0.4)'; ?>; border: 2px solid <?php echo !$categoryFilter ? 'var(--primary)' : 'var(--border-color)'; ?>; border-radius: 24px; color: <?php echo !$categoryFilter ? 'white' : 'var(--text-primary)'; ?>; font-weight: 700; font-size: 14px; text-decoration: none; transition: all 0.3s; white-space: nowrap; display: inline-block;">
        Tümü
    </a>
    <?php foreach ($categories as $cat): ?>
    <a href="?category=<?php echo $cat['id']; ?>" style="padding: 12px 28px; background: <?php echo $categoryFilter == $cat['id'] ? 'linear-gradient(135deg, var(--primary), var(--accent))' : 'rgba(26, 26, 46, 0.4)'; ?>; border: 2px solid <?php echo $categoryFilter == $cat['id'] ? 'var(--primary)' : 'var(--border-color)'; ?>; border-radius: 24px; color: <?php echo $categoryFilter == $cat['id'] ? 'white' : 'var(--text-primary)'; ?>; font-weight: 700; font-size: 14px; text-decoration: none; transition: all 0.3s; white-space: nowrap; display: inline-block;">
        <?php echo $cat['name']; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Ultra Modern Script Grid -->
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
    <?php foreach ($scripts as $script): 
        $isRented = isset($rentalByScript[$script['id']]);
        $rental = $rentalByScript[$script['id']] ?? null;
        
        // Benim kiralamam mı?
        $isMyRental = false;
        foreach ($myRentals as $my) {
            if ($my['script_id'] == $script['id']) {
                $isMyRental = true;
                $myRentalData = $my;
                break;
            }
        }
        
        // Durum
        if ($isRented) {
            if ($rental['rental_status'] == 'active') {
                $statusColor = 'success';
                $statusText = 'AKTİF';
                $statusIcon = 'check-circle-fill';
            } elseif (in_array($rental['rental_status'], ['setup_domain', 'setup_config', 'setup_deploy'])) {
                $statusColor = 'warning';
                $statusText = 'KURULUMDA';
                $statusIcon = 'hourglass-split';
            } else {
                $statusColor = 'info';
                $statusText = 'BEKLİYOR';
                $statusIcon = 'clock';
            }
            // SAAT olarak hesapla (3600 saniye = 1 saat)
            $hoursLeft = $rental['expires_at'] ? ceil((strtotime($rental['expires_at']) - time()) / 3600) : 0;
        }
    ?>
    <div class="col">
        <div class="card" style="height: 100%; <?php echo $isMyRental ? 'border: 3px solid var(--' . $statusColor . ');' : ''; ?> position: relative; overflow: hidden; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-8px)'" onmouseout="this.style.transform='translateY(0)'">
            <!-- Image Area -->
            <div style="height: 200px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                <?php if ($script['image']): ?>
                <img src="<?php echo $script['image']; ?>" alt="<?php echo $script['name']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                <i class="bi bi-box-seam" style="font-size: 80px; background: linear-gradient(135deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                <?php endif; ?>
                
                <!-- Top Left Badge -->
                <div style="position: absolute; top: 16px; left: 16px; z-index: 10;">
                    <?php if ($isRented): ?>
                    <div style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 11px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); display: flex; align-items: center; gap: 6px;">
                        <i class="bi bi-lock-fill"></i> SATILDI
                    </div>
                    <?php else: ?>
                    <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 11px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); display: flex; align-items: center; gap: 6px;">
                        <i class="bi bi-unlock-fill"></i> MÜSAİT
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Top Right Status -->
                <?php if ($isRented): ?>
                <div style="position: absolute; top: 16px; right: 16px; z-index: 10;">
                    <div style="background: var(--<?php echo $statusColor; ?>); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="bi bi-<?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Bottom Category -->
                <?php if ($script['category_name']): ?>
                <div style="position: absolute; bottom: 16px; left: 16px; z-index: 10;">
                    <div style="background: rgba(26, 26, 46, 0.9); backdrop-filter: blur(8px); color: var(--text-primary); padding: 6px 14px; border-radius: 16px; font-weight: 600; font-size: 12px; border: 1px solid var(--border-color);">
                        <?php echo $script['category_name']; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding: 24px;">
                <h5 style="font-size: 20px; font-weight: 800; margin-bottom: 12px; color: var(--text-primary);">
                    <?php echo $script['name']; ?>
                </h5>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 20px; line-height: 1.6; min-height: 40px;">
                    <?php echo Helper::excerpt($script['description'], 80); ?>
                </p>

                <!-- Info Badges -->
                <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                    <?php if ($isRented): ?>
                    <div style="padding: 6px 12px; background: rgba(6, 182, 212, 0.1); border-radius: 16px; font-size: 12px; border: 1px solid rgba(6, 182, 212, 0.3);">
                        <i class="bi bi-globe" style="color: var(--info);"></i>
                        <span style="margin-left: 4px; color: var(--text-muted);">
                            <?php echo $rental['domain'] ? '1 Domain' : '0 Domain'; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!$isRented && $script['package_count'] > 0): ?>
                    <div style="padding: 6px 12px; background: rgba(16, 185, 129, 0.1); border-radius: 16px; font-size: 12px; border: 1px solid rgba(16, 185, 129, 0.3);">
                        <i class="bi bi-box" style="color: var(--success);"></i>
                        <span style="margin-left: 4px; color: var(--text-muted);"><?php echo $script['package_count']; ?> Paket</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($isRented): ?>
                <!-- Kiralama Bilgileri -->
                <div style="padding: 20px; background: rgba(26, 26, 46, 0.6); border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-size: 12px; color: var(--text-muted);">SAHİP</span>
                        <span style="padding: 4px 12px; background: rgba(245, 158, 11, 0.2); border-radius: 12px; color: var(--warning); font-weight: 800; font-family: monospace; font-size: 13px;">
                            <?php echo $rental['display_owner']; ?>
                        </span>
                    </div>
                    
                    <?php if ($rental['domain']): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-size: 12px; color: var(--text-muted);">DOMAIN</span>
                        <code style="font-size: 12px; color: var(--info); max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo $rental['domain']; ?>
                        </code>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: var(--text-muted);">KALAN SÜRE</span>
                        <span style="font-size: 13px; color: var(--<?php echo $statusColor; ?>); font-weight: 700;">
                            <?php 
                            if ($rental['expires_at']) {
                                echo $hoursLeft . ' saat kaldı';
                            } else {
                                echo 'Kurulum bekliyor';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Aksiyon Butonu -->
                <?php if ($isMyRental): ?>
                <a href="<?php echo Helper::url('rental'); ?>" class="btn btn-<?php echo $rental['rental_status'] == 'active' ? 'success' : 'warning'; ?> w-100" style="padding: 14px; font-weight: 700;">
                    <i class="bi bi-<?php echo $rental['rental_status'] == 'active' ? 'eye' : 'gear-fill'; ?>"></i>
                    <?php echo $rental['rental_status'] == 'active' ? 'Yönet' : 'Kurulum'; ?>
                </a>
                <?php else: ?>
                <button class="btn w-100" style="padding: 14px; background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 2px solid rgba(239, 68, 68, 0.3); font-weight: 700; cursor: not-allowed;" disabled>
                    <i class="bi bi-lock-fill"></i>
                    Başkasına Ait
                </button>
                <?php endif; ?>

                <?php else: ?>
                <!-- Fiyat ve Satın Al -->
                <?php 
                $minPrice = $db->fetch("SELECT MIN(price_usdt) as min_price FROM script_packages WHERE script_id = ?", [$script['id']])['min_price'] ?? 0;
                ?>
                <div style="padding: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05)); border-radius: 12px; margin-bottom: 20px; text-align: center; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Başlangıç Fiyatı</div>
                    <div style="font-size: 28px; font-weight: 900; color: var(--success);">
                        <?php echo $minPrice > 0 ? number_format($minPrice, 2) . ' USDT' : 'Fiyat Yok'; ?>
                    </div>
                </div>

                <a href="<?php echo Helper::url('scripts/buy/' . $script['id']); ?>" class="btn btn-primary w-100" style="padding: 14px; font-weight: 700;">
                    <i class="bi bi-cart-plus-fill"></i>
                    Satın Al
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($scripts)): ?>
<div class="empty-state">
    <div class="empty-icon">
        <i class="bi bi-inbox"></i>
    </div>
    <h4>Bu Kategoride Script Yok</h4>
    <p>Başka bir kategori seçin veya tümünü görüntüleyin.</p>
</div>
<?php endif; ?>

<?php require 'templates/footer.php'; ?>