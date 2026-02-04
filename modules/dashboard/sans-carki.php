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
        $finalAngle = $totalSpins + (360 - $winningAngle); // İşaretçi 12'de olduğu için tersini al
        
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
.wheel-wrapper {
    position: relative;
    width: 400px;
    height: 400px;
    margin: 0 auto;
}
.wheel-container {
    width: 100%;
    height: 100%;
    position: relative;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 0 30px rgba(99, 102, 241, 0.5);
}
#wheel {
    width: 100%;
    height: 100%;
    transition: transform 5s cubic-bezier(0.17, 0.67, 0.12, 0.99);
    transform-origin: center;
}
.wheel-pointer {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 20px solid transparent;
    border-right: 20px solid transparent;
    border-top: 40px solid #ec4899;
    z-index: 10;
    filter: drop-shadow(0 0 10px rgba(236, 72, 153, 0.8));
}
.spin-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #ec4899);
    border: 5px solid #fff;
    color: white;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    z-index: 20;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
    transition: all 0.3s;
}
.spin-btn:hover:not(:disabled) {
    transform: translate(-50%, -50%) scale(1.15);
    box-shadow: 0 0 30px rgba(99, 102, 241, 0.8);
}
.spin-btn:disabled {
    background: #6b7280;
    cursor: not-allowed;
    opacity: 0.7;
}
.spin-btn.spinning {
    animation: pulse-btn 0.5s infinite;
}
@keyframes pulse-btn {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.05); }
}
.result-box {
    display: none;
    margin-top: 20px;
    padding: 20px;
    border-radius: 12px;
    animation: fadeIn 0.5s;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-8 text-center">
        <div class="admin-card mb-4">
            <div class="admin-card-header justify-content-center">
                <h2 class="mb-0">🎰 Günlük Şans Çarkı</h2>
            </div>
            <div class="admin-card-body">
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Günde <strong><?php echo $dailyLimit; ?></strong> kez çevirme hakkınız var.
                    Kalan: <strong id="spinsLeft"><?php echo $spinsLeft; ?></strong>
                </div>
                
                <div class="wheel-wrapper mb-4">
                    <div class="wheel-pointer"></div>
                    <div class="wheel-container">
                        <canvas id="wheel" width="400" height="400"></canvas>
                    </div>
                    <button class="spin-btn" id="spinBtn" <?php echo !$canSpin ? 'disabled' : ''; ?>>
                        <?php echo $canSpin ? 'ÇEVİR' : 'YARIN'; ?>
                    </button>
                </div>
                
                <div id="resultBox" class="result-box">
                    <h3 id="resultTitle"></h3>
                    <p id="resultMessage" class="mb-0"></p>
                </div>
            </div>
        </div>
        
        <!-- Son Kazananlar -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">🏆 Son Kazananlar</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Ödül</th>
                            <th>Saat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentWinners as $w): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($w['username']); ?></td>
                            <td class="text-success fw-bold">+<?php echo number_format($w['amount_won'], 2); ?> USDT</td>
                            <td><?php echo date('H:i', strtotime($w['spun_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentWinners)): ?>
                        <tr>
                            <td colspan="3" class="text-muted py-4">Henüz kazanan yok. İlk sen ol!</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const canvas = document.getElementById('wheel');
const ctx = canvas.getContext('2d');
const rewards = <?php echo json_encode($rewards); ?>;
const segmentAngle = (2 * Math.PI) / rewards.length;
let isSpinning = false;

// Çarkı çiz
function drawWheel() {
    ctx.clearRect(0, 0, 400, 400);
    
    rewards.forEach((reward, i) => {
        ctx.beginPath();
        ctx.moveTo(200, 200);
        ctx.arc(200, 200, 190, i * segmentAngle, (i + 1) * segmentAngle);
        ctx.fillStyle = reward.color;
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // Yazıyı yaz
        ctx.save();
        ctx.translate(200, 200);
        ctx.rotate(i * segmentAngle + segmentAngle / 2);
        ctx.textAlign = "center";
        ctx.fillStyle = "#fff";
        ctx.font = "bold 14px Arial";
        ctx.shadowColor = "rgba(0,0,0,0.5)";
        ctx.shadowBlur = 4;
        ctx.fillText(reward.label, 140, 5);
        ctx.restore();
    });
    
    // Merkez daire
    ctx.beginPath();
    ctx.arc(200, 200, 45, 0, 2 * Math.PI);
    ctx.fillStyle = '#1a1a2e';
    ctx.fill();
    ctx.strokeStyle = '#6366f1';
    ctx.lineWidth = 3;
    ctx.stroke();
}

drawWheel();

// Çevirme işlemi
document.getElementById('spinBtn').addEventListener('click', function() {
    if (isSpinning || this.disabled) return;
    
    isSpinning = true;
    const btn = this;
    btn.classList.add('spinning');
    btn.textContent = '...';
    
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
            btn.textContent = 'ÇEVİR';
            return;
        }
        
        // Animasyonu başlat
        canvas.style.transform = `rotate(${data.angle}deg)`;
        
        // Sonucu göster (animasyon bitince)
        setTimeout(() => {
            isSpinning = false;
            btn.classList.remove('spinning');
            
            const resultBox = document.getElementById('resultBox');
            const resultTitle = document.getElementById('resultTitle');
            const resultMessage = document.getElementById('resultMessage');
            const spinsLeftEl = document.getElementById('spinsLeft');
            
            resultBox.style.display = 'block';
            
            if (data.reward.amount > 0) {
                resultBox.className = 'result-box alert alert-success';
                resultTitle.innerHTML = '🎉 Tebrikler!';
                resultMessage.innerHTML = `<strong>${data.reward.label}</strong> kazandınız!<br>Bakiyenize <strong>${data.reward.amount} USDT</strong> eklendi.`;
            } else {
                resultBox.className = 'result-box alert alert-secondary';
                resultTitle.innerHTML = '😔 Bu sefer olmadı';
                resultMessage.innerHTML = 'Tekrar denemek için yarını bekleyin.';
            }
            
            // Kalan hakkı güncelle
            if (spinsLeftEl) spinsLeftEl.textContent = data.spins_left;
            
            // Butonu güncelle
            if (data.spins_left <= 0) {
                btn.disabled = true;
                btn.textContent = 'YARIN';
            } else {
                btn.textContent = 'ÇEVİR';
            }
            
        }, 5200); // Animasyon süresi (5s + 200ms buffer)
    })
    .catch(err => {
        console.error('Hata:', err);
        alert('Bir hata oluştu. Sayfayı yenileyip tekrar deneyin.');
        isSpinning = false;
        btn.classList.remove('spinning');
        btn.textContent = 'ÇEVİR';
    });
});
</script>

<?php require 'templates/footer.php'; ?>