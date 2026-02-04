<?php
// RENTAL YÖNETIM DEBUG SAYFASI
// Bu sayfayı https://tokat.bet/rental_debug.php olarak yükleyin ve ziyaret edin

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Rental Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #6366f1; border-bottom: 2px solid #6366f1; padding-bottom: 10px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #6366f1; color: white; }
        code { background: #f1f1f1; padding: 2px 6px; border-radius: 4px; }
        a { color: #6366f1; text-decoration: none; padding: 8px 16px; background: #f0f0f0; border-radius: 6px; display: inline-block; margin: 5px; }
        a:hover { background: #6366f1; color: white; }
        .test-box { background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 8px; margin: 10px 0; }
    </style>
</head>
<body>";

require_once 'config.php';

echo "<div class='card'>";
echo "<h2>🔍 Rental Yönetim Debug</h2>";
echo "<p>Bu sayfa rental yönetim butonlarının neden çalışmadığını tespit eder.</p>";
echo "</div>";

// 1. Config Kontrolü
echo "<div class='card'>";
echo "<h2>⚙️ Config Ayarları</h2>";
echo "<table>";
echo "<tr><th>Ayar</th><th>Değer</th></tr>";
echo "<tr><td>SITE_URL</td><td><code>" . SITE_URL . "</code></td></tr>";
echo "<tr><td>BASE_PATH</td><td><code>" . BASE_PATH . "</code></td></tr>";
echo "<tr><td>Request URI</td><td><code>" . $_SERVER['REQUEST_URI'] . "</code></td></tr>";
echo "<tr><td>Script Name</td><td><code>" . $_SERVER['SCRIPT_NAME'] . "</code></td></tr>";
echo "</table>";
echo "</div>";

// 2. Helper Test
require_once 'core/Helper.php';

echo "<div class='card'>";
echo "<h2>🔗 Helper::url() Testi</h2>";
echo "<table>";
echo "<tr><th>Path</th><th>Oluşturulan URL</th></tr>";
echo "<tr><td>rental</td><td><code>" . Helper::url('rental') . "</code></td></tr>";
echo "<tr><td>rental/manage/3</td><td><code>" . Helper::url('rental/manage/3') . "</code></td></tr>";
echo "<tr><td>rental/manage/3/ibans</td><td><code>" . Helper::url('rental/manage/3/ibans') . "</code></td></tr>";
echo "</table>";
echo "</div>";

// 3. Routing Test
echo "<div class='card'>";
echo "<h2>🛣️ Routing Analizi</h2>";

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = trim(substr($uri, strlen($base)), '/');
$segments = explode('/', $path);

echo "<table>";
echo "<tr><th>Değişken</th><th>Değer</th></tr>";
echo "<tr><td>URI</td><td><code>$uri</code></td></tr>";
echo "<tr><td>Base</td><td><code>$base</code></td></tr>";
echo "<tr><td>Path</td><td><code>$path</code></td></tr>";
echo "<tr><td>Segments</td><td><code>" . implode(' / ', $segments) . "</code></td></tr>";
echo "<tr><td>Page (segment[0])</td><td><code>" . ($segments[0] ?: 'dashboard') . "</code></td></tr>";
echo "<tr><td>Action (segment[1])</td><td><code>" . ($segments[1] ?? 'index') . "</code></td></tr>";
echo "<tr><td>ID (segment[2])</td><td><code>" . ($segments[2] ?? 'null') . "</code></td></tr>";
echo "<tr><td>Sub Action (segment[3])</td><td><code>" . ($segments[3] ?? 'null') . "</code></td></tr>";
echo "</table>";
echo "</div>";

// 4. Dosya Kontrolü
echo "<div class='card'>";
echo "<h2>📁 Dosya Varlığı</h2>";

$files = [
    'modules/rental/index.php',
    'modules/rental/manage.php',
    'modules/rental/ibans.php',
    'modules/rental/wallets.php',
    'modules/rental/settings.php',
    '.htaccess'
];

echo "<table>";
echo "<tr><th>Dosya</th><th>Durum</th></tr>";
foreach ($files as $file) {
    $exists = file_exists($file);
    echo "<tr>";
    echo "<td><code>$file</code></td>";
    echo "<td>" . ($exists ? "<span class='success'>✓ Var</span>" : "<span class='error'>✗ Yok</span>") . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 5. .htaccess İçeriği
echo "<div class='card'>";
echo "<h2>📄 .htaccess İçeriği</h2>";
if (file_exists('.htaccess')) {
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto;'>";
    echo htmlspecialchars(file_get_contents('.htaccess'));
    echo "</pre>";
} else {
    echo "<p class='error'>⚠️ .htaccess dosyası bulunamadı!</p>";
    echo "<p>Lütfen .htaccess dosyasını root dizine yükleyin.</p>";
}
echo "</div>";

// 6. Database Bağlantısı
echo "<div class='card'>";
echo "<h2>🗄️ Database Bağlantısı</h2>";
try {
    require_once 'core/Database.php';
    $db = Database::getInstance();
    echo "<p class='success'>✓ Database bağlantısı başarılı!</p>";
    
    // Aktif rental kontrolü
    require_once 'core/Auth.php';
    $auth = new Auth();
    
    if ($auth->check()) {
        $user = $auth->user();
        echo "<p>Kullanıcı: <strong>{$user['username']}</strong> (ID: {$user['id']})</p>";
        
        $rentals = $db->fetchAll("
            SELECT r.id, r.status, s.name as script_name, d.domain
            FROM rentals r
            JOIN scripts s ON r.script_id = s.id
            LEFT JOIN script_domains d ON r.domain_id = d.id
            WHERE r.user_id = ?
            ORDER BY r.id DESC
        ", [$user['id']]);
        
        if ($rentals) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Script</th><th>Domain</th><th>Status</th><th>Test URL</th></tr>";
            foreach ($rentals as $rental) {
                $manageUrl = Helper::url('rental/manage/' . $rental['id']);
                echo "<tr>";
                echo "<td>{$rental['id']}</td>";
                echo "<td>{$rental['script_name']}</td>";
                echo "<td>" . ($rental['domain'] ?? '-') . "</td>";
                echo "<td>{$rental['status']}</td>";
                echo "<td>";
                if ($rental['status'] === 'active') {
                    echo "<a href='$manageUrl' target='_blank'>Test Et →</a>";
                } else {
                    echo "<span class='error'>Aktif değil</span>";
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>⚠️ Hiç rental bulunamadı!</p>";
        }
    } else {
        echo "<p class='error'>⚠️ Giriş yapmadınız! <a href='" . Helper::url('login') . "'>Giriş Yap</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database hatası: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 7. mod_rewrite Kontrolü
echo "<div class='card'>";
echo "<h2>🔧 Apache mod_rewrite</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p class='success'>✓ mod_rewrite aktif</p>";
    } else {
        echo "<p class='error'>✗ mod_rewrite aktif değil!</p>";
        echo "<p>Lütfen Apache'de mod_rewrite'ı aktif edin:</p>";
        echo "<code>sudo a2enmod rewrite && sudo service apache2 restart</code>";
    }
} else {
    echo "<p>⚠️ Apache modüllerini kontrol edemiyorum. (Nginx kullanıyor olabilirsiniz)</p>";
}
echo "</div>";

// 8. Link Test Bölümü
echo "<div class='card'>";
echo "<h2>🧪 Manuel Test</h2>";
echo "<div class='test-box'>";
echo "<p><strong>Test adımları:</strong></p>";
echo "<ol>";
echo "<li>Aşağıdaki linklerden birine tıklayın</li>";
echo "<li>Ne olduğunu not edin (404 hatası mı, başka sayfa mı, vs)</li>";
echo "<li>Browser console'u açın (F12) ve hata var mı bakın</li>";
echo "</ol>";
echo "</div>";

echo "<p><strong>Test Linkleri:</strong></p>";
echo "<a href='" . Helper::url('rental') . "' target='_blank'>Kiralamalarım</a>";
echo "<a href='" . Helper::url('rental/manage/3') . "' target='_blank'>Rental Yönetimi #3</a>";
echo "<a href='" . Helper::url('rental/manage/3/ibans') . "' target='_blank'>İBAN Yönetimi #3</a>";
echo "<a href='" . Helper::url('dashboard') . "' target='_blank'>Dashboard</a>";
echo "</div>";

// 9. Çözüm Önerileri
echo "<div class='card'>";
echo "<h2>💡 Olası Sorunlar ve Çözümler</h2>";
echo "<ol>";
echo "<li><strong>404 Hatası alıyorsanız:</strong>
    <ul>
        <li>mod_rewrite aktif değil olabilir</li>
        <li>.htaccess dosyası yüklenmiş mi kontrol edin</li>
        <li>Apache AllowOverride All ayarlı mı kontrol edin</li>
    </ul>
</li>";
echo "<li><strong>Sayfa yüklenmiyor, boş sayfa:</strong>
    <ul>
        <li>PHP hata loglarını kontrol edin</li>
        <li>modules/rental/manage.php dosyası var mı kontrol edin</li>
        <li>Database bağlantısı çalışıyor mu kontrol edin</li>
    </ul>
</li>";
echo "<li><strong>Butona tıklayınca hiçbir şey olmuyor:</strong>
    <ul>
        <li>Browser console'da JavaScript hatası var mı bakın</li>
        <li>Cache temizleyin (Ctrl+Shift+Del)</li>
        <li>Başka browser'da deneyin</li>
    </ul>
</li>";
echo "</ol>";
echo "</div>";

echo "<div class='card'>";
echo "<p><a href='" . Helper::url('dashboard') . "'>← Dashboard'a Dön</a></p>";
echo "</div>";

echo "</body></html>";
?>
