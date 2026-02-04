<?php
$title = 'Script İste';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $error = 'Güvenlik hatası';
    } else {
        try {
            $db->insert('script_requests', [
                'user_id' => $auth->userId(),
                'script_name' => $_POST['script_name'],
                'description' => $_POST['description'],
                'budget' => $_POST['budget'] ?: null,
                'contact_info' => $_POST['contact_info'] ?: null,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = true;
            
        } catch (Exception $e) {
            $error = 'İstek gönderilirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Önceki isteklerini göster
$myRequests = $db->fetchAll("
    SELECT * FROM script_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
", [$auth->userId()]);

require 'templates/header.php';
?>

<style>
/* Form Container Animation */
.form-container {
    animation: slideUp 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Custom Form Styling */
.script-request-form {
    position: relative;
    overflow: hidden;
}

.script-request-form::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, #4338ca, #6366f1, #8b5cf6, #6366f1, #4338ca);
    background-size: 400% 400%;
    border-radius: 18px;
    z-index: -1;
    animation: gradient-border 8s ease infinite;
    opacity: 0;
    transition: opacity 0.5s;
}

.script-request-form:hover::before {
    opacity: 0.4;
}

@keyframes gradient-border {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Form Group Enhancement */
.form-group {
    margin-bottom: 24px;
    animation: fadeInUp 0.6s ease-out backwards;
}

.form-group:nth-child(1) { animation-delay: 0.1s; }
.form-group:nth-child(2) { animation-delay: 0.2s; }
.form-group:nth-child(3) { animation-delay: 0.3s; }
.form-group:nth-child(4) { animation-delay: 0.4s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Label */
.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #ffffff;
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 10px;
}

.form-label i {
    color: #818cf8;
    font-size: 18px;
}

.form-label .required {
    color: var(--danger);
    font-size: 12px;
}

/* Input Enhancements */
.form-control,
.form-select {
    background: rgba(26, 26, 46, 0.6);
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 14px 18px;
    color: #ffffff !important;
    font-size: 15px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.form-control:focus,
.form-select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(26, 26, 46, 0.8);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), 0 8px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
    color: #ffffff !important;
}

.form-control::placeholder {
    color: rgba(161, 161, 170, 0.6);
}

/* Select option colors */
.form-select {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23818cf8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 16px center;
    background-size: 16px 12px;
    padding-right: 40px;
}

.form-select option {
    background: #1a1a2e;
    color: #ffffff;
    padding: 10px;
}

textarea.form-control {
    resize: vertical;
    min-height: 140px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1.6;
}

/* Submit Button */
.submit-btn {
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
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.submit-btn::before {
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

.submit-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.4);
}

.submit-btn:hover::before {
    width: 300px;
    height: 300px;
}

.submit-btn:active {
    transform: translateY(-1px);
}

/* Icon Animations */
.submit-btn i {
    transition: transform 0.3s;
}

.submit-btn:hover i {
    transform: translateX(5px);
}

/* Alert Enhancements */
.alert {
    border-radius: 16px;
    border: none;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: alertSlide 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    position: relative;
    overflow: hidden;
}

@keyframes alertSlide {
    from {
        opacity: 0;
        transform: translateX(-100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: currentColor;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
    color: var(--success);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
    color: var(--danger);
}

.alert i {
    font-size: 24px;
}

/* Request History Table */
.request-history {
    animation: fadeIn 0.8s ease-out 0.5s backwards;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

.status-badge:hover {
    transform: scale(1.05);
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.2);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.4);
}

.status-badge.reviewed {
    background: rgba(6, 182, 212, 0.2);
    color: var(--info);
    border: 1px solid rgba(6, 182, 212, 0.4);
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.status-badge.rejected {
    background: rgba(100, 100, 120, 0.2);
    color: var(--text-muted);
    border: 1px solid rgba(100, 100, 120, 0.4);
}

.status-badge i {
    animation: pulse-icon 2s ease-in-out infinite;
}

@keyframes pulse-icon {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Table Row Hover Effect */
.table tbody tr {
    transition: all 0.3s;
    cursor: pointer;
}

.table tbody tr:hover {
    background: rgba(99, 102, 241, 0.08);
    transform: translateX(5px);
}

/* Info Icons */
.info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    font-size: 12px;
    margin-left: 5px;
    cursor: help;
    transition: all 0.3s;
}

.info-icon:hover {
    background: #6366f1;
    color: white;
    transform: scale(1.2);
}

/* Budget Select Enhancement */
.form-select option {
    background: var(--bg-secondary);
    color: var(--text-primary);
    padding: 10px;
}

/* Character Counter */
.char-counter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    font-size: 13px;
    color: rgba(161, 161, 170, 0.8);
}

.char-count {
    color: #818cf8;
    font-weight: 600;
}

/* Help Text */
.form-help {
    font-size: 13px;
    color: rgba(161, 161, 170, 0.8);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-help i {
    color: #818cf8;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 80px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h5 {
    color: var(--text-secondary);
    margin-bottom: 10px;
}

/* Loading State */
.btn-loading {
    position: relative;
    pointer-events: none;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid transparent;
    border-top-color: white;
    border-radius: 50%;
    animation: spinner 0.8s linear infinite;
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .form-control,
    .form-select {
        padding: 12px 16px;
        font-size: 14px;
    }
    
    .submit-btn {
        padding: 14px;
        font-size: 15px;
    }
    
    .alert {
        padding: 16px 20px;
    }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <?php if ($success): ?>
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>İsteğiniz Alındı!</strong><br>
                <span style="font-size: 14px; opacity: 0.9;">En kısa sürede değerlendirilecek ve size dönüş yapılacak.</span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Hata!</strong><br>
                <span style="font-size: 14px;"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Ana Form -->
        <div class="admin-card mb-4 form-container script-request-form">
            <div class="admin-card-header">
                <h4 class="mb-0">
                    <i class="bi bi-code-square me-2"></i>
                    Yeni Script Talebi
                </h4>
            </div>
            <div class="admin-card-body">
                <p class="mb-4" style="color: rgba(161, 161, 170, 0.9); font-size: 14px;">
                    <i class="bi bi-info-circle me-2" style="color: #818cf8;"></i>
                    İhtiyacınız olan scripti detaylı bir şekilde açıklayın. Ekibimiz sizinle en kısa sürede iletişime geçecektir.
                </p>
                
                <form method="POST" id="scriptRequestForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="bi bi-tag-fill"></i>
                            Script Adı / Türü
                            <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="script_name" 
                            class="form-control" 
                            placeholder="örn: E-Ticaret Scripti, NFT Marketplace, DeFi Dashboard..."
                            required
                            maxlength="100"
                        >
                        <div class="form-help">
                            <i class="bi bi-lightbulb-fill"></i>
                            Script türünü veya ismini belirtin
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="bi bi-file-text-fill"></i>
                            Detaylı Açıklama
                            <span class="required">*</span>
                        </label>
                        <textarea 
                            name="description" 
                            class="form-control" 
                            placeholder="İstediğiniz scriptin özelliklerini, referans siteleri, özel istekleri detaylı bir şekilde yazın..."
                            required
                            maxlength="2000"
                            id="descriptionInput"
                        ></textarea>
                        <div class="char-counter">
                            <span class="form-help">
                                <i class="bi bi-card-text"></i>
                                Detaylı açıklama yapmanız, talebin hızlı değerlendirilmesini sağlar
                            </span>
                            <span class="char-count">
                                <span id="charCount">0</span> / 2000
                            </span>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-cash-stack"></i>
                                    Bütçe Aralığı
                                    <span class="info-icon" title="Tahmini bütçeniz bizim için yol gösterici olacaktır">
                                        <i class="bi bi-question"></i>
                                    </span>
                                </label>
                                <select name="budget" class="form-select">
                                    <option value="">Seçin (Opsiyonel)</option>
                                    <option value="0-500">💰 0 - 500 USDT</option>
                                    <option value="500-1000">💰💰 500 - 1,000 USDT</option>
                                    <option value="1000-5000">💰💰💰 1,000 - 5,000 USDT</option>
                                    <option value="5000+">💰💰💰💰 5,000+ USDT</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-telegram"></i>
                                    İletişim Bilgisi
                                </label>
                                <input 
                                    type="text" 
                                    name="contact_info" 
                                    class="form-control" 
                                    placeholder="Telegram: @kullanici veya email@ornek.com"
                                >
                                <div class="form-help">
                                    <i class="bi bi-shield-check"></i>
                                    Opsiyonel - Hızlı iletişim için
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="bi bi-send-fill"></i>
                        İsteği Gönder
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- İstek Geçmişi -->
        <?php if (!empty($myRequests)): ?>
        <div class="admin-card request-history">
            <div class="admin-card-header">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    İstek Geçmişi
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="bi bi-box-seam me-2"></i>Script</th>
                            <th><i class="bi bi-info-circle me-2"></i>Durum</th>
                            <th><i class="bi bi-calendar3 me-2"></i>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myRequests as $req): ?>
                        <tr onclick="showRequestDetails(<?php echo $req['id']; ?>)">
                            <td>
                                <strong><?php echo htmlspecialchars($req['script_name']); ?></strong>
                                <?php if ($req['budget']): ?>
                                <br><small class="text-muted">Bütçe: <?php echo htmlspecialchars($req['budget']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $req['status']; ?>">
                                    <?php 
                                    $icons = [
                                        'pending' => 'bi-hourglass-split',
                                        'reviewed' => 'bi-eye-fill',
                                        'completed' => 'bi-check-circle-fill',
                                        'rejected' => 'bi-x-circle-fill'
                                    ];
                                    $labels = [
                                        'pending' => 'Bekliyor',
                                        'reviewed' => 'İnceleniyor',
                                        'completed' => 'Tamamlandı',
                                        'rejected' => 'Reddedildi'
                                    ];
                                    echo '<i class="bi ' . $icons[$req['status']] . '"></i> ';
                                    echo $labels[$req['status']];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: var(--text-secondary);">
                                    <?php echo date('d.m.Y', strtotime($req['created_at'])); ?>
                                </span>
                                <br>
                                <small class="text-muted"><?php echo date('H:i', strtotime($req['created_at'])); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-card request-history">
            <div class="admin-card-body">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h5>Henüz İstek Yok</h5>
                    <p>İlk script talebinizi oluşturun ve ekibimiz sizinle iletişime geçsin!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Character counter
const descInput = document.getElementById('descriptionInput');
const charCount = document.getElementById('charCount');

if (descInput && charCount) {
    descInput.addEventListener('input', function() {
        charCount.textContent = this.value.length;
        
        // Color change based on length
        if (this.value.length > 1800) {
            charCount.style.color = 'var(--danger)';
        } else if (this.value.length > 1500) {
            charCount.style.color = 'var(--warning)';
        } else {
            charCount.style.color = 'var(--primary-light)';
        }
    });
}

// Form submission animation
const form = document.getElementById('scriptRequestForm');
if (form) {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('.submit-btn');
        btn.classList.add('btn-loading');
        btn.innerHTML = '<span style="opacity: 0;">Gönderiliyor...</span>';
    });
}

// Request details (placeholder for future feature)
function showRequestDetails(id) {
    console.log('Request ID:', id);
    // Bu özellik gelecekte modal ile detay gösterecek
}

// Auto-resize textarea
if (descInput) {
    descInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
}

// Form validation messages
const inputs = document.querySelectorAll('.form-control, .form-select');
inputs.forEach(input => {
    input.addEventListener('invalid', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--danger)';
        
        setTimeout(() => {
            this.style.borderColor = '';
        }, 2000);
    });
});

// Success animation
<?php if ($success): ?>
setTimeout(() => {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        alert.style.animation = 'alertSlide 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) reverse';
        setTimeout(() => {
            alert.style.display = 'none';
        }, 500);
    }
}, 5000);
<?php endif; ?>
</script>

<?php require 'templates/footer.php'; ?>