<?php
require_once 'config.php';
require_once 'core/Database.php';
require_once 'core/Auth.php';
require_once 'core/Helper.php';

$auth = new Auth();
if (!$auth->check()) {
    die("Lütfen giriş yapın!");
}

$user = $auth->user();
$db = Database::getInstance();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h2 { color: #333; border-bottom: 2px solid #6366f1; padding-bottom: 10px; }
h3 { color: #666; margin-top: 20px; }
table { width: 100%; border-collapse: collapse; }
table td, table th { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
table th { background: #6366f1; color: white; }
.success { color: #10b981; font-weight: bold; }
.error { color: #ef4444; font-weight: bold; }
code { background: #f1f1f1; padding: 2px 6px; border-radius: 4px; }
a { color: #6366f1; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>";

echo "<div class='card'>";
echo "<h2>🔍 Rental Yönetim Diagnostic</h2>";
echo "<p>Bu sayfa sisteminizdeki rental yönetim sorunlarını tespit etmek için hazırlanmıştır.</p>";
echo "</div>";

// 1. Kullanıcı Bilgileri
echo "<div class='card'>";
echo "<h3>👤 Kullanıcı Bilgileri</h3>";
echo "<table>";
echo "<tr><th>Özellik</th><th>Değer</th></tr>";
echo "<tr><td>User ID</td><td>{$user['id']}</td></tr>";
echo "<tr><td>Username</td><td>{$user['username']}</td></tr>";
echo "<tr><td>Admin</td><td>" . ($user['is_admin'] ? '<span class="success">✓ Evet</span>' : 'Hayır') . "</td></tr>";
echo "</table>";
echo "</div>";

// 2. Rental'ları Kontrol Et
echo "<div class='card'>";
echo "<h3>📦 Kiralamalarınız</h3>";

$rentals = $db->fetchAll("
    SELECT r.*, s.name as script_name, d.domain
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
", [$user['id']]);

if ($rentals) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Script</th><th>Domain</th><th>Status</th><th>Test Link</th></tr>";
    foreach ($rentals as $rental) {
        $manageUrl = Helper::url('rental/manage/' . $rental['id']);
        echo "<tr>";
        echo "<td>{$rental['id']}</td>";
        echo "<td>{$rental['script_name']}</td>";
        echo "<td>" . ($rental['domain'] ?? '-') . "</td>";
        echo "<td>{$rental['status']}</td>";
        echo "<td>";
        if ($rental['status'] === 'active') {
            echo "<a href='{$manageUrl}' target='_blank'>Yönetim Paneline Git →</a>";
        } else {
            echo "<span class='error'>Aktif değil</span>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ Hiç kiralama bulunamadı!</p>";
}
echo "</div>";

// 3. URL Testleri
echo "<div class='card'>";
echo "<h3>🔗 URL Yapısı</h3>";
echo "<table>";
echo "<tr><th>Tip</th><th>URL</th></tr>";
echo "<tr><td>SITE_URL</td><td><code>" . SITE_URL . "</code></td></tr>";
echo "<tr><td>BASE_PATH</td><td><code>" . BASE_PATH . "</code></td></tr>";
echo "<tr><td>Rental Ana</td><td><code>" . Helper::url('rental') . "</code></td></tr>";
if ($rentals && isset($rentals[0])) {
    $firstRental = $rentals[0]['id'];
    echo "<tr><td>Rental Manage #{$firstRental}</td><td><code>" . Helper::url('rental/manage/' . $firstRental) . "</code></td></tr>";
    echo "<tr><td>Rental İBAN #{$firstRental}</td><td><code>" . Helper::url('rental/manage/' . $firstRental . '/ibans') . "</code></td></tr>";
}
echo "</table>";
echo "</div>";

// 4. Dosya Kontrolleri
echo "<div class='card'>";
echo "<h3>📁 Dosya Kontrolleri</h3>";
echo "<table>";
echo "<tr><th>Dosya</th><th>Durum</th></tr>";

$files = [
    'modules/rental/index.php' => 'Rental Ana Sayfa',
    'modules/rental/manage.php' => 'Rental Dashboard',
    'modules/rental/ibans.php' => 'İBAN Yönetimi',
    'modules/rental/wallets.php' => 'Cüzdan Yönetimi',
    'modules/rental/settings.php' => 'Ayarlar',
    'api/analytics/track.php' => 'Analytics API'
];

foreach ($files as $file => $name) {
    $exists = file_exists($file);
    echo "<tr>";
    echo "<td>{$name}<br><small><code>{$file}</code></small></td>";
    echo "<td>" . ($exists ? '<span class="success">✓ Var</span>' : '<span class="error">✗ Yok</span>') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 5. Database Tablo Kontrolleri
echo "<div class='card'>";
echo "<h3>🗄️ Database Tabloları</h3>";

$tables = [
    'rental_analytics',
    'rental_analytics_summary',
    'rental_analytics_by_city',
    'rental_deposits',
    'rental_crypto_wallets',
    'rental_ibans',
    'rental_settings',
    'rental_active_sessions'
];

echo "<table>";
echo "<tr><th>Tablo</th><th>Durum</th><th>Kayıt Sayısı</th></tr>";

foreach ($tables as $table) {
    try {
        $count = $db->fetch("SELECT COUNT(*) as cnt FROM `{$table}`");
        $count = $count['cnt'] ?? 0;
        echo "<tr>";
        echo "<td><code>{$table}</code></td>";
        echo "<td><span class='success'>✓ Var</span></td>";
        echo "<td>{$count}</td>";
        echo "</tr>";
    } catch (Exception $e) {
        echo "<tr>";
        echo "<td><code>{$table}</code></td>";
        echo "<td><span class='error'>✗ Yok</span></td>";
        echo "<td>-</td>";
        echo "</tr>";
    }
}
echo "</table>";
echo "</div>";

// 6. Routing Test
echo "<div class='card'>";
echo "<h3>🛣️ Routing Testi</h3>";
echo "<p>Mevcut URL: <code>{$_SERVER['REQUEST_URI']}</code></p>";

// Parse routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = trim(substr($uri, strlen($base)), '/');
$segments = explode('/', $path);

echo "<p>Base Path: <code>{$base}</code></p>";
echo "<p>Parsed Path: <code>{$path}</code></p>";
echo "<p>Segments: <code>" . implode(' / ', $segments) . "</code></p>";
echo "</div>";

// 7. Çözüm Önerileri
echo "<div class='card'>";
echo "<h3>💡 Sorun Giderme</h3>";

if (empty($rentals)) {
    echo "<p><strong>❌ Hiç kiralama yok!</strong></p>";
    echo "<p>Çözüm: Önce bir script kiralayın ve aktif duruma getirin.</p>";
} else {
    $activeRentals = array_filter($rentals, function($r) { return $r['status'] === 'active'; });
    
    if (empty($activeRentals)) {
        echo "<p><strong>⚠️ Aktif kiralama yok!</strong></p>";
        echo "<p>Çözüm: Mevcut kiralamalarınızdan birini aktif duruma getirin.</p>";
    } else {
        echo "<p><strong>✓ Aktif kiralamalar bulundu!</strong></p>";
        echo "<p>Yukarıdaki 'Test Link' kolonundaki linklere tıklayarak yönetim paneline erişebilirsiniz.</p>";
    }
}

// Dosya eksikliği kontrolü
$missingFiles = [];
foreach ($files as $file => $name) {
    if (!file_exists($file)) {
        $missingFiles[] = $file;
    }
}

if (!empty($missingFiles)) {
    echo "<p><strong>❌ Eksik dosyalar:</strong></p>";
    echo "<ul>";
    foreach ($missingFiles as $file) {
        echo "<li><code>{$file}</code></li>";
    }
    echo "</ul>";
    echo "<p>Çözüm: Güncellenmiş proje dosyalarını yükleyin.</p>";
}

echo "</div>";

echo "<div class='card'>";
echo "<p><a href='" . Helper::url('dashboard') . "'>← Dashboard'a Dön</a></p>";
echo "</div>";
