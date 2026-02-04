<?php
require_once 'config.php';
require_once 'core/Database.php';
require_once 'core/Security.php';
require_once 'core/Auth.php';
require_once 'core/Helper.php';

$auth = new Auth();
$csrfToken = Security::generateToken();

// URL parse
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = trim(substr($uri, strlen($base)), '/');
$segments = explode('/', $path);
$page = $segments[0] ?: 'dashboard';
$action = $segments[1] ?? 'index';
$id = $segments[2] ?? null;

// Public sayfalar
$public = ['login', 'register', 'logout', 'payment/callback'];
if (!in_array($page, $public) && !$auth->check()) {
    Helper::redirect('login');
    exit;
}

// Admin kontrol
if ($page === 'admin' && !$auth->isAdmin()) {
    Helper::redirect('dashboard');
    exit;
}

// Route
switch ($page) {
    case 'login':
        require 'modules/auth/login.php';
        break;
    case 'register':
        require 'modules/auth/register.php';
        break;
    case 'logout':
        $auth->logout();
        Helper::redirect('login');
        break;
    case 'dashboard':
        require 'modules/dashboard/index.php';
        break;
    case 'scripts':
        if ($action === 'buy' && $id) {
            require 'modules/scripts/buy.php';
        } else {
            require 'modules/scripts/index.php';
        }
        break;
    case 'rental':
        if ($action === 'setup' && $id) {
            require 'modules/rental/setup.php';
        } else {
            require 'modules/rental/index.php';
        }
        break;
    case 'payment':
        require 'modules/payment/index.php';
        break;
    case 'support':
        if ($action === 'new') {
            require 'modules/support/new.php';
        } elseif ($action === 'view' && $id) {
            require 'modules/support/view.php';
        } else {
            require 'modules/support/index.php';
        }
        break;
    case 'settings':
        require 'modules/dashboard/settings.php';
        break;
    case 'admin':
        if ($action) {
            $adminFile = 'modules/admin/' . $action . '.php';
            if (file_exists($adminFile)) {
                require $adminFile;
            } else {
                require 'modules/admin/index.php';
            }
        } else {
            require 'modules/admin/index.php';
        }
        break;
    case 'premium':
        require 'modules/dashboard/premium.php';
        break;
    default:
        Helper::redirect('dashboard');
}
?>