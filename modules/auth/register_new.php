<?php
$title = 'Kayıt Ol';
require 'templates/auth_header_new.php';
?>

<h2 class="auth-title">Hesap Oluştur</h2>

<?php if (isset($error)): ?>
<div class="alert-auth alert-danger">
    <i class="bi bi-exclamation-circle"></i>
    <?php echo $error; ?>
</div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert-auth alert-success">
    <i class="bi bi-check-circle"></i>
    <?php echo $success; ?>
</div>
<?php endif; ?>

<form method="POST" action="<?php echo Helper::url('auth/register'); ?>">
    <div class="form-group">
        <div class="form-input-wrapper">
            <i class="bi bi-person"></i>
            <input type="text" 
                   name="username" 
                   class="auth-input" 
                   placeholder="Kullanıcı Adı"
                   required
                   autocomplete="username">
        </div>
    </div>

    <div class="form-group">
        <div class="form-input-wrapper">
            <i class="bi bi-envelope"></i>
            <input type="email" 
                   name="email" 
                   class="auth-input" 
                   placeholder="E-posta Adresi"
                   required
                   autocomplete="email">
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
                   autocomplete="new-password"
                   id="password">
            <button type="button" class="password-toggle">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="form-group">
        <div class="form-input-wrapper">
            <i class="bi bi-lock-fill"></i>
            <input type="password" 
                   name="password_confirm" 
                   class="auth-input" 
                   placeholder="Şifre Tekrar"
                   required
                   autocomplete="new-password"
                   id="password_confirm">
            <button type="button" class="password-toggle">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="auth-btn">
        <i class="bi bi-person-plus"></i>
        Kayıt Ol
    </button>
</form>

<div class="auth-footer">
    <p>Zaten hesabınız var mı? <a href="<?php echo Helper::url('auth/login'); ?>">Giriş Yap</a></p>
</div>

<?php require 'templates/auth_footer_new.php'; ?>
