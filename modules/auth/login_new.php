<?php
$title = 'Giriş Yap';
require 'templates/auth_header_new.php';
?>

<h2 class="auth-title">Hoş Geldiniz</h2>

<?php if (isset($error)): ?>
<div class="alert-auth alert-danger">
    <i class="bi bi-exclamation-circle"></i>
    <?php echo $error; ?>
</div>
<?php endif; ?>

<form method="POST" action="<?php echo Helper::url('auth/login'); ?>">
    <div class="form-group">
        <div class="form-input-wrapper">
            <i class="bi bi-person"></i>
            <input type="text" 
                   name="username" 
                   class="auth-input" 
                   placeholder="Kullanıcı Adı veya E-posta"
                   required
                   autocomplete="username">
        </div>
    </div>

    <div class="form-group">
        <div class="form-input-wrapper">
            <i class="bi bi-lock"></i>
            <input type="password" 
                   name="password" 
                   class="auth-input" 
                   placeholder="Şifre"
                   required
                   autocomplete="current-password"
                   id="password">
            <button type="button" class="password-toggle">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="auth-btn">
        <i class="bi bi-box-arrow-in-right"></i>
        Giriş Yap
    </button>
</form>

<div class="auth-footer">
    <p>Hesabınız yok mu? <a href="<?php echo Helper::url('auth/register'); ?>">Kayıt Ol</a></p>
</div>

<?php require 'templates/auth_footer_new.php'; ?>
