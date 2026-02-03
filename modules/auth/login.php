<?php
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması başarısız';
    } else {
        $result = $auth->login(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            isset($_POST['remember'])
        );
        
        if ($result['success']) {
            Helper::redirect('dashboard');
        } else {
            $error = $result['message'];
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
    <title>Giriş - <?php echo SITE_NAME; ?></title>
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
            margin-bottom: 20px;
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
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: #6366f1;
            border-color: #6366f1;
        }
        
        .form-check-label {
            color: #a1a1aa;
            font-size: 14px;
            cursor: pointer;
            margin: 0;
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
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #ef4444;
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
                <p>Hesabınıza giriş yapın</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert">
                <i class="bi bi-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <i class="bi bi-person form-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Kullanıcı adı" required>
                </div>
                
                <div class="form-group">
                    <i class="bi bi-lock form-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="Şifre" required>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Beni hatırla</label>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Giriş Yap
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Hesabınız yok mu? <a href="<?php echo Helper::url('register'); ?>">Kayıt Ol</a></p>
            </div>
        </div>
    </div>
</body>
</html>
