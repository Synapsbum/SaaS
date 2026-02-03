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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Theme CSS -->
    <link href="<?php echo Helper::asset('css/theme.css'); ?>" rel="stylesheet">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="bi bi-list"></i>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <!-- Logo Section -->
        <div class="sidebar-logo">
            <h4><?php echo SITE_NAME; ?></h4>
        </div>

        <!-- Navigation -->
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
            
            <?php if ($auth->isAdmin()): ?>
            <a class="nav-link <?php echo $currentPage == 'admin' ? 'active' : ''; ?>" 
               href="<?php echo Helper::url('admin'); ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Admin Panel</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- User Info Section -->
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

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation Bar -->
        <nav class="top-navbar">
            <h1 class="navbar-title"><?php echo $title ?? 'Dashboard'; ?></h1>
            <?php if ($user['is_premium']): ?>
            <span class="premium-badge">
                <i class="bi bi-star-fill"></i>
                PREMIUM
            </span>
            <?php endif; ?>
        </nav>

        <!-- Page Content -->
