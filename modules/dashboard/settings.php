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

<style>
.settings-card {
    background: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    margin-bottom: 24px;
    overflow: hidden;
}

.settings-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.settings-card-body {
    padding: 32px 24px;
    background: transparent;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.form-group input[type="text"],
.form-group input[type="password"] {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 12px 16px;
    color: white;
    width: 100%;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(0,0,0,0.4);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-group input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-group small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 6px;
    display: block;
}

.telegram-input-wrapper {
    position: relative;
    width: 100%;
}

.telegram-input-wrapper .at-symbol {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 15px;
    pointer-events: none;
    z-index: 1;
}

.telegram-input-wrapper input {
    padding-left: 36px !important;
}

.checkbox-container {
    padding: 20px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    margin-bottom: 24px;
}

.checkbox-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.custom-checkbox {
    position: relative;
    width: 24px;
    height: 24px;
    flex-shrink: 0;
}

.custom-checkbox input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.checkbox-mark {
    position: absolute;
    top: 0;
    left: 0;
    height: 24px;
    width: 24px;
    background-color: rgba(0,0,0,0.3);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.custom-checkbox input:checked ~ .checkbox-mark {
    background-color: var(--primary);
    border-color: var(--primary);
}

.checkbox-mark:after {
    content: "";
    position: absolute;
    display: none;
    left: 7px;
    top: 3px;
    width: 6px;
    height: 11px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.custom-checkbox input:checked ~ .checkbox-mark:after {
    display: block;
}

.checkbox-content {
    flex: 1;
}

.checkbox-content label {
    display: block;
    color: white;
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 6px;
    cursor: pointer;
}

.checkbox-content small {
    color: var(--text-muted);
    font-size: 13px;
}
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="text-white mb-4"><i class="bi bi-gear me-2"></i>Hesap Ayarları</h2>
            
            <?php if ($success): ?>
            <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); border-radius: 12px; margin-bottom: 24px;">
                <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger" style="background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); color: #ff4444; border-radius: 12px; margin-bottom: 24px;">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <!-- Profil Ayarları -->
            <div class="settings-card">
                <div class="settings-card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));">
                    <h5 class="mb-0 text-white"><i class="bi bi-person me-2"></i>Profil Bilgileri</h5>
                </div>
                <div class="settings-card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label>Kullanıcı Adı</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small><i class="bi bi-info-circle me-1"></i>Kullanıcı adı değiştirilemez</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Telegram Kullanıcı Adı</label>
                            <div class="telegram-input-wrapper">
                                <span class="at-symbol">@</span>
                                <input type="text" name="telegram_username" value="<?php echo htmlspecialchars(ltrim($user['telegram_username'] ?? '', '@')); ?>" placeholder="kullaniciadi">
                            </div>
                            <small><i class="bi bi-info-circle me-1"></i>Destek bildirimleri için kullanılır</small>
                        </div>
                        
                        <div class="checkbox-container">
                            <div class="checkbox-wrapper">
                                <label class="custom-checkbox">
                                    <input type="checkbox" id="is_hidden" name="is_hidden" value="1" <?php echo $user['is_hidden'] ? 'checked' : ''; ?>>
                                    <span class="checkbox-mark"></span>
                                </label>
                                <div class="checkbox-content">
                                    <label for="is_hidden">
                                        <i class="bi bi-incognito me-1"></i>Gizli Mod (İsmimi gizle)
                                    </label>
                                    <small>Script sahibi olarak "Gizli Tokatçı" görünür</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-weight: 600;">
                            <i class="bi bi-save me-2"></i>Kaydet
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Şifre Değiştir -->
            <div class="settings-card">
                <div class="settings-card-header" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));">
                    <h5 class="mb-0 text-white"><i class="bi bi-shield-lock me-2"></i>Şifre Değiştir</h5>
                </div>
                <div class="settings-card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label>Mevcut Şifre</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Yeni Şifre</label>
                            <input type="password" name="new_password" minlength="8" required>
                            <small><i class="bi bi-info-circle me-1"></i>En az 8 karakter</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Yeni Şifre Tekrar</label>
                            <input type="password" name="confirm_password" minlength="8" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning" style="padding: 12px 32px; font-weight: 600;">
                            <i class="bi bi-key me-2"></i>Şifreyi Değiştir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>