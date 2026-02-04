<?php
$title = 'Script Yönetimi';
$user = $auth->user();
$db = Database::getInstance();

// Rental ID al (URL segment'inden)
$rentalId = (int)$id;

// Rental bilgilerini getir
$rental = $db->fetch("
    SELECT r.*, s.name as script_name, s.slug, d.domain, u.username
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$rental) {
    Helper::redirect('rental');
    exit;
}

// Bugünkü özet verileri
$today = date('Y-m-d');
$todayStats = $db->fetch("
    SELECT 
        COALESCE(unique_visitors, 0) as unique_visitors,
        COALESCE(total_pageviews, 0) as total_pageviews,
        COALESCE(active_users_now, 0) as active_users_now,
        COALESCE(total_deposits_try, 0) as total_deposits_try,
        COALESCE(deposit_count, 0) as deposit_count
    FROM rental_analytics_summary
    WHERE rental_id = ? AND date = ?
", [$rentalId, $today]);

if (!$todayStats) {
    $todayStats = [
        'unique_visitors' => 0,
        'total_pageviews' => 0,
        'active_users_now' => 0,
        'total_deposits_try' => 0,
        'deposit_count' => 0
    ];
}

// Son 7 günün verileri (grafik için)
$weeklyStats = $db->fetchAll("
    SELECT 
        date,
        unique_visitors,
        total_pageviews,
        total_deposits_try
    FROM rental_analytics_summary
    WHERE rental_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY date ASC
", [$rentalId]);

// Şehir bazlı veriler (harita için)
$cityStats = $db->fetchAll("
    SELECT 
        city,
        SUM(visitor_count) as total_visitors,
        SUM(pageview_count) as total_pageviews,
        latitude,
        longitude
    FROM rental_analytics_by_city
    WHERE rental_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY city, latitude, longitude
    ORDER BY total_visitors DESC
    LIMIT 20
", [$rentalId]);

// İBAN sayısı
$ibanCount = $db->fetch("
    SELECT COUNT(*) as cnt FROM rental_ibans WHERE rental_id = ? AND status = 'active'
", [$rentalId])['cnt'] ?? 0;

// Kripto cüzdan sayısı
$walletCount = $db->fetch("
    SELECT COUNT(*) as cnt FROM rental_crypto_wallets WHERE rental_id = ? AND status = 'active'
", [$rentalId])['cnt'] ?? 0;

// Tawk.to ayarı
$tawktoId = $db->fetch("
    SELECT setting_value FROM rental_settings 
    WHERE rental_id = ? AND setting_key = 'tawkto_id'
", [$rentalId])['setting_value'] ?? '';

require 'templates/header_new.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.2);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.stat-label {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.stat-change {
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-change.positive {
    color: var(--success);
}

.stat-change.negative {
    color: var(--danger);
}

.chart-container {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 30px;
    border: 1px solid var(--border-color);
}

.map-container {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--border-color);
    min-height: 500px;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 30px;
}

.quick-action-btn {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    border: none;
    border-radius: 12px;
    padding: 20px;
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    font-weight: 600;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    color: white;
}

.quick-action-btn i {
    font-size: 24px;
}

.rental-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.rental-header h2 {
    color: white;
    margin-bottom: 10px;
}

.rental-domain {
    font-size: 18px;
    opacity: 0.9;
    margin-bottom: 15px;
}

.rental-status {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
}
</style>

<div class="rental-header">
    <div style="display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2><i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($rental['script_name']); ?></h2>
            <div class="rental-domain">
                <i class="bi bi-globe me-2"></i>
                <strong><?php echo htmlspecialchars($rental['domain'] ?? 'Domain atanmamış'); ?></strong>
            </div>
            <span class="rental-status">
                <i class="bi bi-check-circle me-1"></i>
                <?php echo ucfirst($rental['status']); ?>
            </span>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Kalan Süre</div>
            <div style="font-size: 24px; font-weight: 700;">
                <?php echo Helper::remaining($rental['expires_at']); ?>
            </div>
        </div>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            <i class="bi bi-people-fill" style="color: white;"></i>
        </div>
        <div class="stat-label">Bugünkü Ziyaretçi</div>
        <div class="stat-value"><?php echo number_format($todayStats['unique_visitors']); ?></div>
        <div class="stat-change positive">
            <i class="bi bi-arrow-up"></i>
            <span>Aktif şimdi: <?php echo number_format($todayStats['active_users_now']); ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <i class="bi bi-eye-fill" style="color: white;"></i>
        </div>
        <div class="stat-label">Sayfa Görüntüleme</div>
        <div class="stat-value"><?php echo number_format($todayStats['total_pageviews']); ?></div>
        <div class="stat-change">
            <span style="color: var(--text-muted);">Bugün</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="bi bi-cash-coin" style="color: white;"></i>
        </div>
        <div class="stat-label">Toplam Para Yatırma</div>
        <div class="stat-value">₺<?php echo number_format($todayStats['total_deposits_try'], 2); ?></div>
        <div class="stat-change positive">
            <span><?php echo number_format($todayStats['deposit_count']); ?> işlem</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <i class="bi bi-activity" style="color: white;"></i>
        </div>
        <div class="stat-label">Aktif Kullanıcı</div>
        <div class="stat-value"><?php echo number_format($todayStats['active_users_now']); ?></div>
        <div class="stat-change">
            <span style="color: var(--text-muted);">Son 5 dakika</span>
        </div>
    </div>
</div>

<!-- Grafikler -->
<div class="chart-container">
    <h5 style="margin-bottom: 20px;"><i class="bi bi-graph-up me-2"></i>Son 7 Günlük İstatistikler</h5>
    <canvas id="statsChart" height="80"></canvas>
</div>

<!-- Türkiye Haritası -->
<div class="map-container">
    <h5 style="margin-bottom: 20px;"><i class="bi bi-geo-alt me-2"></i>Şehir Bazlı Ziyaretçi Dağılımı</h5>
    <div id="turkeyMap"></div>
    
    <!-- Şehir listesi -->
    <div style="margin-top: 30px;">
        <h6>En Çok Ziyaret Edilen Şehirler</h6>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php foreach ($cityStats as $index => $city): ?>
            <div style="background: rgba(99, 102, 241, 0.05); padding: 15px; border-radius: 8px; border-left: 3px solid var(--primary);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 5px;"><?php echo htmlspecialchars($city['city']); ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php echo number_format($city['total_visitors']); ?> ziyaretçi
                        </div>
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--primary);">
                        #<?php echo $index + 1; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Hızlı İşlemler -->
<div class="quick-actions">
    <a href="<?php echo Helper::url('rental/manage/' . $rentalId . '/ibans'); ?>" class="quick-action-btn">
        <i class="bi bi-bank"></i>
        <div>
            <div>İBAN Yönetimi</div>
            <small style="opacity: 0.8;"><?php echo $ibanCount; ?> aktif</small>
        </div>
    </a>
    
    <a href="<?php echo Helper::url('rental/manage/' . $rentalId . '/wallets'); ?>" class="quick-action-btn">
        <i class="bi bi-wallet2"></i>
        <div>
            <div>Kripto Cüzdan</div>
            <small style="opacity: 0.8;"><?php echo $walletCount; ?> aktif</small>
        </div>
    </a>
    
    <a href="<?php echo Helper::url('rental/manage/' . $rentalId . '/settings'); ?>" class="quick-action-btn">
        <i class="bi bi-gear"></i>
        <div>
            <div>Ayarlar</div>
            <small style="opacity: 0.8;">Tawk.to, limit vb.</small>
        </div>
    </a>
    
    <a href="https://<?php echo htmlspecialchars($rental['domain']); ?>" target="_blank" class="quick-action-btn">
        <i class="bi bi-box-arrow-up-right"></i>
        <div>
            <div>Siteyi Aç</div>
            <small style="opacity: 0.8;">Yeni sekmede</small>
        </div>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Grafik verileri
const weeklyData = <?php echo json_encode($weeklyStats); ?>;
const cityData = <?php echo json_encode($cityStats); ?>;

// Stats Chart
const ctx = document.getElementById('statsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: weeklyData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('tr-TR', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'Ziyaretçi',
            data: weeklyData.map(d => d.unique_visitors),
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.4
        }, {
            label: 'Sayfa Görüntüleme',
            data: weeklyData.map(d => d.total_pageviews),
            borderColor: 'rgb(139, 92, 246)',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            tension: 0.4
        }, {
            label: 'Para Yatırma (₺)',
            data: weeklyData.map(d => d.total_deposits_try),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Ziyaretçi / Görüntüleme'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Para Yatırma (₺)'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

// Harita için basit bir görselleştirme
// Gerçek harita için Leaflet veya Google Maps kullanabilirsiniz
const mapContainer = document.getElementById('turkeyMap');
mapContainer.innerHTML = '<div style="background: rgba(99, 102, 241, 0.05); height: 300px; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><p style="color: var(--text-muted);">Harita görünümü için Leaflet.js veya Google Maps entegrasyonu yapılacak</p></div>';
</script>

<?php require 'templates/footer_new.php'; ?>
