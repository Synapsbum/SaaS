<?php
$title = 'Kurulum';
$user = $auth->user();
$db = Database::getInstance();

$rentalId = $id ?? 0;
$rental = $db->fetch("
    SELECT r.*, s.name as script_name, s.setup_command, s.ssh_host, s.ssh_user, s.ssh_pass
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$rental) {
    Helper::flash('error', 'Kiralama bulunamadı');
    Helper::redirect('rental');
}

$step = 1;
if ($rental['status'] == 'setup_config') $step = 2;
if ($rental['status'] == 'setup_deploy') $step = 3;

// Domain havuzu
$domains = $db->fetchAll("SELECT * FROM script_domains WHERE script_id = ? AND status = 'available'", [$rental['script_id']]);

// Kullanıcının IBANları
$ibans = $db->fetchAll("SELECT * FROM user_ibans WHERE user_id = ? AND is_active = 1", [$user['id']]);

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        switch ($step) {
            case 1: // Domain seçimi
                $domainId = $_POST['domain_id'] ?? 0;
                $domain = $db->fetch("SELECT * FROM script_domains WHERE id = ? AND status = 'available'", [$domainId]);
                
                if ($domain) {
                    $db->beginTransaction();
                    
                    $db->update('script_domains', [
                        'status' => 'in_use',
                        'current_user_id' => $user['id'],
                        'assigned_until' => date('Y-m-d H:i:s', strtotime('+' . $rental['duration_hours'] . ' hours'))
                    ], 'id = ?', [$domainId]);
                    
                    $setupData = json_encode(['domain' => $domain['domain'], 'step1_at' => date('Y-m-d H:i:s')]);
                    
                    $db->update('rentals', [
                        'domain_id' => $domainId,
                        'status' => 'setup_config',
                        'setup_data' => $setupData
                    ], 'id = ?', [$rentalId]);
                    
                    $db->commit();
                    Helper::redirect('rental/setup/' . $rentalId);
                }
                break;
                
            case 2: // Konfigürasyon
                $setupData = json_decode($rental['setup_data'], true) ?: [];
                $setupData['ibans'] = $_POST['ibans'] ?? [];
                $setupData['tawkto_id'] = $_POST['tawkto_id'] ?? '';
                $setupData['withdrawal_limit'] = $_POST['withdrawal_limit'] ?? '';
                $setupData['step2_at'] = date('Y-m-d H:i:s');
                
                $db->update('rentals', [
                    'status' => 'setup_deploy',
                    'setup_data' => json_encode($setupData)
                ], 'id = ?', [$rentalId]);
                
                Helper::redirect('rental/setup/' . $rentalId);
                break;
                
            case 3: // Deploy
                // SSH ile kurulum
                $setupData = json_decode($rental['setup_data'], true) ?: [];
                $domain = $setupData['domain'] ?? '';
                
                // SSH komutunu hazırla
                $command = $rental['setup_command'];
                $command = str_replace('{DOMAIN}', $domain, $command);
                $command = str_replace('{USER_ID}', $user['id'], $command);
                $command = str_replace('{DURATION}', $rental['duration_hours'], $command);
                
                // SSH bağlantısı ve komut çalıştırma
                $output = '';
                $returnVar = 0;
                
                if ($rental['ssh_host']) {
                    // key-based veya password auth
                    $connection = ssh2_connect($rental['ssh_host'], 22);
                    if ($connection) {
                        if (@ssh2_auth_password($connection, $rental['ssh_user'], $rental['ssh_pass'])) {
                            $stream = ssh2_exec($connection, $command);
                            stream_set_blocking($stream, true);
                            $output = stream_get_contents($stream);
                            fclose($stream);
                            
                            // Nginx restart
                            $restart = ssh2_exec($connection, 'sudo systemctl restart nginx');
                            fclose($restart);
                            
                            $returnVar = 0;
                        } else {
                            $returnVar = 1;
                            $output = "SSH auth failed";
                        }
                    } else {
                        $returnVar = 1;
                        $output = "SSH connection failed";
                    }
                }
                
                if ($returnVar === 0) {
                    $db->update('rentals', [
                        'status' => 'active',
                        'activated_at' => date('Y-m-d H:i:s'),
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $rental['duration_hours'] . ' hours')),
                        'deploy_log' => $output,
                        'deploy_status' => 'success'
                    ], 'id = ?', [$rentalId]);
                    
                    Helper::flash('success', 'Kurulum tamamlandı! Script aktif.');
                    Helper::redirect('rental');
                } else {
                    $db->update('rentals', [
                        'deploy_log' => $output,
                        'deploy_status' => 'failed'
                    ], 'id = ?', [$rentalId]);
                    
                    Helper::flash('error', 'Kurulum başarısız: ' . substr($output, 0, 200));
                }
                break;
        }
    }
}

require 'templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $rental['script_name']; ?> - Kurulum (Adım <?php echo $step; ?>/3)</h5>
            </div>
            <div class="card-body">
                
                <!-- Progress -->
                <div class="progress mb-4">
                    <div class="progress-bar bg-success" style="width: <?php echo ($step / 3) * 100; ?>%"></div>
                </div>
                
                <?php if ($step == 1): ?>
                <!-- Adım 1: Domain Seçimi -->
                <h6>1. Domain Seçimi</h6>
                <p class="text-muted">Scriptinizin kurulacağı domaini seçin</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <?php foreach ($domains as $domain): ?>
                        <div class="form-check card p-3 mb-2">
                            <input class="form-check-input" type="radio" name="domain_id" value="<?php echo $domain['id']; ?>" required>
                            <label class="form-check-label">
                                <strong><?php echo $domain['domain']; ?></strong>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Devam Et</button>
                </form>
                
                <?php elseif ($step == 2): ?>
                <!-- Adım 2: Konfigürasyon -->
                <h6>2. Site Ayarları</h6>
                <p class="text-muted">Ödeme ve destek ayarlarını yapılandırın (isteğe bağlı)</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Tawk.to Chat ID</label>
                        <input type="text" name="tawkto_id" class="form-control" placeholder="örn: 5f8a9b...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Çekim Limiti (USDT)</label>
                        <input type="number" name="withdrawal_limit" class="form-control" placeholder="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">IBAN Bilgileri</label>
                        <div class="alert alert-info">
                            <small>IBANlarınızı hesap ayarlarından ekleyebilirsiniz.</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Devam Et</button>
                    <a href="<?php echo Helper::url('rental/setup/' . $rentalId . '?skip=1'); ?>" class="btn btn-link">Atla</a>
                </form>
                
                <?php else: ?>
                <!-- Adım 3: Deploy -->
                <h6>3. Kurulumu Tamamla</h6>
                <p class="text-muted">Sunucuya bağlanıyor ve script kuruluyor...</p>
                
                <?php 
                $setupData = json_decode($rental['setup_data'], true) ?: [];
                ?>
                <div class="alert alert-info">
                    <strong>Domain:</strong> <?php echo $setupData['domain'] ?? ''; ?><br>
                    <strong>Süre:</strong> <?php echo $rental['duration_hours']; ?> saat
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-rocket-takeoff"></i> Kurulumu Başlat
                    </button>
                </form>
                
                <?php if ($rental['deploy_log']): ?>
                <div class="mt-3">
                    <small class="text-muted">Son log:</small>
                    <pre class="bg-dark p-2 rounded text-muted small"><?php echo nl2br($rental['deploy_log']); ?></pre>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>