<?php
/**
 * Analytics Tracking API Endpoint
 * Kiralanan sitelerden gelen analytics verilerini toplar
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

$db = Database::getInstance();

// POST verilerini al
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['type']) || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$type = $data['type'];
$trackData = $data['data'];
$rentalId = $trackData['rental_id'] ?? null;

if (!$rentalId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rental ID required']);
    exit;
}

// IP adresini al
$visitorIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (strpos($visitorIp, ',') !== false) {
    $visitorIp = trim(explode(',', $visitorIp)[0]);
}

try {
    switch ($type) {
        case 'pageview':
            handlePageview($db, $rentalId, $trackData, $visitorIp);
            break;
            
        case 'heartbeat':
            handleHeartbeat($db, $rentalId, $trackData, $visitorIp);
            break;
            
        case 'deposit':
            handleDeposit($db, $rentalId, $trackData, $visitorIp);
            break;
            
        case 'session_end':
            handleSessionEnd($db, $rentalId, $trackData);
            break;
            
        case 'custom_event':
            handleCustomEvent($db, $rentalId, $trackData);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown type']);
            exit;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Analytics API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Pageview kaydı
 */
function handlePageview($db, $rentalId, $data, $visitorIp) {
    $sessionId = $data['session_id'] ?? '';
    $pageUrl = $data['page_url'] ?? '';
    $userAgent = $data['user_agent'] ?? '';
    
    // Şehir bilgisini IP'den al (GeoIP kullanabilirsiniz)
    $cityData = getCityFromIp($visitorIp);
    
    $visitDate = date('Y-m-d');
    $visitTime = date('Y-m-d H:i:s');
    
    // Unique visitor kontrolü (bugün bu IP daha önce geldi mi?)
    $existing = $db->fetch("
        SELECT id FROM rental_analytics 
        WHERE rental_id = ? AND visitor_ip = ? AND visit_date = ?
        LIMIT 1
    ", [$rentalId, $visitorIp, $visitDate]);
    
    $isUnique = empty($existing) ? 1 : 0;
    
    // Analytics kaydı ekle
    $db->query("
        INSERT INTO rental_analytics 
        (rental_id, visitor_ip, city, region, country, page_url, user_agent, session_id, visit_date, visit_time, is_unique)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $rentalId,
        $visitorIp,
        $cityData['city'],
        $cityData['region'],
        $cityData['country'],
        $pageUrl,
        $userAgent,
        $sessionId,
        $visitDate,
        $visitTime,
        $isUnique
    ]);
    
    // Summary güncelle
    updateDailySummary($db, $rentalId, $visitDate);
    
    // Şehir bazlı istatistikleri güncelle
    if ($cityData['city']) {
        updateCityStats($db, $rentalId, $cityData, $visitDate, $isUnique);
    }
    
    // Aktif session güncelle
    updateActiveSession($db, $rentalId, $sessionId, $visitorIp);
}

/**
 * Heartbeat (aktif kullanıcı takibi)
 */
function handleHeartbeat($db, $rentalId, $data, $visitorIp) {
    $sessionId = $data['session_id'] ?? '';
    
    // Aktif session güncelle
    updateActiveSession($db, $rentalId, $sessionId, $visitorIp);
    
    // Son 5 dakika içindeki aktif kullanıcı sayısını güncelle
    $activeCount = $db->fetch("
        SELECT COUNT(DISTINCT session_id) as cnt
        FROM rental_active_sessions
        WHERE rental_id = ? AND last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ", [$rentalId])['cnt'] ?? 0;
    
    // Summary'yi güncelle
    $db->query("
        UPDATE rental_analytics_summary 
        SET active_users_now = ?
        WHERE rental_id = ? AND date = CURDATE()
    ", [$activeCount, $rentalId]);
}

/**
 * Para yatırma işlemi
 */
function handleDeposit($db, $rentalId, $data, $visitorIp) {
    $amount = $data['amount_try'] ?? 0;
    $method = $data['payment_method'] ?? 'unknown';
    $transactionId = $data['transaction_id'] ?? null;
    
    // Deposit kaydı ekle
    $db->query("
        INSERT INTO rental_deposits 
        (rental_id, user_ip, amount_try, payment_method, transaction_id, status)
        VALUES (?, ?, ?, ?, ?, 'completed')
    ", [$rentalId, $visitorIp, $amount, $method, $transactionId]);
    
    // Summary güncelle
    $db->query("
        UPDATE rental_analytics_summary 
        SET total_deposits_try = total_deposits_try + ?,
            deposit_count = deposit_count + 1
        WHERE rental_id = ? AND date = CURDATE()
    ", [$amount, $rentalId]);
}

/**
 * Session sonu
 */
function handleSessionEnd($db, $rentalId, $data) {
    $sessionId = $data['session_id'] ?? '';
    
    // Aktif session'dan kaldır
    $db->query("
        DELETE FROM rental_active_sessions 
        WHERE rental_id = ? AND session_id = ?
    ", [$rentalId, $sessionId]);
}

/**
 * Custom event
 */
function handleCustomEvent($db, $rentalId, $data) {
    // Custom event'ler için ayrı bir tablo oluşturabilirsiniz
    // Şimdilik log'layalım
    error_log("Custom Event for Rental {$rentalId}: " . json_encode($data));
}

/**
 * Günlük özeti güncelle
 */
function updateDailySummary($db, $rentalId, $date) {
    // Unique visitor ve pageview sayıları
    $stats = $db->fetch("
        SELECT 
            COUNT(DISTINCT CASE WHEN is_unique = 1 THEN visitor_ip END) as unique_visitors,
            COUNT(*) as total_pageviews
        FROM rental_analytics
        WHERE rental_id = ? AND visit_date = ?
    ", [$rentalId, $date]);
    
    // Insert or update
    $db->query("
        INSERT INTO rental_analytics_summary 
        (rental_id, date, unique_visitors, total_pageviews)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            unique_visitors = VALUES(unique_visitors),
            total_pageviews = VALUES(total_pageviews)
    ", [
        $rentalId,
        $date,
        $stats['unique_visitors'] ?? 0,
        $stats['total_pageviews'] ?? 0
    ]);
}

/**
 * Şehir bazlı istatistikleri güncelle
 */
function updateCityStats($db, $rentalId, $cityData, $date, $isUnique) {
    $city = $cityData['city'];
    $lat = $cityData['latitude'];
    $lng = $cityData['longitude'];
    
    $db->query("
        INSERT INTO rental_analytics_by_city 
        (rental_id, city, date, visitor_count, pageview_count, latitude, longitude)
        VALUES (?, ?, ?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE 
            visitor_count = visitor_count + ?,
            pageview_count = pageview_count + 1
    ", [
        $rentalId,
        $city,
        $date,
        $isUnique,
        $lat,
        $lng,
        $isUnique
    ]);
}

/**
 * Aktif session güncelle
 */
function updateActiveSession($db, $rentalId, $sessionId, $visitorIp) {
    $db->query("
        INSERT INTO rental_active_sessions 
        (rental_id, session_id, visitor_ip, last_activity)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            last_activity = NOW()
    ", [$rentalId, $sessionId, $visitorIp]);
}

/**
 * IP'den şehir bilgisi al
 * GeoIP2 veya başka bir servis kullanabilirsiniz
 */
function getCityFromIp($ip) {
    // Basit bir çözüm: ip-api.com (ücretsiz, ama limit var)
    // Üretim için GeoIP2 database kullanın
    
    // Türkiye şehirleri listesi
    $turkishCities = [
        'İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya', 'Adana', 
        'Gaziantep', 'Konya', 'Mersin', 'Kayseri', 'Eskişehir', 'Diyarbakır'
    ];
    
    // Şimdilik rastgele bir şehir döndürelim (test için)
    // Gerçek uygulamada GeoIP kullanın
    $randomCity = $turkishCities[array_rand($turkishCities)];
    
    $cityCoordinates = [
        'İstanbul' => ['lat' => 41.0082, 'lng' => 28.9784],
        'Ankara' => ['lat' => 39.9334, 'lng' => 32.8597],
        'İzmir' => ['lat' => 38.4237, 'lng' => 27.1428],
        'Bursa' => ['lat' => 40.1885, 'lng' => 29.0610],
        'Antalya' => ['lat' => 36.8969, 'lng' => 30.7133],
        'Adana' => ['lat' => 37.0000, 'lng' => 35.3213],
        'Gaziantep' => ['lat' => 37.0662, 'lng' => 37.3833],
        'Konya' => ['lat' => 37.8746, 'lng' => 32.4932],
        'Mersin' => ['lat' => 36.8121, 'lng' => 34.6415],
        'Kayseri' => ['lat' => 38.7205, 'lng' => 35.4826],
        'Eskişehir' => ['lat' => 39.7767, 'lng' => 30.5206],
        'Diyarbakır' => ['lat' => 37.9144, 'lng' => 40.2306]
    ];
    
    $coords = $cityCoordinates[$randomCity] ?? ['lat' => 39.9334, 'lng' => 32.8597];
    
    return [
        'city' => $randomCity,
        'region' => 'Türkiye',
        'country' => 'TR',
        'latitude' => $coords['lat'],
        'longitude' => $coords['lng']
    ];
}
