<?php
$user = $auth->user();
$currentPage = $page;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f0f1e">
    <title><?php echo $title ?? SITE_NAME; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        /* ===== CSS VARIABLES ===== */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #8b5cf6;
            --accent: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            
            --bg-primary: #0f0f1e;
            --bg-secondary: #1a1a2e;
            --bg-tertiary: #16213e;
            --bg-card: rgba(26, 26, 46, 0.6);
            --bg-sidebar: rgba(15, 15, 30, 0.95);
            
            --text-primary: #ffffff;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            
            --border-color: rgba(255, 255, 255, 0.1);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.3);
            
            --sidebar-width: 280px;
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --transition-base: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
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
            z-index: -1;
        }
        
        @keyframes gradient-shift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(5%, 5%) rotate(120deg); }
            66% { transform: translate(-5%, 5%) rotate(240deg); }
        }
        
        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--bg-secondary); }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--primary-light), var(--accent));
        }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-sidebar);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform var(--transition-base);
            overflow-y: auto;
        }
        
        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        }
        
        .sidebar-logo h4 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            margin-bottom: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 15px;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary), var(--accent));
            transform: scaleY(0);
            transition: transform var(--transition-base);
        }
        
        .nav-link:hover {
            color: var(--text-primary);
            background: rgba(99, 102, 241, 0.1);
            transform: translateX(4px);
        }
        
        .nav-link:hover::before { transform: scaleY(1); }
        
        .nav-link.active {
            color: var(--text-primary);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1));
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        
        .nav-link.active::before { transform: scaleY(1); }
        
        .nav-link i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .nav-link.premium-link {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .nav-link.premium-link:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(251, 191, 36, 0.2));
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.3);
            transform: translateX(4px) scale(1.02);
        }
        
        .nav-link.premium-link.active {
            background: linear-gradient(135deg, var(--warning), #fbbf24);
            color: #000;
            font-weight: 700;
        }
        
        .sidebar-user {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: rgba(26, 26, 46, 0.4);
        }
        
        .user-info-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }
        
        .user-details {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-balance {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .logout-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-base);
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            width: 44px;
            height: 44px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            z-index: 1001;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            font-size: 20px;
            transition: all var(--transition-base);
        }
        
        .mobile-menu-toggle:hover {
            background: var(--primary);
            transform: scale(1.05);
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            transition: margin-left var(--transition-base);
        }
        
        .top-navbar {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-md);
        }
        
        .navbar-title {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .premium-badge {
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--warning), #fbbf24);
            color: #000;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.5); }
            50% { box-shadow: 0 0 30px rgba(245, 158, 11, 0.8); }
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .mobile-menu-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content {
                margin-left: 0;
                padding: 80px 16px 16px;
            }
            .top-navbar {
                padding: 12px 16px;
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .navbar-title { font-size: 22px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 70px 12px 12px; }
            .navbar-title { font-size: 20px; }
            .sidebar-logo h4 { font-size: 20px; }
            .nav-link {
                padding: 12px 14px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="bi bi-list"></i>
    </div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <h4><?php echo SITE_NAME; ?></h4>
        </div>

        <nav class="sidebar-nav">
            <a class="nav-link <?php echo $currentPage == 'dashboard' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('dashboard'); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            
            <a class="nav-link <?php echo $currentPage == 'scripts' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('scripts'); ?>">
                <i class="bi bi-box-seam"></i>
                <span>Scriptler</span>
            </a>
            
            <a class="nav-link <?php echo $currentPage == 'rental' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('rental'); ?>">
                <i class="bi bi-key"></i>
                <span>Kiralamalarım</span>
            </a>
            
            <a class="nav-link <?php echo $currentPage == 'payment' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('payment'); ?>">
                <i class="bi bi-wallet2"></i>
                <span>Bakiye Yükle</span>
            </a>
            
            <a class="nav-link <?php echo $currentPage == 'support' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('support'); ?>">
                <i class="bi bi-headset"></i>
                <span>Destek</span>
            </a>
            <a class="nav-link <?php echo $currentPage == 'settings' ? 'active' : ''; ?>" href="<?php echo Helper::url('settings'); ?>">
                <i class="bi bi-gear"></i> Ayarlar
            </a>
            <?php if ($auth->isAdmin()): ?>
            <a class="nav-link <?php echo $currentPage == 'admin' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('admin'); ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Admin Panel</span>
            </a>
            <?php endif; ?>
            <?php if (!$user['is_premium']): ?>
            <a class="nav-link premium-link <?php echo $currentPage == 'premium' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('premium'); ?>">
                <i class="bi bi-star-fill"></i>
                <span>Premium</span>
            </a>
            <?php else: ?>
            <a class="nav-link premium-link active" 
               href="<?php echo Helper::url('premium'); ?>">
                <i class="bi bi-star-fill"></i>
                <span>Premium</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-user">
            <div class="user-info-wrapper">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo $user['username']; ?></div>
                    <div class="user-balance">
                        <i class="bi bi-wallet"></i>
                        <?php echo Helper::money($user['balance']); ?>
                    </div>
                </div>
                <a href="<?php echo Helper::url('logout'); ?>" class="logout-btn" title="Çıkış Yap">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <nav class="top-navbar">
            <h1 class="navbar-title"><?php echo $title ?? 'Dashboard'; ?></h1>
            <?php if ($user['is_premium']): ?>
            <span class="premium-badge">
                <i class="bi bi-star-fill"></i>
                PREMIUM
            </span>
            <?php endif; ?>
        </nav>
