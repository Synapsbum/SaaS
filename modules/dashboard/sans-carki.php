<?php
$title = 'Şans Çarkı';
$db = Database::getInstance();

// Ayarları kontrol et
$wheelEnabled = $db->fetch("SELECT setting_value FROM admin_settings WHERE setting_key = 'wheel_enabled'")['setting_value'] ?? '1';
$dailyLimit = intval($db->fetch("SELECT setting_value FROM admin_settings WHERE setting_key = 'wheel_daily_limit'")['setting_value'] ?? '1');

if ($wheelEnabled != '1') {
    die("Şans çarkı şu anda aktif değil.");
}

// Bugün çevirme sayısını kontrol et
$todaySpins = $db->fetch("
    SELECT COUNT(*) as total 
    FROM wheel_spins 
    WHERE user_id = ? AND DATE(spun_at) = CURDATE()
", [$auth->userId()])['total'];

$canSpin = $todaySpins < $dailyLimit;
$spinsLeft = $dailyLimit - $todaySpins;

// Ödülleri çek
$rewards = $db->fetchAll("SELECT * FROM wheel_rewards WHERE is_active = 1 ORDER BY id ASC");

// Çevirme işlemi (AJAX)
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'spin') {
    header('Content-Type: application/json');
    
    if (!$canSpin) {
        echo json_encode(['error' => 'Bugünlük çevirme hakkınız bitti!']);
        exit;
    }
    
    // Rastgele ödül seç (olasılığa göre)
    $random = mt_rand(1, 100);
    $cumulative = 0;
    $selectedReward = null;
    $selectedIndex = 0;
    
    foreach ($rewards as $index => $reward) {
        $cumulative += $reward['probability'];
        if ($random <= $cumulative) {
            $selectedReward = $reward;
            $selectedIndex = $index;
            break;
        }
    }
    
    if ($selectedReward) {
        // Kazanan açıyı hesapla (segmentin ortasına denk gelecek şekilde)
        $segmentAngle = 360 / count($rewards);
        $winningAngle = ($selectedIndex * $segmentAngle) + ($segmentAngle / 2);
        
        // 5-10 tur döndür + kazanan açıya git
        $totalSpins = 360 * (5 + mt_rand(0, 5));
        $finalAngle = $totalSpins + (360 - $winningAngle);
        
        // Kaydet
        $db->insert('wheel_spins', [
            'user_id' => $auth->userId(),
            'reward_id' => $selectedReward['id'],
            'amount_won' => $selectedReward['amount'],
            'spun_at' => date('Y-m-d H:i:s')
        ]);
        
        // Bakiye ekle (eğer USDT ise)
        if ($selectedReward['reward_type'] == 'usdt' && $selectedReward['amount'] > 0) {
            $db->query("UPDATE users SET balance = balance + ? WHERE id = ?", [
                $selectedReward['amount'],
                $auth->userId()
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'reward' => $selectedReward,
            'angle' => $finalAngle,
            'spins_left' => max(0, $dailyLimit - $todaySpins - 1)
        ]);
    } else {
        echo json_encode(['error' => 'Ödül seçilemedi']);
    }
    exit;
}

// Son kazananları çek
$recentWinners = $db->fetchAll("
    SELECT w.*, u.username 
    FROM wheel_spins w
    JOIN users u ON w.user_id = u.id
    WHERE w.amount_won > 0
    ORDER BY w.spun_at DESC
    LIMIT 10
");

require 'templates/header.php';
?>

<style>
/* Ana Çark Container */
.wheel-section {
    position: relative;
    padding: 40px 0;
    overflow: hidden;
}

.wheel-wrapper {
    position: relative;
    width: 450px;
    height: 450px;
    margin: 0 auto;
    filter: drop-shadow(0 20px 40px rgba(99, 102, 241, 0.3));
}

/* Çark Container */
.wheel-container {
    width: 100%;
    height: 100%;
    position: relative;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 
        0 0 0 8px rgba(99, 102, 241, 0.2),
        0 0 0 16px rgba(99, 102, 241, 0.1),
        inset 0 0 60px rgba(0, 0, 0, 0.5);
    animation: wheel-glow 3s ease-in-out infinite;
}

@keyframes wheel-glow {
    0%, 100% { box-shadow: 0 0 0 8px rgba(99, 102, 241, 0.2), 0 0 0 16px rgba(99, 102, 241, 0.1), inset 0 0 60px rgba(0, 0, 0, 0.5), 0 0 80px rgba(99, 102, 241, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(236, 72, 153, 0.3), 0 0 0 16px rgba(236, 72, 153, 0.15), inset 0 0 60px rgba(0, 0, 0, 0.5), 0 0 100px rgba(236, 72, 153, 0.5); }
}

#wheel {
    width: 100%;
    height: 100%;
    transition: transform 6s cubic-bezier(0.15, 0.65, 0.15, 0.99);
    transform-origin: center;
}

/* Dış Çerçeve Dekorasyonu */
.wheel-decoration {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 480px;
    height: 480px;
    border-radius: 50%;
    border: 3px solid transparent;
    background: linear-gradient(45deg, var(--primary), var(--accent), var(--primary)) border-box;
    -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    animation: spin-decoration 20s linear infinite;
}

@keyframes spin-decoration {
    from { transform: translate(-50%, -50%) rotate(0deg); }
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

/* İşaretçi (Pointer) */
.wheel-pointer {
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 30px solid transparent;
    border-right: 30px solid transparent;
    border-top: 60px solid #ec4899;
    z-index: 10;
    filter: drop-shadow(0 4px 15px rgba(236, 72, 153, 0.8));
    animation: pointer-bounce 2s ease-in-out infinite;
}

.wheel-pointer::after {
    content: '';
    position: absolute;
    top: -50px;
    left: -15px;
    width: 30px;
    height: 30px;
    background: radial-gradient(circle, #fff, #ec4899);
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(236, 72, 153, 1);
}

@keyframes pointer-bounce {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50% { transform: translateX(-50%) translateY(8px); }
}

/* Çevirme Butonu */
.spin-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #ec4899 100%);
    border: 6px solid #fff;
    color: white;
    font-weight: 800;
    font-size: 18px;
    cursor: pointer;
    z-index: 20;
    box-shadow: 
        0 8px 32px rgba(99, 102, 241, 0.5),
        inset 0 -4px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.spin-btn:hover:not(:disabled) {
    transform: translate(-50%, -50%) scale(1.1);
    box-shadow: 
        0 12px 48px rgba(99, 102, 241, 0.7),
        inset 0 -4px 8px rgba(0, 0, 0, 0.2);
}

.spin-btn:active:not(:disabled) {
    transform: translate(-50%, -50%) scale(0.95);
}

.spin-btn:disabled {
    background: linear-gradient(135deg, #6b7280, #374151);
    cursor: not-allowed;
    opacity: 0.6;
}

.spin-btn.spinning {
    animation: btn-pulse 0.8s ease-in-out infinite;
    pointer-events: none;
}

@keyframes btn-pulse {
    0%, 100% { 
        transform: translate(-50%, -50%) scale(1);
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.5);
    }
    50% { 
        transform: translate(-50%, -50%) scale(1.08);
        box-shadow: 0 12px 48px rgba(236, 72, 153, 0.8);
    }
}

/* Sonuç Kutusu */
.result-box {
    display: none;
    margin-top: 30px;
    padding: 30px;
    border-radius: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.result-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

.result-box h3 {
    font-size: 32px;
    margin-bottom: 15px;
    font-weight: 800;
}

.result-box.win {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(6, 182, 212, 0.2));
    border: 2px solid var(--success);
    animation: result-appear 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.result-box.lose {
    background: linear-gradient(135deg, rgba(100, 100, 120, 0.2), rgba(80, 80, 100, 0.2));
    border: 2px solid var(--text-muted);
    animation: result-appear 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes result-appear {
    0% {
        opacity: 0;
        transform: scale(0.8) translateY(-30px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Konfeti Container */
#confetti-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
}

.confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #f0f;
    animation: confetti-fall 3s linear forwards;
}

@keyframes confetti-fall {
    to {
        transform: translateY(100vh) rotate(360deg);
        opacity: 0;
    }
}

/* Kazananlar Tablosu */
.winners-table {
    animation: fadeInUp 0.6s ease-out 0.3s backwards;
}

/* Winners List */
.winners-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.winner-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: slideInRight 0.5s ease-out backwards;
}

.winner-item:nth-child(1) { animation-delay: 0.1s; }
.winner-item:nth-child(2) { animation-delay: 0.15s; }
.winner-item:nth-child(3) { animation-delay: 0.2s; }
.winner-item:nth-child(4) { animation-delay: 0.25s; }
.winner-item:nth-child(5) { animation-delay: 0.3s; }

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.winner-item:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}

.winner-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.winner-info {
    flex: 1;
    min-width: 0;
}

.winner-name {
    font-weight: 600;
    font-size: 15px;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.winner-time {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-muted);
}

.winner-time i {
    font-size: 12px;
}

.winner-amount {
    font-weight: 700;
    font-size: 16px;
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
    padding: 8px 16px;
    border-radius: 20px;
    white-space: nowrap;
    border: 1px solid rgba(16, 185, 129, 0.2);
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

/* Parçacık Efekti Container */
.particle-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    overflow: hidden;
}

.particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: radial-gradient(circle, rgba(255,255,255,0.8), transparent);
    border-radius: 50%;
    pointer-events: none;
}

/* Bilgilendirme Kartı */
.info-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    animation: fadeIn 0.5s ease-out;
}

.info-card i {
    font-size: 24px;
    color: var(--primary-light);
}

/* Responsive */
@media (max-width: 768px) {
    .wheel-wrapper {
        width: 350px;
        height: 350px;
    }
    
    .wheel-decoration {
        width: 380px;
        height: 380px;
    }
    
    .spin-btn {
        width: 90px;
        height: 90px;
        font-size: 16px;
    }
    
    .winner-item {
        padding: 12px;
        gap: 12px;
    }
    
    .winner-avatar {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
    
    .winner-amount {
        font-size: 14px;
        padding: 6px 12px;
    }
}

@media (max-width: 480px) {
    .wheel-wrapper {
        width: 280px;
        height: 280px;
    }
    
    .wheel-decoration {
        width: 310px;
        height: 310px;
    }
    
    .spin-btn {
        width: 75px;
        height: 75px;
        font-size: 14px;
    }
    
    .wheel-pointer {
        border-left-width: 20px;
        border-right-width: 20px;
        border-top-width: 40px;
        top: -20px;
    }
    
    .winner-item {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .winner-amount {
        width: 100%;
        text-align: center;
        margin-top: 8px;
    }
}
</style>

<div id="confetti-container"></div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Bilgi Kartı -->
        <div class="info-card text-center">
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong>Günlük <?php echo $dailyLimit; ?> Çevirme Hakkı</strong>
            <span class="ms-3">•</span>
            <span class="ms-3">Kalan: <strong id="spinsLeft" class="text-primary"><?php echo $spinsLeft; ?></strong></span>
        </div>
        
        <!-- Ana Çark Bölümü -->
        <div class="admin-card mb-4 wheel-section">
            <div class="admin-card-header justify-content-center">
                <h2 class="mb-0">🎰 Şans Çarkı</h2>
            </div>
            <div class="admin-card-body">
                <div class="wheel-wrapper">
                    <div class="wheel-decoration"></div>
                    <div class="wheel-pointer"></div>
                    <div class="particle-container" id="particleContainer"></div>
                    <div class="wheel-container">
                        <canvas id="wheel" width="450" height="450"></canvas>
                    </div>
                    <button class="spin-btn" id="spinBtn" <?php echo !$canSpin ? 'disabled' : ''; ?>>
                        <?php echo $canSpin ? 'ÇEVİR!' : 'YARIN'; ?>
                    </button>
                </div>
                
                <div id="resultBox" class="result-box"></div>
            </div>
        </div>
        
        <!-- Son Kazananlar -->
        <div class="admin-card winners-table">
            <div class="admin-card-header" style="margin-bottom: 30px;">
                <h5 class="mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Son Kazananlar</h5>
            </div>
            <div class="admin-card-body">
                <?php if (empty($recentWinners)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-emoji-smile display-4 d-block mb-3 text-muted" style="opacity: 0.3;"></i>
                    <h5 class="text-muted mb-2">Henüz Kazanan Yok</h5>
                    <p class="text-muted mb-0">İlk kazanan sen ol!</p>
                </div>
                <?php else: ?>
                <div class="winners-list">
                    <?php foreach ($recentWinners as $w): ?>
                    <div class="winner-item">
                        <div class="winner-avatar">
                            <?php echo strtoupper(substr($w['username'], 0, 2)); ?>
                        </div>
                        <div class="winner-info">
                            <div class="winner-name"><?php echo htmlspecialchars($w['username']); ?></div>
                            <div class="winner-time">
                                <i class="bi bi-clock"></i>
                                <?php echo date('H:i', strtotime($w['spun_at'])); ?>
                            </div>
                        </div>
                        <div class="winner-amount">
                            +<?php 
                                $amount = $w['amount_won'];
                                echo (floor($amount) == $amount) ? number_format($amount, 0) : number_format($amount, 2);
                            ?> USDT
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== Ses Efektleri ====================
const AudioContext = window.AudioContext || window.webkitAudioContext;
const audioCtx = new AudioContext();

function playSpinSound() {
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);
    
    oscillator.frequency.setValueAtTime(400, audioCtx.currentTime);
    oscillator.frequency.exponentialRampToValueAtTime(200, audioCtx.currentTime + 0.1);
    
    gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);
    
    oscillator.start(audioCtx.currentTime);
    oscillator.stop(audioCtx.currentTime + 0.1);
}

function playWinSound() {
    const notes = [523.25, 659.25, 783.99, 1046.50];
    notes.forEach((freq, index) => {
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        oscillator.frequency.setValueAtTime(freq, audioCtx.currentTime + index * 0.15);
        gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime + index * 0.15);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + index * 0.15 + 0.3);
        
        oscillator.start(audioCtx.currentTime + index * 0.15);
        oscillator.stop(audioCtx.currentTime + index * 0.15 + 0.3);
    });
}

function playLoseSound() {
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);
    
    oscillator.frequency.setValueAtTime(400, audioCtx.currentTime);
    oscillator.frequency.exponentialRampToValueAtTime(100, audioCtx.currentTime + 0.5);
    
    gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
    
    oscillator.start(audioCtx.currentTime);
    oscillator.stop(audioCtx.currentTime + 0.5);
}

function playTickSound() {
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);
    
    oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
    gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.05);
    
    oscillator.start(audioCtx.currentTime);
    oscillator.stop(audioCtx.currentTime + 0.05);
}

// ==================== Konfeti Efekti ====================
function createConfetti() {
    const container = document.getElementById('confetti-container');
    const colors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6'];
    
    for (let i = 0; i < 100; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            container.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 3000);
        }, i * 30);
    }
}

// ==================== Parçacık Efekti ====================
function createParticles() {
    const container = document.getElementById('particleContainer');
    for (let i = 0; i < 20; i++) {
        setTimeout(() => {
            const particle = document.createElement('div');
            particle.className = 'particle';
            
            const angle = (Math.random() * 360) * (Math.PI / 180);
            const distance = Math.random() * 200 + 100;
            const duration = Math.random() * 1 + 0.5;
            
            particle.style.left = '50%';
            particle.style.top = '50%';
            
            particle.animate([
                { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
                { 
                    transform: `translate(calc(-50% + ${Math.cos(angle) * distance}px), calc(-50% + ${Math.sin(angle) * distance}px)) scale(0)`,
                    opacity: 0 
                }
            ], {
                duration: duration * 1000,
                easing: 'cubic-bezier(0, .9, .57, 1)'
            });
            
            container.appendChild(particle);
            setTimeout(() => particle.remove(), duration * 1000);
        }, i * 50);
    }
}

// ==================== Çark Çizimi ====================
const canvas = document.getElementById('wheel');
const ctx = canvas.getContext('2d');
const rewards = <?php echo json_encode($rewards); ?>;
const segmentAngle = (2 * Math.PI) / rewards.length;
let isSpinning = false;
let currentRotation = 0;

function drawWheel() {
    ctx.clearRect(0, 0, 450, 450);
    
    // Her segment için
    rewards.forEach((reward, i) => {
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(225, 225);
        ctx.arc(225, 225, 215, i * segmentAngle, (i + 1) * segmentAngle);
        ctx.fillStyle = reward.color || `hsl(${i * (360 / rewards.length)}, 70%, 60%)`;
        ctx.fill();
        
        // Gradient border
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 3;
        ctx.stroke();
        
        // İçerideki koyu kenarlık
        ctx.beginPath();
        ctx.moveTo(225, 225);
        ctx.arc(225, 225, 210, i * segmentAngle, (i + 1) * segmentAngle);
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.2)';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // Yazıyı yaz
        ctx.translate(225, 225);
        ctx.rotate(i * segmentAngle + segmentAngle / 2);
        ctx.textAlign = "center";
        ctx.font = "bold 16px 'Plus Jakarta Sans', sans-serif";
        
        // Text shadow
        ctx.shadowColor = "rgba(0, 0, 0, 0.7)";
        ctx.shadowBlur = 6;
        ctx.shadowOffsetX = 2;
        ctx.shadowOffsetY = 2;
        
        ctx.fillStyle = "#ffffff";
        ctx.fillText(reward.label, 155, 8);
        
        // İkinci satır (miktar varsa)
        if (reward.amount > 0) {
            ctx.font = "bold 13px 'Plus Jakarta Sans', sans-serif";
            ctx.fillStyle = "#fbbf24";
            // Miktar formatla - gereksiz sıfırları kaldır
            const formattedAmount = parseFloat(reward.amount) === parseInt(reward.amount) 
                ? parseInt(reward.amount) 
                : parseFloat(reward.amount).toFixed(2).replace(/\.?0+$/, '');
            ctx.fillText(`${formattedAmount} USDT`, 155, 26);
        }
        
        ctx.restore();
    });
    
    // Merkez daire - gradient
    const gradient = ctx.createRadialGradient(225, 225, 0, 225, 225, 55);
    gradient.addColorStop(0, '#1a1a2e');
    gradient.addColorStop(1, '#0f0f1e');
    
    ctx.beginPath();
    ctx.arc(225, 225, 55, 0, 2 * Math.PI);
    ctx.fillStyle = gradient;
    ctx.fill();
    ctx.strokeStyle = '#6366f1';
    ctx.lineWidth = 4;
    ctx.stroke();
    
    // İç highlight
    ctx.beginPath();
    ctx.arc(225, 225, 50, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
    ctx.lineWidth = 2;
    ctx.stroke();
}

drawWheel();

// ==================== Çevirme İşlemi ====================
document.getElementById('spinBtn').addEventListener('click', function() {
    if (isSpinning || this.disabled) return;
    
    isSpinning = true;
    audioCtx.resume(); // Safari için
    
    const btn = this;
    btn.classList.add('spinning');
    btn.textContent = '...';
    
    playSpinSound();
    createParticles();
    
    // FormData oluştur
    const formData = new FormData();
    formData.append('action', 'spin');
    formData.append('csrf_token', '<?php echo $csrfToken; ?>');
    
    // Sunucuya istek gönder
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) throw new Error('Network error');
        return res.json();
    })
    .then(data => {
        if (data.error) {
            alert(data.error);
            isSpinning = false;
            btn.classList.remove('spinning');
            btn.textContent = 'ÇEVİR!';
            playLoseSound();
            return;
        }
        
        // Tick sesi için interval
        let tickCount = 0;
        const tickInterval = setInterval(() => {
            if (tickCount++ < 30) {
                playTickSound();
            } else {
                clearInterval(tickInterval);
            }
        }, 200);
        
        // Animasyonu başlat
        canvas.style.transform = `rotate(${data.angle}deg)`;
        currentRotation = data.angle;
        
        // Sonucu göster (animasyon bitince)
        setTimeout(() => {
            isSpinning = false;
            btn.classList.remove('spinning');
            clearInterval(tickInterval);
            
            const resultBox = document.getElementById('resultBox');
            const spinsLeftEl = document.getElementById('spinsLeft');
            
            resultBox.style.display = 'block';
            
            if (data.reward.amount > 0) {
                // KAZANDI!
                playWinSound();
                createConfetti();
                
                resultBox.className = 'result-box win';
                resultBox.innerHTML = `
                    <h3>🎉 Tebrikler!</h3>
                    <p class="mb-0" style="font-size: 18px;">
                        <strong style="font-size: 24px; color: #10b981;">${data.reward.label}</strong><br>
                        Bakiyenize <strong style="font-size: 22px; color: #fbbf24;">${data.reward.amount} USDT</strong> eklendi!
                    </p>
                `;
            } else {
                // Kaybetti
                playLoseSound();
                
                resultBox.className = 'result-box lose';
                resultBox.innerHTML = `
                    <h3>😔 Bu Sefer Olmadı</h3>
                    <p class="mb-0" style="font-size: 16px; color: var(--text-secondary);">
                        ${data.reward.label}<br>
                        Yarın tekrar dene!
                    </p>
                `;
            }
            
            // Kalan hakkı güncelle
            if (spinsLeftEl) spinsLeftEl.textContent = data.spins_left;
            
            // Butonu güncelle
            if (data.spins_left <= 0) {
                btn.disabled = true;
                btn.textContent = 'YARIN';
            } else {
                btn.textContent = 'ÇEVİR!';
            }
            
            // 5 saniye sonra sonuç kutusunu gizle
            setTimeout(() => {
                resultBox.style.display = 'none';
            }, 8000);
            
        }, 6200);
    })
    .catch(err => {
        console.error('Hata:', err);
        alert('Bir hata oluştu. Sayfayı yenileyip tekrar deneyin.');
        isSpinning = false;
        btn.classList.remove('spinning');
        btn.textContent = 'ÇEVİR!';
        playLoseSound();
    });
});
</script>

<?php require 'templates/footer.php'; ?>