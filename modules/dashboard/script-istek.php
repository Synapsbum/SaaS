<?php
$title = 'Script İste';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $error = 'Güvenlik hatası';
    } else {
        try {
            $db->insert('script_requests', [
                'user_id' => $auth->userId(),
                'script_name' => $_POST['script_name'],
                'description' => $_POST['description'],
                'budget' => $_POST['budget'] ?: null,
                'contact_info' => $_POST['contact_info'] ?: null,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = true;
            
            // Opsiyonel: Telegram bildirimi burada da gönderilebilir
            // Ama genelde admin onayı ile gönderilir
        } catch (Exception $e) {
            $error = 'İstek gönderilirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Önceki isteklerini göster
$myRequests = $db->fetchAll("
    SELECT * FROM script_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
", [$auth->userId()]);

require 'templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($success): ?>
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>İsteğiniz alındı!</strong> En kısa sürede değerlendirilecek ve size dönüş yapılacak.
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Yeni Script İste</h4>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Script Adı / Türü *</label>
                        <input type="text" name="script_name" class="form-control form-control-dark" placeholder="örn: E-Ticaret Scripti, Wallet Clone vb." required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Detaylı Açıklama *</label>
                        <textarea name="description" class="form-control form-control-dark" rows="5" placeholder="İstediğiniz scriptin özellikleri, referans siteler, özel istekler..." required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Bütçe Aralığı</label>
                            <select name="budget" class="form-select form-control-dark">
                                <option value="">Seçin (Opsiyonel)</option>
                                <option value="0-500">0 - 500 USDT</option>
                                <option value="500-1000">500 - 1000 USDT</option>
                                <option value="1000-5000">1000 - 5000 USDT</option>
                                <option value="5000+">5000+ USDT</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">İletişim Bilgisi (Opsiyonel)</label>
                            <input type="text" name="contact_info" class="form-control form-control-dark" placeholder="Telegram @kullanici veya email">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-admin btn-primary-custom w-100">
                        <i class="bi bi-send me-2"></i>İsteği Gönder
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Önceki İsteklerim -->
        <?php if (!empty($myRequests)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Önceki İsteklerim</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Script</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myRequests as $req): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($req['script_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $req['status'] == 'pending' ? 'warning' : 
                                         ($req['status'] == 'reviewed' ? 'info' : 
                                         ($req['status'] == 'completed' ? 'success' : 'secondary')); 
                                ?>">
                                    <?php 
                                    echo $req['status'] == 'pending' ? 'Bekliyor' : 
                                         ($req['status'] == 'reviewed' ? 'İnceleniyor' : 
                                         ($req['status'] == 'completed' ? 'Tamamlandı' : 'Reddedildi')); 
                                    ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($req['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 'templates/footer.php'; ?>