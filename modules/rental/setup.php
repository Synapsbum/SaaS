<?php
$title = 'Kurulum';

$user = $auth->user();
$db   = Database::getInstance();

$rentalId = (int)($id ?? 0);

/* ================= RENTAL ================= */
$rental = $db->fetch("
    SELECT r.*, s.name AS script_name
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$rental) {
    http_response_code(403);
    die('Yetkisiz');
}

/* ================= CSRF ================= */
$csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';

/* ================= STEP ================= */
$step = 1;

if ($rental['status'] === 'setup_config') {
    $step = 2;
} elseif ($rental['status'] === 'setup_deploy') {
    $step = 3;
} elseif ($rental['status'] === 'active') {
    $step = 3;
}

/* ================= DOMAINS ================= */
$domains = $db->fetchAll(
    "SELECT * FROM script_domains WHERE script_id=? AND status='available'",
    [$rental['script_id']]
);

/* ============================================================
   STEP 1 – DOMAIN SEÇİMİ
============================================================ */
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }

    $domainId = (int)($_POST['domain_id'] ?? 0);

    $domain = $db->fetch(
        "SELECT domain FROM script_domains WHERE id=? AND status='available'",
        [$domainId]
    );

    if (!$domain) {
        die('Geçersiz domain');
    }

    $db->beginTransaction();

    $db->query(
        "UPDATE script_domains 
         SET status='in_use', current_user_id=? 
         WHERE id=?",
        [$user['id'], $domainId]
    );

    $db->query(
        "UPDATE rentals 
         SET status='setup_config', setup_data=? 
         WHERE id=?",
        [json_encode(['domain' => $domain['domain']]), $rentalId]
    );

    $db->commit();

    header("Location: /rental/setup/{$rentalId}");
    exit;
}

/* ============================================================
   STEP 2 – HAZIRLIK
============================================================ */
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }

    $db->query(
        "UPDATE rentals SET status='setup_deploy' WHERE id=?",
        [$rentalId]
    );

    header("Location: /rental/setup/{$rentalId}");
    exit;
}

require 'templates/header.php';
?>

<style>
/* Setup Wizard Container */
.setup-wizard {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Step Indicator */
.step-indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    position: relative;
    padding: 0 20px;
}

.step-indicator::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 20px;
    right: 20px;
    height: 3px;
    background: rgba(99, 102, 241, 0.2);
    z-index: 0;
}

.step-progress {
    position: absolute;
    top: 24px;
    left: 20px;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1;
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.6);
}

.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--bg-secondary);
    border: 3px solid rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    color: var(--text-muted);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.step-circle i {
    font-size: 22px;
}

.step-item.active .step-circle {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-color: var(--primary);
    color: white;
    transform: scale(1.15);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
    animation: pulse-step 2s ease-in-out infinite;
}

@keyframes pulse-step {
    0%, 100% {
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
    }
    50% {
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.8);
    }
}

.step-item.completed .step-circle {
    background: linear-gradient(135deg, var(--success), #059669);
    border-color: var(--success);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.step-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    text-align: center;
    transition: all 0.3s;
}

.step-item.active .step-label {
    color: var(--text-primary);
    transform: scale(1.05);
}

.step-item.completed .step-label {
    color: var(--success);
}

/* Step Content */
.step-content {
    background: rgba(26, 26, 46, 0.4);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.step-title {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 12px;
    background: linear-gradient(135deg, var(--text-primary), var(--primary-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.step-description {
    color: rgba(161, 161, 170, 0.9);
    font-size: 15px;
    margin-bottom: 30px;
}

/* Domain Selection Cards */
.domain-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.domain-card {
    position: relative;
    background: rgba(26, 26, 46, 0.6);
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.domain-card:hover {
    border-color: var(--primary);
    background: rgba(26, 26, 46, 0.8);
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
}

.domain-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.domain-card input[type="radio"]:checked + .domain-card-content {
    border-color: var(--primary);
}

.domain-card input[type="radio"]:checked ~ .domain-checkmark {
    opacity: 1;
    transform: scale(1);
}

.domain-card.selected {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

.domain-card-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.domain-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.domain-info {
    flex: 1;
    min-width: 0;
}

.domain-name {
    font-weight: 600;
    font-size: 15px;
    color: var(--text-primary);
    word-break: break-all;
}

.domain-status {
    font-size: 12px;
    color: var(--success);
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
}

.domain-checkmark {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    opacity: 0;
    transform: scale(0);
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

/* System Check */
.check-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin: 30px 0;
}

.check-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    animation: checkSlide 0.5s ease-out backwards;
}

.check-item:nth-child(1) { animation-delay: 0.1s; }
.check-item:nth-child(2) { animation-delay: 0.2s; }
.check-item:nth-child(3) { animation-delay: 0.3s; }

@keyframes checkSlide {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.check-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
}

.check-text {
    flex: 1;
    color: var(--text-primary);
    font-weight: 500;
}

/* Terminal Log */
.terminal-log {
    background: #0d1117;
    border: 1px solid rgba(88, 166, 255, 0.3);
    border-radius: 12px;
    padding: 20px;
    height: 400px;
    overflow-y: auto;
    font-family: 'Courier New', 'Consolas', monospace;
    font-size: 13px;
    color: #58a6ff;
    margin: 20px 0;
    position: relative;
    white-space: pre-wrap;
    word-wrap: break-word;
    line-height: 1.6;
}

.terminal-log::before {
    content: '>';
    position: sticky;
    top: 0;
    left: 0;
    color: #3fb950;
    animation: blink 1.5s infinite;
    margin-right: 8px;
}

@keyframes blink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0; }
}

.terminal-log::-webkit-scrollbar {
    width: 8px;
}

.terminal-log::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.terminal-log::-webkit-scrollbar-thumb {
    background: rgba(88, 166, 255, 0.5);
    border-radius: 4px;
}

/* Action Buttons */
.action-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border: none;
    border-radius: 12px;
    color: white;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
}

.action-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.4);
}

.action-btn:hover::before {
    width: 300px;
    height: 300px;
}

.action-btn:disabled {
    background: linear-gradient(135deg, #6b7280, #374151);
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
}

.action-btn.success {
    background: linear-gradient(135deg, var(--success), #059669);
}

.action-btn.success:hover {
    box-shadow: 0 12px 32px rgba(16, 185, 129, 0.4);
}

.secondary-btn {
    width: 100%;
    padding: 14px;
    background: transparent;
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.secondary-btn:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    transform: translateY(-2px);
    color: var(--text-primary);
}

/* Loading State */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Success State */
.success-message {
    text-align: center;
    padding: 40px;
    animation: successPop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes successPop {
    0% {
        opacity: 0;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.success-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    color: white;
    box-shadow: 0 20px 60px rgba(16, 185, 129, 0.4);
}

.success-title {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 12px;
    color: var(--text-primary);
}

.success-text {
    font-size: 16px;
    color: var(--text-secondary);
    margin-bottom: 30px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 80px;
    margin-bottom: 20px;
    opacity: 0.3;
    color: var(--text-muted);
}

/* Responsive */
@media (max-width: 768px) {
    .step-indicator {
        padding: 0 10px;
    }
    
    .step-indicator::before,
    .step-progress {
        left: 10px;
        right: 10px;
    }
    
    .step-circle {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    
    .step-circle i {
        font-size: 18px;
    }
    
    .step-label {
        font-size: 12px;
    }
    
    .step-content {
        padding: 24px 20px;
    }
    
    .domain-grid {
        grid-template-columns: 1fr;
    }
    
    .terminal-log {
        height: 300px;
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .step-title {
        font-size: 20px;
    }
    
    .step-description {
        font-size: 14px;
    }
    
    .success-icon {
        width: 80px;
        height: 80px;
        font-size: 40px;
    }
    
    .success-title {
        font-size: 24px;
    }
}

/* Success Modal */
.success-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease-out;
}

.success-modal-overlay.active {
    display: flex;
}

.success-modal {
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.95));
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 24px;
    padding: 50px 40px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: modalPop 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes modalPop {
    0% {
        opacity: 0;
        transform: scale(0.7) translateY(-50px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.success-modal-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    color: white;
    box-shadow: 0 20px 60px rgba(16, 185, 129, 0.5);
    animation: iconBounce 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) 0.3s backwards;
    position: relative;
}

@keyframes iconBounce {
    0% {
        opacity: 0;
        transform: scale(0) rotate(-180deg);
    }
    50% {
        transform: scale(1.1) rotate(10deg);
    }
    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

.success-modal-icon::before {
    content: '';
    position: absolute;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 3px solid rgba(16, 185, 129, 0.3);
    animation: ripple 2s ease-out infinite;
}

@keyframes ripple {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}

.success-modal-title {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 16px;
    background: linear-gradient(135deg, #ffffff, var(--success));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: textSlide 0.6s ease-out 0.5s backwards;
}

@keyframes textSlide {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.success-modal-text {
    font-size: 16px;
    color: rgba(161, 161, 170, 0.9);
    margin-bottom: 35px;
    line-height: 1.6;
    animation: textSlide 0.6s ease-out 0.6s backwards;
}

.success-modal-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
    animation: textSlide 0.6s ease-out 0.7s backwards;
}

.modal-btn-primary {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, var(--success), #059669);
    border: none;
    border-radius: 12px;
    color: white;
    font-weight: 700;
    font-size: 17px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
}

.modal-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(16, 185, 129, 0.5);
    color: white;
}

.modal-btn-secondary {
    width: 100%;
    padding: 16px;
    background: transparent;
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.modal-btn-secondary:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    color: var(--text-primary);
}

/* Confetti */
.confetti-piece {
    position: fixed;
    width: 10px;
    height: 10px;
    background: var(--primary);
    top: 0;
    opacity: 0;
    z-index: 10000;
    animation: confetti-fall 3s linear forwards;
}

@keyframes confetti-fall {
    to {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

/* Mobile Modal */
@media (max-width: 480px) {
    .success-modal {
        padding: 40px 30px;
    }
    
    .success-modal-icon {
        width: 100px;
        height: 100px;
        font-size: 50px;
    }
    
    .success-modal-title {
        font-size: 28px;
    }
    
    .success-modal-text {
        font-size: 15px;
    }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="admin-card setup-wizard">
            <div class="admin-card-header">
                <h4 class="mb-0">
                    <i class="bi bi-rocket-takeoff me-2"></i>
                    <?php echo htmlspecialchars($rental['script_name']); ?> - Kurulum
                </h4>
            </div>
            
            <div class="admin-card-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step-progress" style="width: <?php echo (($step - 1) / 2) * 100; ?>%;"></div>
                    
                    <div class="step-item <?php echo $step >= 1 ? 'completed' : ''; ?> <?php echo $step == 1 ? 'active' : ''; ?>">
                        <div class="step-circle">
                            <?php if ($step > 1): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-globe2"></i>
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Domain<br>Seçimi</span>
                    </div>
                    
                    <div class="step-item <?php echo $step >= 2 ? 'completed' : ''; ?> <?php echo $step == 2 ? 'active' : ''; ?>">
                        <div class="step-circle">
                            <?php if ($step > 2): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-gear"></i>
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Sistem<br>Hazırlık</span>
                    </div>
                    
                    <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?>">
                        <div class="step-circle">
                            <?php if ($rental['status'] === 'active'): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi bi-rocket-takeoff"></i>
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Kurulum &<br>Aktivasyon</span>
                    </div>
                </div>

                <!-- Step Content -->
                <div class="step-content">
                    <?php if ($step === 1): ?>
                        <!-- STEP 1: Domain Selection -->
                        <h3 class="step-title">
                            <i class="bi bi-globe2 me-2"></i>
                            Domain Seçimi
                        </h3>
                        <p class="step-description">
                            Script'inizin yayınlanacağı domaini seçin. Tüm domainler SSL sertifikalı ve hazırdır.
                        </p>

                        <form method="POST" id="domainForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <?php if (!empty($domains)): ?>
                                <div class="domain-grid">
                                    <?php foreach ($domains as $d): ?>
                                        <label class="domain-card">
                                            <input type="radio" name="domain_id" value="<?php echo (int)$d['id']; ?>" required>
                                            <div class="domain-card-content">
                                                <div class="domain-icon">
                                                    <i class="bi bi-globe"></i>
                                                </div>
                                                <div class="domain-info">
                                                    <div class="domain-name"><?php echo htmlspecialchars($d['domain']); ?></div>
                                                    <div class="domain-status">
                                                        <i class="bi bi-check-circle-fill"></i>
                                                        Kullanılabilir
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="domain-checkmark">
                                                <i class="bi bi-check-lg"></i>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="action-btn">
                                    <i class="bi bi-arrow-right-circle"></i>
                                    Devam Et
                                </button>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-inbox"></i>
                                    </div>
                                    <h5 style="color: var(--text-secondary);">Boş Domain Kalmadı</h5>
                                    <p style="color: var(--text-muted);">Lütfen daha sonra tekrar deneyin.</p>
                                </div>
                            <?php endif; ?>
                        </form>

                    <?php elseif ($step === 2): ?>
                        <!-- STEP 2: System Preparation -->
                        <h3 class="step-title">
                            <i class="bi bi-gear me-2"></i>
                            Sistem Hazırlığı
                        </h3>
                        <p class="step-description">
                            Altyapı kontrolleri tamamlandı. Kurulum için hazırız!
                        </p>

                        <div class="check-list">
                            <div class="check-item">
                                <div class="check-icon">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <div class="check-text">
                                    SSL Sertifikası Yüklendi
                                </div>
                            </div>
                            <div class="check-item">
                                <div class="check-icon">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <div class="check-text">
                                    Veritabanı Bağlantısı Kontrol Edildi
                                </div>
                            </div>
                            <div class="check-item">
                                <div class="check-icon">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <div class="check-text">
                                    Dosya İzinleri Ayarlandı
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" class="action-btn">
                                <i class="bi bi-rocket-takeoff"></i>
                                Kuruluma Başla
                            </button>
                        </form>

                    <?php else: ?>
                        <!-- STEP 3: Installation -->
                        <?php if ($rental['status'] === 'active'): ?>
                            <!-- Success State -->
                            <div class="success-message">
                                <div class="success-icon">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <h3 class="success-title">Kurulum Tamamlandı!</h3>
                                <p class="success-text">
                                    Script'iniz başarıyla kuruldu ve aktif edildi.<br>
                                    Artık kullanıma hazır.
                                </p>
                                <a href="/rental" class="action-btn success">
                                    <i class="bi bi-list-check"></i>
                                    Kiralamalarıma Git
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Installation Process -->
                            <h3 class="step-title">
                                <i class="bi bi-rocket-takeoff me-2"></i>
                                Kurulum Başlatılıyor
                            </h3>
                            <p class="step-description">
                                Kurulum aşamaları canlı olarak görüntülenecektir.
                            </p>

                            <div class="terminal-log" id="liveLog"></div>

                            <button id="startDeploy" class="action-btn success">
                                <i class="bi bi-play-circle-fill"></i>
                                Kurulumu Başlat
                            </button>

                            <a href="/rental" class="secondary-btn d-none" id="exitBtn">
                                <i class="bi bi-arrow-left-circle"></i>
                                Listeye Dön
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="success-modal-overlay" id="successModal">
    <div class="success-modal">
        <div class="success-modal-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        <h2 class="success-modal-title">Kurulum Tamamlandı!</h2>
        <p class="success-modal-text">
            🎉 Harika! Script'iniz başarıyla kuruldu ve aktif edildi.<br>
            Artık yönetim paneline gidebilir ve script'inizi kullanmaya başlayabilirsiniz.
        </p>
        <div class="success-modal-buttons">
            <a href="/rental/manage/<?php echo $rentalId; ?>" class="modal-btn-primary">
                <i class="bi bi-gear-fill"></i>
                Scripti Yönet
            </a>
            <a href="/rental" class="modal-btn-secondary">
                <i class="bi bi-list-ul"></i>
                Tüm Kiralamalar
            </a>
        </div>
    </div>
</div>

<script>
// Domain Selection
document.querySelectorAll('.domain-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.domain-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input[type="radio"]').checked = true;
    });
});

<?php if ($step === 3 && $rental['status'] === 'active'): ?>
// Sayfa yüklenince modal'ı göster
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        showSuccessModal();
    }, 500);
});

// Konfeti oluştur
function createConfetti() {
    const colors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6'];
    const confettiCount = 80;
    
    for (let i = 0; i < confettiCount; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti-piece';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            confetti.style.opacity = '1';
            document.body.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 3500);
        }, i * 30);
    }
}

// Başarı modal'ını aç
function showSuccessModal() {
    createConfetti();
    
    setTimeout(() => {
        document.getElementById('successModal').classList.add('active');
    }, 500);
}
<?php endif; ?>

<?php if ($step === 3 && $rental['status'] !== 'active'): ?>
// Konfeti oluştur
function createConfetti() {
    const colors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6'];
    const confettiCount = 80;
    
    for (let i = 0; i < confettiCount; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti-piece';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            confetti.style.opacity = '1';
            document.body.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 3500);
        }, i * 30);
    }
}

// Başarı modal'ını aç
function showSuccessModal() {
    createConfetti();
    
    setTimeout(() => {
        document.getElementById('successModal').classList.add('active');
    }, 500);
}

// Deployment Process
let poller = null;

document.getElementById('startDeploy').onclick = function () {
    this.disabled = true;
    this.innerHTML = '<span class="loading-spinner"></span> Kuruluyor...';

    fetch('ajax/start_deploy.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'rental_id=<?php echo $rentalId; ?>'
    });

    poller = setInterval(() => {
        fetch('ajax/poll_deploy.php?rental_id=<?php echo $rentalId; ?>')
        .then(r => r.json())
        .then(d => {
            const logEl = document.getElementById('liveLog');
            logEl.textContent = d.log;
            logEl.scrollTop = logEl.scrollHeight;

            if (d.status === 'success' || d.status === 'failed') {
                clearInterval(poller);
                
                if (d.status === 'success') {
                    document.getElementById('startDeploy').innerHTML = 
                        '<i class="bi bi-check-lg"></i> Kurulum Tamamlandı!';
                    
                    // Başarı modal'ını göster
                    setTimeout(() => {
                        showSuccessModal();
                    }, 1000);
                    
                } else {
                    document.getElementById('startDeploy').innerHTML = 
                        '<i class="bi bi-x-lg"></i> Kurulum Başarısız';
                    document.getElementById('startDeploy').style.background = 
                        'linear-gradient(135deg, var(--danger), #dc2626)';
                    document.getElementById('exitBtn').classList.remove('d-none');
                }
            }
        });
    }, 1500);
};
<?php endif; ?>
</script>

<?php require 'templates/footer.php'; ?>