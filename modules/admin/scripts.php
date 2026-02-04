<?php
$title = 'Script Yönetimi';
$db = Database::getInstance();

// Script ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../uploads/scripts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $targetFile = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imagePath = 'uploads/scripts/' . $filename;
            }
        }
        
        $scriptId = $db->insert('scripts', [
            'name' => $_POST['name'],
            'slug' => strtolower(preg_replace('/[^a-z0-9]/', '-', $_POST['name'])),
            'description' => $_POST['description'],
            'image' => $imagePath,
            'ssh_host' => $_POST['ssh_host'],
            'ssh_user' => $_POST['ssh_user'],
            'ssh_pass' => $_POST['ssh_pass'],
            'setup_command' => $_POST['setup_command'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Paketleri ekle
        foreach ([1 => 'price_1day', 3 => 'price_3day', 7 => 'price_7day', 30 => 'price_30day'] as $days => $field) {
            if (!empty($_POST[$field]) && $_POST[$field] > 0) {
                $db->insert('script_packages', [
                    'script_id' => $scriptId,
                    'duration_days' => $days,
                    'price_usdt' => $_POST[$field]
                ]);
            }
        }
        
        Helper::flash('success', 'Script eklendi');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Script güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $scriptId = $_POST['script_id'];
        
        $updateData = [
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'ssh_host' => $_POST['ssh_host'],
            'ssh_user' => $_POST['ssh_user'],
            'ssh_pass' => $_POST['ssh_pass'] ?: null, // Boşsa değiştirme
            'setup_command' => $_POST['setup_command'],
            'status' => $_POST['status']
        ];
        
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../uploads/scripts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $targetFile = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $oldScript = $db->fetch("SELECT image FROM scripts WHERE id = ?", [$scriptId]);
                if ($oldScript['image'] && file_exists(__DIR__ . '/../../' . $oldScript['image'])) {
                    unlink(__DIR__ . '/../../' . $oldScript['image']);
                }
                $updateData['image'] = 'uploads/scripts/' . $filename;
            }
        }
        
        $db->update('scripts', $updateData, 'id = ?', [$scriptId]);
        
        // Paketleri güncelle
        $db->query("DELETE FROM script_packages WHERE script_id = ?", [$scriptId]);
        foreach ([1 => 'price_1day', 3 => 'price_3day', 7 => 'price_7day', 30 => 'price_30day'] as $days => $field) {
            if (!empty($_POST[$field]) && $_POST[$field] > 0) {
                $db->insert('script_packages', [
                    'script_id' => $scriptId,
                    'duration_days' => $days,
                    'price_usdt' => $_POST[$field]
                ]);
            }
        }
        
        Helper::flash('success', 'Script güncellendi');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Domain işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_domain') {
    $db->insert('script_domains', [
        'script_id' => $_POST['script_id'],
        'domain' => $_POST['domain'],
        'status' => 'available'
    ]);
    Helper::flash('success', 'Domain eklendi');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_domain') {
    $db->query("DELETE FROM script_domains WHERE id = ? AND status != 'in_use'", [$_POST['domain_id']]);
    Helper::flash('success', 'Domain silindi');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Script silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $scriptId = $_POST['script_id'];
    $script = $db->fetch("SELECT image FROM scripts WHERE id = ?", [$scriptId]);
    if ($script['image'] && file_exists(__DIR__ . '/../../' . $script['image'])) {
        unlink(__DIR__ . '/../../' . $script['image']);
    }
    $db->query("DELETE FROM script_packages WHERE script_id = ?", [$scriptId]);
    $db->query("DELETE FROM script_domains WHERE script_id = ?", [$scriptId]);
    $db->query("DELETE FROM scripts WHERE id = ?", [$scriptId]);
    Helper::flash('success', 'Script silindi');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$scripts = $db->fetchAll("SELECT * FROM scripts ORDER BY created_at DESC");

require 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0"><i class="bi bi-box-seam me-2"></i>Script Yönetimi</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScriptModal">
            <i class="bi bi-plus-lg me-2"></i>Yeni Script
        </button>
    </div>

    <div class="row g-4">
        <?php foreach ($scripts as $script): 
            $packages = $db->fetchAll("SELECT * FROM script_packages WHERE script_id = ? ORDER BY duration_days", [$script['id']]);
            $domains = $db->fetchAll("SELECT * FROM script_domains WHERE script_id = ? ORDER BY status, domain", [$script['id']]);
            $packagePrices = array_column($packages, 'price_usdt', 'duration_days');
        ?>
        <div class="col-12">
            <div class="card bg-dark border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <?php if ($script['image']): ?>
                            <img src="<?php echo Helper::url($script['image']); ?>" class="img-fluid rounded" style="max-height: 100px;">
                            <?php else: ?>
                            <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center" style="height: 100px;">
                                <i class="bi bi-image text-muted fs-1"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <h5 class="text-white mb-1"><?php echo $script['name']; ?></h5>
                            <span class="badge bg-<?php echo $script['status'] == 'active' ? 'success' : ($script['status'] == 'maintenance' ? 'warning' : 'secondary'); ?> mb-2">
                                <?php echo $script['status']; ?>
                            </span>
                            <p class="text-muted small mb-0"><?php echo Helper::excerpt($script['description'], 100); ?></p>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="bg-black bg-opacity-50 rounded p-2 mb-2">
                                <small class="text-muted d-block">Fiyatlar (USDT):</small>
                                <div class="d-flex gap-2 flex-wrap mt-1">
                                    <?php foreach ([1, 3, 7, 30] as $day): ?>
                                    <?php if (isset($packagePrices[$day])): ?>
                                    <span class="badge bg-primary"><?php echo $day; ?>g: <?php echo $packagePrices[$day]; ?></span>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="bg-black bg-opacity-50 rounded p-2">
                                <small class="text-muted d-block">Domainler:</small>
                                <span class="badge bg-info"><?php echo count($domains); ?> adet</span>
                            </div>
                        </div>
                        
                        <div class="col-md-3 text-end">
                            <button class="btn btn-outline-info btn-sm mb-2 w-100" data-bs-toggle="modal" data-bs-target="#domainsModal<?php echo $script['id']; ?>">
                                <i class="bi bi-globe me-1"></i>Domainler
                            </button>
                            <button class="btn btn-outline-primary btn-sm mb-2 w-100" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $script['id']; ?>">
                                <i class="bi bi-pencil me-1"></i>Düzenle
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Script ve tüm verileri silinecek. Emin misiniz?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="script_id" value="<?php echo $script['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="bi bi-trash me-1"></i>Sil
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain Yönetimi Modal -->
        <div class="modal fade" id="domainsModal<?php echo $script['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-white"><?php echo $script['name']; ?> - Domain Yönetimi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Domain Ekle -->
                        <form method="POST" class="row g-2 mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="add_domain">
                            <input type="hidden" name="script_id" value="<?php echo $script['id']; ?>">
                            <div class="col-md-8">
                                <input type="text" name="domain" class="form-control bg-dark text-white border-secondary" placeholder="ornek.com" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-lg me-1"></i>Domain Ekle</button>
                            </div>
                        </form>
                        
                        <!-- Domain Listesi -->
                        <div class="table-responsive">
                            <table class="table table-dark table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>Durum</th>
                                        <th>Kullanıcı</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($domains as $domain): 
                                        $domainUser = $domain['current_user_id'] ? $db->fetch("SELECT username FROM users WHERE id = ?", [$domain['current_user_id']]) : null;
                                    ?>
                                    <tr>
                                        <td><code class="text-info"><?php echo $domain['domain']; ?></code></td>
                                        <td>
                                            <span class="badge bg-<?php echo $domain['status'] == 'available' ? 'success' : ($domain['status'] == 'in_use' ? 'warning' : 'danger'); ?>">
                                                <?php echo $domain['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $domainUser['username'] ?? '-'; ?></td>
                                        <td>
                                            <?php if ($domain['status'] != 'in_use'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Domain silinecek?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="delete_domain">
                                                <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled><i class="bi bi-lock"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($domains)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">Henüz domain eklenmemiş</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Düzenle Modal -->
        <div class="modal fade" id="editModal<?php echo $script['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content bg-dark">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-white">Script Düzenle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="script_id" value="<?php echo $script['id']; ?>">
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-white">Script Adı</label>
                                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" value="<?php echo $script['name']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-white">Açıklama</label>
                                        <textarea name="description" class="form-control bg-dark text-white border-secondary" rows="3"><?php echo $script['description']; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-white">Yeni Fotoğraf (Boş = Değiştirme)</label>
                                        <input type="file" name="image" class="form-control bg-dark text-white border-secondary" accept="image/*">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-white">Durum</label>
                                        <select name="status" class="form-select bg-dark text-white border-secondary">
                                            <option value="active" <?php echo $script['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="inactive" <?php echo $script['status'] == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                            <option value="maintenance" <?php echo $script['status'] == 'maintenance' ? 'selected' : ''; ?>>Bakım</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-white">SSH Host</label>
                                        <input type="text" name="ssh_host" class="form-control bg-dark text-white border-secondary" value="<?php echo $script['ssh_host']; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-white">SSH Kullanıcı</label>
                                        <input type="text" name="ssh_user" class="form-control bg-dark text-white border-secondary" value="<?php echo $script['ssh_user']; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-white">SSH Şifre (Boş = Değiştirme)</label>
                                        <input type="password" name="ssh_pass" class="form-control bg-dark text-white border-secondary" placeholder="••••••">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-white">Kurulum Komutu</label>
                                        <textarea name="setup_command" class="form-control bg-dark text-white border-secondary font-monospace small" rows="2"><?php echo $script['setup_command']; ?></textarea>
                                        <small class="text-muted">{DOMAIN} {USER_ID} {DURATION}</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="border-secondary">
                            
                            <h6 class="text-white mb-3">Paket Fiyatları (USDT)</h6>
                            <div class="row g-3">
                                <?php foreach ([1 => '1 Gün', 3 => '3 Gün', 7 => '7 Gün', 30 => '30 Gün'] as $day => $label): ?>
                                <div class="col-md-3">
                                    <label class="form-label text-muted small"><?php echo $label; ?></label>
                                    <input type="number" name="price_<?php echo $day; ?>day" class="form-control bg-dark text-white border-secondary" 
                                           value="<?php echo $packagePrices[$day] ?? ''; ?>" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Yeni Script Modal -->
<div class="modal fade" id="addScriptModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Yeni Script Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-white">Script Adı <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white">Açıklama</label>
                                <textarea name="description" class="form-control bg-dark text-white border-secondary" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white">Fotoğraf</label>
                                <input type="file" name="image" class="form-control bg-dark text-white border-secondary" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-white">SSH Host</label>
                                <input type="text" name="ssh_host" class="form-control bg-dark text-white border-secondary">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white">SSH Kullanıcı</label>
                                <input type="text" name="ssh_user" class="form-control bg-dark text-white border-secondary">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white">SSH Şifre</label>
                                <input type="password" name="ssh_pass" class="form-control bg-dark text-white border-secondary">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white">Kurulum Komutu</label>
                                <textarea name="setup_command" class="form-control bg-dark text-white border-secondary font-monospace small" rows="2" placeholder="{DOMAIN} {USER_ID} {DURATION}"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="border-secondary">
                    
                    <h6 class="text-white mb-3">Paket Fiyatları (USDT) <span class="text-muted small">- En az birini doldurun</span></h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small">1 Gün</label>
                            <input type="number" name="price_1day" class="form-control bg-dark text-white border-secondary" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">3 Gün</label>
                            <input type="number" name="price_3day" class="form-control bg-dark text-white border-secondary" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">7 Gün</label>
                            <input type="number" name="price_7day" class="form-control bg-dark text-white border-secondary" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">30 Gün</label>
                            <input type="number" name="price_30day" class="form-control bg-dark text-white border-secondary" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>