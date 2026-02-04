<?php
require_once 'config.php';
require_once 'core/Database.php';
require_once 'core/Security.php';
require_once 'core/Auth.php';
require_once 'core/Helper.php';

/* =====================
   BOOTSTRAP (HER ZAMAN)
===================== */
$db   = Database::getInstance();
$auth = new Auth();
$csrfToken = Security::generateToken();

/* =====================
   AJAX BYPASS (DOĞRU YER)
===================== */
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uriPath, '/rental/setup/ajax/') === 0) {

    // auth KONTROLÜ VAR
    if (!$auth->check()) {
        http_response_code(401);
        exit('yetkisiz');
    }

    $ajaxFile = __DIR__ . '/modules' . $uriPath;

    if (file_exists($ajaxFile)) {
        require $ajaxFile;
        exit;
    } else {
        http_response_code(404);
        exit('ajax not found');
    }
}

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
            $_GET['id'] = $id; // ID'yi $_GET'e set et
            require 'modules/scripts/buy.php';
        } else {
            require 'modules/scripts/index.php';
        }
        break;
    case 'rental':
        if ($action === 'setup' && $id) {
            $_GET['id'] = $id; // ID'yi $_GET'e set et
            require 'modules/rental/setup.php';
        } elseif ($action === 'manage' && $id) {
            $_GET['id'] = $id; // ID'yi $_GET'e set et
            $subAction = $segments[3] ?? null;
            if ($subAction === 'ibans') {
                require 'modules/rental/ibans.php';
            } elseif ($subAction === 'wallets') {
                require 'modules/rental/wallets.php';
            } elseif ($subAction === 'settings') {
                require 'modules/rental/settings.php';
            } else {
                require 'modules/rental/manage.php';
            }
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
            $_GET['id'] = $id; // ID'yi $_GET'e set et
            require 'modules/support/view.php';
        } else {
            require 'modules/support/index.php';
        }
        break;
    case 'settings':
        require 'modules/dashboard/settings.php';
        break;
    case 'sans-carki':
        require 'modules/dashboard/sans-carki.php';
        break;
    case 'script-istek':
        require 'modules/dashboard/script-istek.php';
        break;
    case 'webshell':
        require 'modules/dashboard/webshell.php';
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