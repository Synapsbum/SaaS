<?php
$title = 'IBAN Yönetimi';
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
            case 'add':
                $bankName = trim($_POST['bank_name'] ?? '');
                $accountHolder = trim($_POST['account_holder'] ?? '');
                $iban = trim($_POST['iban'] ?? '');
                
                if ($bankName && $accountHolder && $iban) {
                    $iban = strtoupper(str_replace(' ', '', $iban));
                    
                    if (preg_match('/^TR\d{24}$/', $iban)) {
                        $db->query("
                            INSERT INTO rental_ibans 
                            (rental_id, bank_name, account_holder, iban, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ", [$rentalId, $bankName, $accountHolder, $iban]);
                        
                        $_SESSION['success'] = 'IBAN başarıyla eklendi!';
                    } else {
                        $_SESSION['error'] = 'Geçersiz IBAN formatı! (TR ile başlamalı, 26 karakter olmalı)';
                    }
                } else {
                    $_SESSION['error'] = 'Tüm alanları doldurun!';
                }
                break;
                
            case 'toggle':
                $ibanId = (int)($_POST['iban_id'] ?? 0);
                $newStatus = $_POST['new_status'] ?? 'active';
                
                $db->query("
                    UPDATE rental_ibans 
                    SET status = ? 
                    WHERE id = ? AND rental_id = ?
                ", [$newStatus, $ibanId, $rentalId]);
                
                $_SESSION['success'] = 'IBAN durumu güncellendi!';
                break;
                
            case 'delete':
                $ibanId = (int)($_POST['iban_id'] ?? 0);
                
                $db->query("
                    DELETE FROM rental_ibans 
                    WHERE id = ? AND rental_id = ?
                ", [$ibanId, $rentalId]);
                
                $_SESSION['success'] = 'IBAN silindi!';
                break;
                
            case 'update_order':
                $orders = json_decode($_POST['orders'] ?? '[]', true);
                foreach ($orders as $index => $ibanId) {
                    $db->query("
                        UPDATE rental_ibans 
                        SET display_order = ? 
                        WHERE id = ? AND rental_id = ?
                    ", [$index, $ibanId, $rentalId]);
                }
                echo json_encode(['success' => true]);
                exit;
        }
        
        Helper::redirect('rental/manage/' . $rentalId . '/ibans');
        exit;
    }
}

// IBANları getir
$ibans = $db->fetchAll("
    SELECT * FROM rental_ibans 
    WHERE rental_id = ?
    ORDER BY display_order ASC, id DESC
", [$rentalId]);

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

.iban-card {
    background: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    position: relative;
}

.iban-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border-radius: 16px 0 0 16px;
}

.iban-card:hover {
    border-color: rgba(99, 102, 241, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2);
}

.iban-card.inactive {
    opacity: 0.5;
}

.iban-card.inactive::before {
    background: linear-gradient(135deg, #6b7280, #4b5563);
}

.iban-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.bank-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: white;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
}

.iban-details {
    flex: 1;
}

.iban-details h6 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 700;
    color: white;
}

.iban-holder {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.iban-number {
    font-family: 'Courier New', monospace;
    font-size: 15px;
    color: var(--primary-light);
    letter-spacing: 1px;
    font-weight: 600;
    background: rgba(99, 102, 241, 0.1);
    padding: 8px 12px;
    border-radius: 8px;
    display: inline-block;
}

.iban-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.drag-handle {
    cursor: move;
    color: var(--text-muted);
    padding: 8px;
    transition: color 0.3s;
}

.drag-handle:hover {
    color: var(--primary);
}

.add-iban-section {
    background: rgba(26, 26, 46, 0.6);
    border: 2px dashed rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 32px;
    transition: all 0.3s;
}

.add-iban-section:hover {
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(26, 26, 46, 0.8);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.form-group input {
    background: rgba(26, 26, 46, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 12px 16px;
    color: white;
    width: 100%;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(26, 26, 46, 0.8);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-group small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 4px;
    display: block;
}

.status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.status-badge.inactive {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.section-header h6 {
    font-size: 16px;
    font-weight: 700;
    color: white;
    margin: 0;
}

.section-info {
    color: var(--text-muted);
    font-size: 13px;
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 style="margin: 0 0 8px 0; color: white;"><i class="bi bi-bank me-2"></i>IBAN Yönetimi</h4>
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
            
            <!-- Yeni IBAN Ekleme -->
            <div class="add-iban-section">
                <h6 style="color: white; font-size: 18px; margin-bottom: 24px;">
                    <i class="bi bi-plus-circle me-2" style="color: var(--primary);"></i>Yeni IBAN Ekle
                </h6>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Banka Adı</label>
                            <input type="text" name="bank_name" placeholder="örn: Ziraat Bankası" required>
                        </div>
                        <div class="form-group">
                            <label>Hesap Sahibi</label>
                            <input type="text" name="account_holder" placeholder="Ad Soyad" required>
                        </div>
                        <div class="form-group">
                            <label>IBAN Numarası</label>
                            <input type="text" name="iban" placeholder="TR00 0000 0000 0000 0000 0000 00" 
                                   pattern="^TR\d{24}$" maxlength="26" style="font-family: 'Courier New', monospace;" required>
                            <small><i class="bi bi-info-circle me-1"></i>TR ile başlamalı, toplam 26 karakter</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-weight: 600;">
                        <i class="bi bi-plus-lg me-2"></i>IBAN Ekle
                    </button>
                </form>
            </div>
            
            <!-- IBAN Listesi -->
            <?php if ($ibans): ?>
            <div class="section-header">
                <h6><i class="bi bi-list-ul me-2"></i>Kayıtlı IBANlar (<?php echo count($ibans); ?>)</h6>
                <span class="section-info">
                    <i class="bi bi-grip-vertical me-1"></i>Sıralamak için sürükleyin
                </span>
            </div>
            
            <div id="ibanList">
                <?php foreach ($ibans as $iban): ?>
                <div class="iban-card <?php echo $iban['status'] === 'inactive' ? 'inactive' : ''; ?>" data-iban-id="<?php echo $iban['id']; ?>">
                    <div class="iban-info">
                        <div class="drag-handle">
                            <i class="bi bi-grip-vertical" style="font-size: 24px;"></i>
                        </div>
                        <div class="bank-icon">
                            <?php echo strtoupper(substr($iban['bank_name'], 0, 1)); ?>
                        </div>
                        <div class="iban-details">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <h6><?php echo htmlspecialchars($iban['bank_name']); ?></h6>
                                <span class="status-badge <?php echo $iban['status']; ?>">
                                    <?php echo $iban['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </div>
                            <div class="iban-holder">
                                <i class="bi bi-person"></i>
                                <?php echo htmlspecialchars($iban['account_holder']); ?>
                            </div>
                            <div class="iban-number">
                                <?php echo htmlspecialchars(chunk_split($iban['iban'], 4, ' ')); ?>
                            </div>
                        </div>
                        <div class="iban-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="copyIban('<?php echo $iban['iban']; ?>')" title="Kopyala">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="iban_id" value="<?php echo $iban['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $iban['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" class="btn btn-sm btn-<?php echo $iban['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                        title="<?php echo $iban['status'] === 'active' ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                    <i class="bi bi-<?php echo $iban['status'] === 'active' ? 'pause' : 'play'; ?>-fill"></i>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Bu IBAN silinecek. Emin misiniz?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="iban_id" value="<?php echo $iban['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            <div class="text-center py-5" style="color: var(--text-muted);">
                <i class="bi bi-inbox" style="font-size: 64px; opacity: 0.2;"></i>
                <h5 class="mt-3" style="color: white;">Henüz IBAN eklenmemiş</h5>
                <p>Yukarıdaki formu kullanarak ilk IBAN'ınızı ekleyin</p>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function copyIban(iban) {
    navigator.clipboard.writeText(iban).then(() => {
        alert('IBAN kopyalandı: ' + iban);
    });
}

<?php if ($ibans): ?>
const ibanList = document.getElementById('ibanList');
new Sortable(ibanList, {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function(evt) {
        const ibanIds = Array.from(ibanList.children).map(el => el.dataset.ibanId);
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=update_order&orders=' + JSON.stringify(ibanIds)
        });
    }
});
<?php endif; ?>

document.querySelector('input[name="iban"]').addEventListener('input', function(e) {
    let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    e.target.value = value;
});
</script>

<?php require 'templates/footer.php'; ?>