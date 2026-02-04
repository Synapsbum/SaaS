<?php
$title = 'Kripto Cüzdan Yönetimi';
$user = $auth->user();
$db = Database::getInstance();

// ID'yi doğru şekilde al
$rentalId = isset($id) ? (int)$id : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($rentalId <= 0) {
    $_SESSION['error'] = 'Geçersiz kiralama ID';
    Helper::redirect('rental');
    exit;
}

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
                    $existing = $db->fetch("
                        SELECT id FROM rental_crypto_wallets 
                        WHERE rental_id = ? AND wallet_type = ?
                    ", [$rentalId, $walletType]);
                    
                    if ($existing) {
                        $db->query("
                            UPDATE rental_crypto_wallets 
                            SET wallet_address = ?, updated_at = NOW()
                            WHERE id = ?
                        ", [$walletAddress, $existing['id']]);
                        
                        $_SESSION['success'] = 'Cüzdan güncellendi!';
                    } else {
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

require 'templates/header.php';
?>

<style>
.page-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 24px 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wallet-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.wallet-type-card {
    background: linear-gradient(135deg, var(--wallet-color) 0%, var(--wallet-color-dark, #333) 100%);
    border-radius: 20px;
    padding: 32px 28px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
}

.wallet-type-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
    opacity: 0;
    transition: opacity 0.3s;
}

.wallet-type-card:hover::before {
    opacity: 1;
}

.wallet-type-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 40px rgba(0,0,0,0.3);
    border-color: rgba(255,255,255,0.3);
}

.wallet-type-card.has-wallet {
    opacity: 0.8;
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(16, 16, 32, 0.95));
    border-color: var(--wallet-color);
}

.wallet-type-icon {
    font-size: 56px;
    margin-bottom: 16px;
    display: block;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
}

.wallet-type-card h5 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.wallet-type-card .network-badge {
    background: rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    margin-top: 8px;
}

.wallet-type-card .status-tag {
    background: rgba(16, 185, 129, 0.9);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
}

.wallet-card {
    background: rgba(26, 26, 46, 0.8);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.wallet-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: var(--wallet-color);
}

.wallet-card:hover {
    border-color: var(--wallet-color);
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.2);
}

.wallet-card.inactive {
    opacity: 0.5;
}

.wallet-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
}

.wallet-icon-large {
    width: 80px;
    height: 80px;
    background: var(--wallet-color);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    flex-shrink: 0;
    box-shadow: 0 12px 24px rgba(0,0,0,0.2);
}

.wallet-info {
    flex: 1;
}

.wallet-info h5 {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 8px 0;
    color: white;
}

.wallet-network {
    font-size: 14px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.wallet-status {
    margin-left: auto;
}

.wallet-address-box {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 16px 20px;
    margin: 20px 0;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    word-break: break-all;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: var(--primary-light);
    font-weight: 600;
}

.wallet-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.add-form-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.add-form-modal.active {
    display: flex;
}

.modal-content {
    background: rgba(26, 26, 46, 0.98);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 20px;
    padding: 36px;
    max-width: 550px;
    width: 100%;
    box-shadow: 0 24px 64px rgba(0,0,0,0.5);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.modal-header h5 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: white;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.form-group input {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 14px 18px;
    color: white;
    width: 100%;
    font-size: 15px;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(0,0,0,0.4);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-group input[readonly] {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
    color: var(--primary-light);
    cursor: not-allowed;
}

.form-group small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 6px;
    display: block;
}

.section-header {
    margin-bottom: 28px;
}

.section-header h6 {
    font-size: 18px;
    font-weight: 700;
    color: white;
    margin: 0 0 8px 0;
}

.section-header p {
    color: var(--text-secondary);
    font-size: 14px;
    margin: 0;
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 style="margin: 0 0 8px 0; color: white;"><i class="bi bi-wallet2 me-2"></i>Kripto Cüzdan Yönetimi</h4>
            <div style="color: var(--text-secondary); font-size: 14px;">
                <i class="bi bi-box-seam me-1"></i>
                <?php echo htmlspecialchars($rental['script_name']); ?> 
                <span style="opacity: 0.5; margin: 0 8px;">•</span>
                <i class="bi bi-globe me-1"></i>
                <?php echo htmlspecialchars($rental['domain']); ?>
            </div>
        </div>
        <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Geri Dön
        </a>
    </div>

    <div class="card" style="background: rgba(26, 26, 46, 0.8); border: 1px solid rgba(255, 255, 255, 0.1);">
        <div class="card-body" style="padding: 32px;">
            
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--success); border-radius: 12px; margin-bottom: 24px;">
                <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); color: #ff4444; border-radius: 12px; margin-bottom: 24px;">
                <i class="bi bi-x-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Cüzdan Ekleme Kartları -->
            <div class="section-header">
                <h6><i class="bi bi-plus-circle me-2"></i>Cüzdan Ekle / Güncelle</h6>
                <p>Aşağıdaki cüzdanlardan birini seçerek ekleyin veya güncelleyin</p>
            </div>
            
            <div class="wallet-type-grid">
                <?php foreach ($availableTypes as $type => $info): 
                    $hasWallet = in_array($type, $existingTypes);
                ?>
                <div class="wallet-type-card <?php echo $hasWallet ? 'has-wallet' : ''; ?>" 
                     style="--wallet-color: <?php echo $info['color']; ?>; --wallet-color-dark: <?php echo $info['color']; ?>88;"
                     onclick="openWalletForm('<?php echo $type; ?>')">
                    <span class="wallet-type-icon"><?php echo $info['icon']; ?></span>
                    <h5><?php echo $info['name']; ?></h5>
                    <span class="network-badge">
                        <i class="bi bi-diagram-3 me-1"></i><?php echo $info['network']; ?> Network
                    </span>
                    <?php if ($hasWallet): ?>
                    <div class="status-tag">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Ekli - Düzenlemek için tıkla</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Kayıtlı Cüzdanlar -->
            <?php if ($wallets): ?>
            <div class="section-header">
                <h6><i class="bi bi-list-ul me-2"></i>Kayıtlı Cüzdanlar (<?php echo count($wallets); ?>)</h6>
                <p>Aktif kripto cüzdanlarınızı yönetin</p>
            </div>
            
            <?php foreach ($wallets as $wallet): 
                $info = $availableTypes[$wallet['wallet_type']];
            ?>
            <div class="wallet-card <?php echo $wallet['status'] === 'inactive' ? 'inactive' : ''; ?>" 
                 style="--wallet-color: <?php echo $info['color']; ?>;">
                <div class="wallet-header">
                    <div class="wallet-icon-large" style="background: <?php echo $info['color']; ?>;">
                        <?php echo $info['icon']; ?>
                    </div>
                    <div class="wallet-info">
                        <h5><?php echo $info['name']; ?></h5>
                        <div class="wallet-network">
                            <i class="bi bi-diagram-3"></i>
                            <span><?php echo $info['network']; ?> Network</span>
                        </div>
                    </div>
                    <div class="wallet-status">
                        <span class="badge bg-<?php echo $wallet['status'] === 'active' ? 'success' : 'secondary'; ?>" 
                              style="padding: 8px 16px; font-size: 13px; font-weight: 700;">
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
                <i class="bi bi-wallet" style="font-size: 64px; opacity: 0.2;"></i>
                <h5 class="mt-3" style="color: white;">Henüz cüzdan eklenmemiş</h5>
                <p>Yukarıdaki kartlardan birini seçerek ilk cüzdanınızı ekleyin</p>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Cüzdan Ekleme/Düzenleme Modal -->
<div class="add-form-modal" id="walletModal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 id="modalTitle">Cüzdan Ekle</h5>
            <button onclick="closeWalletForm()" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_or_update">
            <input type="hidden" name="wallet_type" id="formWalletType">
            
            <div class="form-group">
                <label>Cüzdan Tipi</label>
                <input type="text" id="formWalletName" readonly>
            </div>
            
            <div class="form-group">
                <label>Cüzdan Adresi</label>
                <input type="text" name="wallet_address" id="formWalletAddress" 
                       style="font-family: 'Courier New', monospace;" 
                       placeholder="Cüzdan adresinizi girin" required>
                <small id="formExample"></small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill" style="padding: 12px; font-weight: 600;">
                    <i class="bi bi-check-lg me-2"></i>Kaydet
                </button>
                <button type="button" onclick="closeWalletForm()" class="btn btn-outline-secondary" style="padding: 12px 24px;">
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
    document.getElementById('formExample').innerHTML = '<i class="bi bi-info-circle me-1"></i>Örnek: ' + info.example;
    
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeWalletForm();
    }
});

document.getElementById('walletModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeWalletForm();
    }
});
</script>

<?php require 'templates/footer.php'; ?>