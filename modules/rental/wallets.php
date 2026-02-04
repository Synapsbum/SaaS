<?php
$title = 'Kripto Cüzdan Yönetimi';
$user = $auth->user();
$db = Database::getInstance();

$rentalId = (int)$id;

// Rental kontrolü
$rental = $db->fetch("
    SELECT r.*, s.name as script_name, d.domain
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    LEFT JOIN script_domains d ON r.domain_id = d.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$rental) {
    Helper::redirect('rental');
    exit;
}

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_or_update':
                $walletType = $_POST['wallet_type'] ?? '';
                $walletAddress = trim($_POST['wallet_address'] ?? '');
                
                if ($walletType && $walletAddress) {
                    // Cüzdan var mı kontrol et
                    $existing = $db->fetch("
                        SELECT id FROM rental_crypto_wallets 
                        WHERE rental_id = ? AND wallet_type = ?
                    ", [$rentalId, $walletType]);
                    
                    if ($existing) {
                        // Güncelle
                        $db->query("
                            UPDATE rental_crypto_wallets 
                            SET wallet_address = ?, updated_at = NOW()
                            WHERE id = ?
                        ", [$walletAddress, $existing['id']]);
                        
                        $_SESSION['success'] = 'Cüzdan güncellendi!';
                    } else {
                        // Ekle
                        $db->query("
                            INSERT INTO rental_crypto_wallets 
                            (rental_id, wallet_type, wallet_address, status)
                            VALUES (?, ?, ?, 'active')
                        ", [$rentalId, $walletType, $walletAddress]);
                        
                        $_SESSION['success'] = 'Cüzdan eklendi!';
                    }
                } else {
                    $_SESSION['error'] = 'Tüm alanları doldurun!';
                }
                break;
                
            case 'toggle':
                $walletId = (int)($_POST['wallet_id'] ?? 0);
                $newStatus = $_POST['new_status'] ?? 'active';
                
                $db->query("
                    UPDATE rental_crypto_wallets 
                    SET status = ? 
                    WHERE id = ? AND rental_id = ?
                ", [$newStatus, $walletId, $rentalId]);
                
                $_SESSION['success'] = 'Cüzdan durumu güncellendi!';
                break;
                
            case 'delete':
                $walletId = (int)($_POST['wallet_id'] ?? 0);
                
                $db->query("
                    DELETE FROM rental_crypto_wallets 
                    WHERE id = ? AND rental_id = ?
                ", [$walletId, $rentalId]);
                
                $_SESSION['success'] = 'Cüzdan silindi!';
                break;
        }
        
        Helper::redirect('rental/manage/' . $rentalId . '/wallets');
        exit;
    }
}

// Cüzdanları getir
$wallets = $db->fetchAll("
    SELECT * FROM rental_crypto_wallets 
    WHERE rental_id = ?
    ORDER BY 
        FIELD(wallet_type, 'USDT_TRC20', 'TRX_TRON', 'BTC'),
        id DESC
", [$rentalId]);

// Mevcut cüzdan tiplerini listele
$existingTypes = array_column($wallets, 'wallet_type');
$availableTypes = [
    'USDT_TRC20' => [
        'name' => 'USDT (TRC20)',
        'icon' => '💵',
        'color' => '#26a17b',
        'network' => 'TRON',
        'example' => 'TXyz123...'
    ],
    'TRX_TRON' => [
        'name' => 'TRX (TRON)',
        'icon' => '🔺',
        'color' => '#eb0029',
        'network' => 'TRON',
        'example' => 'TXyz123...'
    ],
    'BTC' => [
        'name' => 'Bitcoin (BTC)',
        'icon' => '₿',
        'color' => '#f7931a',
        'network' => 'Bitcoin',
        'example' => '1A1zP1eP...'
    ]
];

require 'templates/header_new.php';
?>

<style>
.wallet-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.wallet-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--wallet-color);
}

.wallet-card:hover {
    border-color: var(--wallet-color);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.wallet-card.inactive {
    opacity: 0.6;
}

.wallet-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.wallet-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--wallet-color), var(--wallet-color-dark, #333));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.wallet-info h5 {
    margin: 0 0 5px 0;
}

.wallet-network {
    font-size: 13px;
    color: var(--text-muted);
}

.wallet-address-box {
    background: rgba(0,0,0,0.05);
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    word-break: break-all;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.wallet-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.add-wallet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.wallet-type-card {
    background: linear-gradient(135deg, var(--wallet-color) 0%, var(--wallet-color-dark, #333) 100%);
    border-radius: 16px;
    padding: 24px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.wallet-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.2);
    color: white;
}

.wallet-type-card.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.wallet-type-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.add-form-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.add-form-modal.active {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
}
</style>

<div class="mb-4">
    <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-2"></i>Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-wallet2 me-2"></i>Kripto Cüzdan Yönetimi</h5>
        <div>
            <span style="color: var(--text-muted); font-size: 14px;">
                <?php echo htmlspecialchars($rental['script_name']); ?> - <?php echo htmlspecialchars($rental['domain']); ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Cüzdan Ekleme Kartları -->
        <div class="mb-4">
            <h6 class="mb-3"><i class="bi bi-plus-circle me-2"></i>Cüzdan Ekle / Güncelle</h6>
            <div class="add-wallet-grid">
                <?php foreach ($availableTypes as $type => $info): ?>
                <div class="wallet-type-card <?php echo in_array($type, $existingTypes) ? 'disabled' : ''; ?>" 
                     style="--wallet-color: <?php echo $info['color']; ?>; --wallet-color-dark: <?php echo $info['color']; ?>88;"
                     onclick="openWalletForm('<?php echo $type; ?>')">
                    <div class="wallet-type-icon"><?php echo $info['icon']; ?></div>
                    <h5 style="margin: 0 0 5px 0;"><?php echo $info['name']; ?></h5>
                    <div style="font-size: 13px; opacity: 0.9;"><?php echo $info['network']; ?> Network</div>
                    <?php if (in_array($type, $existingTypes)): ?>
                    <div class="mt-3" style="font-size: 12px; background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 20px; display: inline-block;">
                        <i class="bi bi-check-circle me-1"></i>Ekli - Düzenle
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Kayıtlı Cüzdanlar -->
        <?php if ($wallets): ?>
        <div class="mb-3">
            <h6><i class="bi bi-list-ul me-2"></i>Kayıtlı Cüzdanlar (<?php echo count($wallets); ?>)</h6>
        </div>
        
        <?php foreach ($wallets as $wallet): 
            $info = $availableTypes[$wallet['wallet_type']];
        ?>
        <div class="wallet-card <?php echo $wallet['status'] === 'inactive' ? 'inactive' : ''; ?>" 
             style="--wallet-color: <?php echo $info['color']; ?>; --wallet-color-dark: <?php echo $info['color']; ?>88;">
            <div class="wallet-header">
                <div class="wallet-icon" style="background: <?php echo $info['color']; ?>;">
                    <?php echo $info['icon']; ?>
                </div>
                <div class="wallet-info">
                    <h5><?php echo $info['name']; ?></h5>
                    <div class="wallet-network">
                        <i class="bi bi-diagram-3 me-1"></i><?php echo $info['network']; ?> Network
                    </div>
                </div>
                <div class="ms-auto">
                    <span class="badge-<?php echo $wallet['status']; ?>">
                        <?php echo $wallet['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                    </span>
                </div>
            </div>
            
            <div class="wallet-address-box">
                <span><?php echo htmlspecialchars($wallet['wallet_address']); ?></span>
                <button class="btn btn-sm btn-outline-primary" onclick="copyAddress('<?php echo htmlspecialchars($wallet['wallet_address']); ?>')">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
            
            <div class="wallet-actions">
                <button class="btn btn-sm btn-primary" onclick="openWalletForm('<?php echo $wallet['wallet_type']; ?>', '<?php echo htmlspecialchars($wallet['wallet_address']); ?>')">
                    <i class="bi bi-pencil me-1"></i>Düzenle
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="wallet_id" value="<?php echo $wallet['id']; ?>">
                    <input type="hidden" name="new_status" value="<?php echo $wallet['status'] === 'active' ? 'inactive' : 'active'; ?>">
                    <button type="submit" class="btn btn-sm btn-<?php echo $wallet['status'] === 'active' ? 'warning' : 'success'; ?>">
                        <i class="bi bi-<?php echo $wallet['status'] === 'active' ? 'pause' : 'play'; ?>-fill me-1"></i>
                        <?php echo $wallet['status'] === 'active' ? 'Pasif Yap' : 'Aktif Yap'; ?>
                    </button>
                </form>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu cüzdan silinecek. Emin misiniz?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="wallet_id" value="<?php echo $wallet['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <div class="text-center py-5" style="color: var(--text-muted);">
            <i class="bi bi-wallet" style="font-size: 48px; opacity: 0.3;"></i>
            <p class="mt-3">Henüz cüzdan eklenmemiş</p>
            <p style="font-size: 14px;">Yukarıdaki kartlardan birini seçerek cüzdan ekleyebilirsiniz</p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Cüzdan Ekleme/Düzenleme Modal -->
<div class="add-form-modal" id="walletModal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h5 id="modalTitle">Cüzdan Ekle</h5>
            <button onclick="closeWalletForm()" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_or_update">
            <input type="hidden" name="wallet_type" id="formWalletType">
            
            <div class="mb-3">
                <label class="form-label">Cüzdan Tipi</label>
                <input type="text" id="formWalletName" class="form-control" readonly>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Cüzdan Adresi</label>
                <input type="text" name="wallet_address" id="formWalletAddress" 
                       class="form-control" style="font-family: 'Courier New', monospace;" 
                       placeholder="Cüzdan adresinizi girin" required>
                <small class="text-muted" id="formExample"></small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-check-lg me-2"></i>Kaydet
                </button>
                <button type="button" onclick="closeWalletForm()" class="btn btn-outline-secondary">
                    İptal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const walletInfo = <?php echo json_encode($availableTypes); ?>;

function openWalletForm(type, address = '') {
    const modal = document.getElementById('walletModal');
    const info = walletInfo[type];
    
    document.getElementById('modalTitle').textContent = address ? 'Cüzdan Düzenle' : 'Cüzdan Ekle';
    document.getElementById('formWalletType').value = type;
    document.getElementById('formWalletName').value = info.name;
    document.getElementById('formWalletAddress').value = address;
    document.getElementById('formExample').textContent = 'Örnek: ' + info.example;
    
    modal.classList.add('active');
}

function closeWalletForm() {
    document.getElementById('walletModal').classList.remove('active');
}

function copyAddress(address) {
    navigator.clipboard.writeText(address).then(() => {
        alert('Cüzdan adresi kopyalandı: ' + address);
    });
}

// ESC tuşu ile modal kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeWalletForm();
    }
});

// Modal dışına tıklayınca kapatma
document.getElementById('walletModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeWalletForm();
    }
});
</script>

<?php require 'templates/footer_new.php'; ?>
