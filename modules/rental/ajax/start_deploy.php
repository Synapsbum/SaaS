<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/phpseclib/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bootstrap.php';

$user = $auth->user();
$db = Database::getInstance();

$rentalId = (int)$_POST['rental_id'];
$r = $db->fetch("
 SELECT r.*, s.setup_command, s.ssh_host, s.ssh_user, s.ssh_pass, s.ssh_port
 FROM rentals r JOIN scripts s ON r.script_id=s.id
 WHERE r.id=? AND r.user_id=?
", [$rentalId, $user['id']]);

if (!$r) exit;

$log = __DIR__."/../deploy_logs/rental_{$rentalId}.user.log";
$status = __DIR__."/../deploy_logs/rental_{$rentalId}.status";

file_put_contents($log, "[+] Kurulum başlatıldı\n");
file_put_contents($status, "running");

function ulog($m){global $log; file_put_contents($log,$m."\n",FILE_APPEND);}

ignore_user_abort(true);
set_time_limit(0);

$ssh = new \phpseclib3\Net\SSH2($r['ssh_host'],$r['ssh_port']??22);
if(!$ssh->login($r['ssh_user'],$r['ssh_pass'])){
    ulog("[✗] Sunucuya bağlanılamadı");
    file_put_contents($status,"failed");
    exit;
}

$domain = json_decode($r['setup_data'],true)['domain'] ?? '';

ulog("[+] Nginx konfigürasyonu uygulanıyor");
$ssh->exec("cp -f /etc/nginx/_yedekler/{$domain}.conf /etc/nginx/sites-available/{$domain}.conf");
$ssh->exec("ln -sf /etc/nginx/sites-available/{$domain}.conf /etc/nginx/sites-enabled/{$domain}.conf");

ulog("[+] Nginx ayarları doğrulanıyor");
$test = $ssh->exec("nginx -t 2>&1");
if(!str_contains($test,'successful')){
    ulog("[✗] Nginx doğrulaması başarısız (NGINX-T-002)");
    file_put_contents($status,"failed");
    exit;
}

ulog("[+] Servisler başlatılıyor");
$ssh->exec("systemctl reload nginx");

ulog("[✓] Kurulum tamamlandı");
file_put_contents($status,"success");

$db->query("UPDATE rentals SET deploy_status='success' WHERE id=?",[$rentalId]);
