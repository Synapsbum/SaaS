<?php
// Admin Header - Modern Layout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($title)) $title = 'Admin Panel';
if (!isset($csrfToken)) $csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? bin2hex(random_bytes(32));

// Admin kontrolü (eğer $auth tanımlıysa)
if (isset($auth) && !$auth->isAdmin()) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg-dark: #0f0f1e;
            --bg-card: #1a1a2e;
            --border-color: rgba(255,255,255,0.1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            padding: 24px 0;
        }
        
        .sidebar-brand {
            padding: 0 24px 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .sidebar-brand h4 {
            color: #fff;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #818cf8, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-section {
            padding: 0 16px;
            margin-bottom: 24px;
        }
        
        .nav-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #71717a;
            margin-bottom: 12px;
            padding: 0 12px;
            font-weight: 700;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #a1a1aa;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 4px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(99, 102, 241, 0.15);
            color: #fff;
        }
        
        .nav-link i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .admin-main {
            margin-left: var(--sidebar-width);
            padding: 32px;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .admin-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .admin-card-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-card-body {
            padding: 24px;
        }
        
        .admin-table {
            width: 100%;
            color: #fff;
            margin-bottom: 0;
        }
        
        .admin-table thead th {
            background: rgba(0,0,0,0.2);
            padding: 16px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #71717a;
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
        }
        
        .admin-table tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .admin-table tbody tr:hover td {
            background: rgba(255,255,255,0.02);
        }
        
        .btn-admin {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #818cf8, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: #71717a;
            font-size: 14px;
        }
        
        .form-control-dark, .form-select {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
        }
        
        .form-control-dark:focus, .form-select:focus {
            background: rgba(0,0,0,0.4);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: #fff;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 20px 24px;
        }
        
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        .alert-admin {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success-custom {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        .alert-danger-custom {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        @media (max-width: 991px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .admin-sidebar.show {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="admin-sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-shield-lock me-2"></i>Admin Panel</h4>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">Ana Menü</div>
        <a href="/admin" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="/admin/users" class="nav-link">
            <i class="bi bi-people"></i> Kullanıcılar
        </a>
        <a href="/admin/rentals" class="nav-link">
            <i class="bi bi-key"></i> Kiralamalar
        </a>
        <a href="/admin/scripts" class="nav-link">
            <i class="bi bi-box-seam"></i> Scriptler
        </a>
        <a href="/admin/domains" class="nav-link">
            <i class="bi bi-globe"></i> Domainler
        </a>
        <a href="/admin/sans-carki" class="nav-link">
            <i class="bi bi-globe"></i> Sans Carki
        </a>
        <a href="/admin/script-istekleri" class="nav-link">
            <i class="bi bi-globe"></i> Script Istekleri
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">Finans</div>
        <a href="/admin/payments" class="nav-link">
            <i class="bi bi-cash-stack"></i> Ödemeler
        </a>
        <a href="/admin/coupons" class="nav-link">
            <i class="bi bi-ticket-perforated"></i> Kuponlar
        </a>
        <a href="/admin/premium" class="nav-link">
            <i class="bi bi-star"></i> Premium
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">Destek</div>
        <a href="/admin/tickets" class="nav-link">
            <i class="bi bi-headset"></i> Ticketler
        </a>
    </div>
    
    <div class="nav-section" style="margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 20px;">
        <a href="/dashboard" class="nav-link">
            <i class="bi bi-arrow-left"></i> Siteye Dön
        </a>
        <a href="/logout" class="nav-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Çıkış Yap
        </a>
    </div>
</div>

<div class="admin-main">
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert-admin alert-success-custom">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <span><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></span>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert-admin alert-danger-custom">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <span><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></span>
    </div>
    <?php endif; ?>
    
    <div class="page-header">
        <h1><?php echo htmlspecialchars($title); ?></h1>
    </div>