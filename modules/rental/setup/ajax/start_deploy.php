<?php
/**
 * AJAX: Kurulumu başlatır
 * - index.php bootstrap kullanır
 * - router BYPASS edilir (index.php içinden)
 * - log + status dosyası üretir
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/index.php';

/* ================= AUTH ================= */
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'yetkisiz']);
    exit;
}

$user = $auth->user();

/* ================= DB ================= */
$db = $db; // index.php bootstrap’ten geliyor

$rentalId = (int)($_POST['rental_id'] ?? 0);
if ($rentalId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'rental_id yok']);
    exit;
}

/* ================= RENTAL ================= */
$r = $db->fetch("
    SELECT r.*, s.setup_command, s.ssh_host, s.ssh_user, s.ssh_pass, s.ssh_port
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$r) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'rental bulunamadı']);
    exit;
}

/* ================= LOG DOSYALARI ================= */
$logDir = __DIR__ . '/../deploy_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile    = $logDir . "/rental_{$rentalId}.log";
$statusFile = $logDir . "/rental_{$rentalId}.status";

/* LOG YAZMA FONKSİYONU */
function ulog(string $msg): void {
    global $logFile;
    file_put_contents(
        $logFile,
        '[' . date('H:i:s') . '] ' . $msg . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/* ================= BAŞLAT ================= */
file_put_contents($logFile, '');
file_put_contents($statusFile, 'running');

ulog('Kurulum başlatıldı');

/* ================= SSH ================= */
require_once $_SERVER['DOCUMENT_ROOT'] . '/phpseclib/autoload.php';

ignore_user_abort(true);
set_time_limit(0);

try {
    $ssh = new \phpseclib3\Net\SSH2(
        $r['ssh_host'],
        $r['ssh_port'] ?: 22
    );

    if (!$ssh->login($r['ssh_user'], $r['ssh_pass'])) {
        ulog('Sunucuya bağlanılamadı');
        file_put_contents($statusFile, 'failed');
        echo json_encode(['ok' => false]);
        exit;
    }

    ulog('Nginx konfigürasyonu uygulanıyor');

    $setupData = json_decode($r['setup_data'], true);
    $domain = $setupData['domain'] ?? '';

    $ssh->exec("cp -f /etc/nginx/_yedekler/{$domain}.conf /etc/nginx/sites-available/{$domain}.conf");
    $ssh->exec("ln -sf /etc/nginx/sites-available/{$domain}.conf /etc/nginx/sites-enabled/{$domain}.conf");

    ulog('Nginx ayarları doğrulanıyor');

    $test = $ssh->exec("nginx -t 2>&1");
    if (strpos($test, 'successful') === false) {
        ulog('Nginx doğrulaması başarısız (NGINX-T-002)');
        file_put_contents($statusFile, 'failed');
        echo json_encode(['ok' => false]);
        exit;
    }

    ulog('Nginx yeniden yükleniyor');
    $ssh->exec("systemctl reload nginx");

    ulog('Kurulum tamamlandı');
    file_put_contents($statusFile, 'success');

    /* DB STATUS */
    $db->query("
        UPDATE rentals SET
            status = 'active',
            deploy_status = 'success',
            activated_at = NOW()
        WHERE id = ?
    ", [$rentalId]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    ulog('Hata: ' . $e->getMessage());
    file_put_contents($statusFile, 'failed');
    echo json_encode(['ok' => false]);
}
