<?php
$title = 'İBAN Yönetimi';
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
            case 'add':
                $bankName = trim($_POST['bank_name'] ?? '');
                $accountHolder = trim($_POST['account_holder'] ?? '');
                $iban = trim($_POST['iban'] ?? '');
                
                if ($bankName && $accountHolder && $iban) {
                    // IBAN formatını kontrol et
                    $iban = strtoupper(str_replace(' ', '', $iban));
                    
                    if (preg_match('/^TR\d{24}$/', $iban)) {
                        $db->query("
                            INSERT INTO rental_ibans 
                            (rental_id, bank_name, account_holder, iban, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ", [$rentalId, $bankName, $accountHolder, $iban]);
                        
                        $_SESSION['success'] = 'İBAN başarıyla eklendi!';
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
                
                $_SESSION['success'] = 'İBAN durumu güncellendi!';
                break;
                
            case 'delete':
                $ibanId = (int)($_POST['iban_id'] ?? 0);
                
                $db->query("
                    DELETE FROM rental_ibans 
                    WHERE id = ? AND rental_id = ?
                ", [$ibanId, $rentalId]);
                
                $_SESSION['success'] = 'İBAN silindi!';
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

// İBANları getir
$ibans = $db->fetchAll("
    SELECT * FROM rental_ibans 
    WHERE rental_id = ?
    ORDER BY display_order ASC, id DESC
", [$rentalId]);

require 'templates/header_new.php';
?>

<style>
.iban-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.iban-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
}

.iban-card.inactive {
    opacity: 0.6;
    background: rgba(0,0,0,0.02);
}

.iban-info {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 20px;
}

.bank-logo {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    font-weight: 700;
}

.iban-details h6 {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.iban-number {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    color: var(--text-muted);
    letter-spacing: 1px;
}

.iban-actions {
    display: flex;
    gap: 10px;
}

.drag-handle {
    cursor: move;
    color: var(--text-muted);
    padding: 5px;
}

.add-iban-form {
    background: var(--card-bg);
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.badge-active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-inactive {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<div class="mb-4">
    <a href="<?php echo Helper::url('rental/manage/' . $rentalId); ?>" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-2"></i>Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-bank me-2"></i>İBAN Yönetimi</h5>
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
        
        <!-- Yeni İBAN Ekleme Formu -->
        <div class="add-iban-form">
            <h6 class="mb-3"><i class="bi bi-plus-circle me-2"></i>Yeni İBAN Ekle</h6>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div>
                        <label class="form-label">Banka Adı</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="örn: Ziraat Bankası" required>
                    </div>
                    <div>
                        <label class="form-label">Hesap Sahibi</label>
                        <input type="text" name="account_holder" class="form-control" placeholder="Ad Soyad" required>
                    </div>
                    <div>
                        <label class="form-label">İBAN Numarası</label>
                        <input type="text" name="iban" class="form-control" placeholder="TR00 0000 0000 0000 0000 0000 00" 
                               pattern="^TR\d{24}$" maxlength="26" style="font-family: 'Courier New', monospace;" required>
                        <small class="text-muted">TR ile başlamalı, toplam 26 karakter</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-lg me-2"></i>İBAN Ekle
                </button>
            </form>
        </div>
        
        <!-- İBAN Listesi -->
        <?php if ($ibans): ?>
        <div class="mb-3">
            <h6><i class="bi bi-list-ul me-2"></i>Kayıtlı İBANlar (<?php echo count($ibans); ?>)</h6>
            <p class="text-muted" style="font-size: 14px;">
                <i class="bi bi-info-circle me-1"></i>
                Sıralamayı değiştirmek için kartları sürükleyin
            </p>
        </div>
        
        <div id="ibanList">
            <?php foreach ($ibans as $iban): ?>
            <div class="iban-card <?php echo $iban['status'] === 'inactive' ? 'inactive' : ''; ?>" data-iban-id="<?php echo $iban['id']; ?>">
                <div class="iban-info">
                    <div class="drag-handle">
                        <i class="bi bi-grip-vertical" style="font-size: 20px;"></i>
                    </div>
                    <div class="bank-logo">
                        <?php echo strtoupper(substr($iban['bank_name'], 0, 1)); ?>
                    </div>
                    <div class="iban-details">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <h6><?php echo htmlspecialchars($iban['bank_name']); ?></h6>
                            <span class="badge-<?php echo $iban['status']; ?>">
                                <?php echo $iban['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                            </span>
                        </div>
                        <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 3px;">
                            <?php echo htmlspecialchars($iban['account_holder']); ?>
                        </div>
                        <div class="iban-number">
                            <?php echo htmlspecialchars(chunk_split($iban['iban'], 4, ' ')); ?>
                        </div>
                    </div>
                    <div class="iban-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="iban_id" value="<?php echo $iban['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $iban['status'] === 'active' ? 'inactive' : 'active'; ?>">
                            <button type="submit" class="btn btn-sm btn-<?php echo $iban['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                    title="<?php echo $iban['status'] === 'active' ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                <i class="bi bi-<?php echo $iban['status'] === 'active' ? 'pause' : 'play'; ?>-fill"></i>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-primary" onclick="copyIban('<?php echo $iban['iban']; ?>')" title="Kopyala">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bu İBAN silinecek. Emin misiniz?');">
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
            <i class="bi bi-inbox" style="font-size: 48px; opacity: 0.3;"></i>
            <p class="mt-3">Henüz İBAN eklenmemiş</p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// IBAN Kopyalama
function copyIban(iban) {
    navigator.clipboard.writeText(iban).then(() => {
        alert('İBAN kopyalandı: ' + iban);
    });
}

// Sürükle bırak
<?php if ($ibans): ?>
const ibanList = document.getElementById('ibanList');
new Sortable(ibanList, {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function(evt) {
        // Yeni sıralamayı kaydet
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

// IBAN formatı otomatik düzenleme
document.querySelector('input[name="iban"]').addEventListener('input', function(e) {
    let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    e.target.value = value;
});
</script>

<?php require 'templates/footer_new.php'; ?>
