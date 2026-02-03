<?php
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız';
    } else {
        $result = $auth->register(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['telegram'] ?? null,
            $_POST['email'] ?? null
        );
        
        if ($result['success']) {
            $success = true;
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f0f1e">
    <title>Kayıt - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0f0f1e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 70% 50%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
            animation: gradient-shift 15s ease infinite;
            pointer-events: none;
            z-index: 0;
        }
        
        @keyframes gradient-shift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(5%, 5%) rotate(120deg); }
            66% { transform: translate(-5%, 5%) rotate(240deg); }
        }
        
        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            padding: 20px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .auth-card {
            background: rgba(26, 26, 46, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 48px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .auth-logo h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #818cf8, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .auth-logo p {
            color: #a1a1aa;
            font-size: 15px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 16px;
            position: relative;
        }
        
        .form-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #71717a;
            font-size: 18px;
            pointer-events: none;
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(26, 26, 46, 0.8);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-control::placeholder { color: #71717a; }
        
        .input-group { position: relative; }
        
        .input-group-text {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #71717a;
            font-weight: 600;
            z-index: 2;
            padding: 0;
        }
        
        .input-group .form-control {
            padding-left: 48px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }
        
        .btn-primary:active { transform: translateY(0); }
        
        .auth-footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .auth-footer p {
            color: #a1a1aa;
            font-size: 14px;
            margin: 0;
        }
        
        .auth-footer a {
            color: #818cf8;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .auth-footer a:hover { color: #ec4899; }
        
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
            color: #10b981;
        }
        
        .alert-success a {
            color: #10b981;
            font-weight: 700;
            text-decoration: underline;
        }
        
        .alert i { font-size: 18px; }
        
        @media (max-width: 576px) {
            .auth-card { padding: 32px 24px; }
            .auth-logo h1 { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Yeni hesap oluşturun</p>
            </div>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>Hesabınız oluşturuldu! <a href="<?php echo Helper::url('login'); ?>">Giriş yapın</a></span>
            </div>
            <?php else: ?>
                <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?php foreach ($errors as $err) echo $err . '<br>'; ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-group">
                        <i class="bi bi-person form-icon"></i>
                        <input type="text" name="username" class="form-control" placeholder="Kullanıcı adı *" required>
                    </div>
                    
                    <div class="form-group">
                        <i class="bi bi-lock form-icon"></i>
                        <input type="password" name="password" class="form-control" placeholder="Şifre * (en az 8 karakter)" required>
                    </div>
                    
                    <div class="form-group">
                        <i class="bi bi-lock-fill form-icon"></i>
                        <input type="password" name="password_confirm" class="form-control" placeholder="Şifre tekrar *" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" name="telegram" class="form-control" placeholder="Telegram (isteğe bağlı)">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <i class="bi bi-envelope form-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="Email (isteğe bağlı)">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-person-plus"></i>
                        Kayıt Ol
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Zaten hesabınız var mı? <a href="<?php echo Helper::url('login'); ?>">Giriş Yap</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
