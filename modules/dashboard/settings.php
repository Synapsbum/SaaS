<?php
$title = 'Ayarlar';
$user = $auth->user();
$db = Database::getInstance();

$success = '';
$error = '';

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!Security::validateToken($_POST['csrf_token'])) {
        $error = 'Güvenlik hatası';
    } else {
        $isHidden = isset($_POST['is_hidden']) ? 1 : 0;
        $telegram = trim($_POST['telegram_username'] ?? '');
        
        // Telegram @ işareti kontrolü
        if ($telegram && !str_starts_with($telegram, '@')) {
            $telegram = '@' . $telegram;
        }
        
        $db->update('users', [
            'is_hidden' => $isHidden,
            'telegram_username' => $telegram ?: null
        ], 'id = ?', [$user['id']]);
        
        $success = 'Profil güncellendi';
    }
}

// Şifre değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!Security::validateToken($_POST['csrf_token'])) {
        $error = 'Güvenlik hatası';
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        // Mevcut şifre kontrolü
        $dbUser = $db->fetch("SELECT password_hash FROM users WHERE id = ?", [$user['id']]);
        
        if (!Security::verifyPassword($currentPass, $dbUser['password_hash'])) {
            $error = 'Mevcut şifre yanlış';
        } elseif (strlen($newPass) < 8) {
            $error = 'Yeni şifre en az 8 karakter olmalı';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Şifreler eşleşmiyor';
        } else {
            $db->update('users', [
                'password_hash' => Security::hashPassword($newPass)
            ], 'id = ?', [$user['id']]);
            
            $success = 'Şifre değiştirildi';
        }
    }
}

// Güncel kullanıcı bilgisi
$user = $auth->user();

require 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="text-white mb-4"><i class="bi bi-gear me-2"></i>Hesap Ayarları</h2>
            
            <?php if ($success): ?>
            <div class="alert alert-success mb-4">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <!-- Profil Ayarları -->
            <div class="card mb-4" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0 text-white"><i class="bi bi-person me-2"></i>Profil Bilgileri</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label text-white">Kullanıcı Adı</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" value="<?php echo $user['username']; ?>" disabled>
                            <small class="text-muted">Kullanıcı adı değiştirilemez</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-white">Telegram Kullanıcı Adı</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted">@</span>
                                <input type="text" name="telegram_username" class="form-control bg-dark text-white border-secondary" value="<?php echo ltrim($user['telegram_username'] ?? '', '@'); ?>" placeholder="kullaniciadi">
                            </div>
                            <small class="text-muted">Destek bildirimleri için kullanılır</small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_hidden" name="is_hidden" value="1" <?php echo $user['is_hidden'] ? 'checked' : ''; ?>>
                            <label class="form-check-label text-white" for="is_hidden">
                                <i class="bi bi-incognito me-1"></i>Gizli Mod (İsmimi gizle)
                            </label>
                            <small class="text-muted d-block">Script sahibi olarak "Gizli Tokatçı" görünür</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Kaydet
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Şifre Değiştir -->
            <div class="card" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="card-header" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05)); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0 text-white"><i class="bi bi-shield-lock me-2"></i>Şifre Değiştir</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label text-white">Mevcut Şifre</label>
                            <input type="password" name="current_password" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-white">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control bg-dark text-white border-secondary" minlength="8" required>
                            <small class="text-muted">En az 8 karakter</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-white">Yeni Şifre Tekrar</label>
                            <input type="password" name="confirm_password" class="form-control bg-dark text-white border-secondary" minlength="8" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-2"></i>Şifreyi Değiştir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>