<?php
$title = 'Script Yönetimi';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Kategorileri çek
$categories = $db->fetchAll("SELECT id, name FROM script_categories WHERE status = 1 ORDER BY name");

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                try {
                    $slug = Helper::slug($_POST['name']);
                    $db->insert('scripts', [
                        'category_id' => $_POST['category_id'] ?: null,
                        'name' => $_POST['name'],
                        'slug' => $slug,
                        'description' => $_POST['description'] ?? '',
                        'image' => $_POST['image'] ?? null,
                        'status' => $_POST['status'] ?? 'active',
                        'ssh_host' => $_POST['ssh_host'] ?? null,
                        'ssh_port' => $_POST['ssh_port'] ?: 22,
                        'ssh_user' => $_POST['ssh_user'] ?? null,
                        'ssh_pass' => $_POST['ssh_pass'] ?? null,
                        'ssh_key_path' => $_POST['ssh_key_path'] ?? null,
                        'setup_command' => $_POST['setup_command'] ?? null,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Son ID'yi çek
                    $scriptId = $db->fetch("SELECT LAST_INSERT_ID() as id")['id'];
                    
                    // Paketleri ekle
                    if (!empty($_POST['packages']) && is_array($_POST['packages'])) {
                        foreach ($_POST['packages'] as $pkg) {
                            if (!empty($pkg['days']) && !empty($pkg['price'])) {
                                $db->insert('script_packages', [
                                    'script_id' => $scriptId,
                                    'duration_days' => intval($pkg['days']),
                                    'price_usdt' => floatval($pkg['price']),
                                    'is_default' => !empty($pkg['default']) ? 1 : 0
                                ]);
                            }
                        }
                    }
                    
                    Helper::flash('success', 'Script başarıyla eklendi');
                } catch (Exception $e) {
                    Helper::flash('error', 'Hata: ' . $e->getMessage());
                }
                break;
                
            case 'edit':
                $scriptId = intval($_POST['script_id'] ?? 0);
                if ($scriptId) {
                    try {
                        $db->update('scripts', [
                            'category_id' => $_POST['category_id'] ?: null,
                            'name' => $_POST['name'],
                            'slug' => Helper::slug($_POST['name']),
                            'description' => $_POST['description'] ?? '',
                            'image' => $_POST['image'] ?? null,
                            'status' => $_POST['status'] ?? 'active',
                            'ssh_host' => $_POST['ssh_host'] ?? null,
                            'ssh_port' => $_POST['ssh_port'] ?: 22,
                            'ssh_user' => $_POST['ssh_user'] ?? null,
                            'ssh_pass' => $_POST['ssh_pass'] ?? null,
                            'ssh_key_path' => $_POST['ssh_key_path'] ?? null,
                            'setup_command' => $_POST['setup_command'] ?? null
                        ], 'id = ?', [$scriptId]);
                        
                        Helper::flash('success', 'Script güncellendi');
                    } catch (Exception $e) {
                        Helper::flash('error', 'Güncelleme hatası: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'update_packages':
                $scriptId = intval($_POST['script_id'] ?? 0);
                if ($scriptId) {
                    // Mevcut paketleri sil
                    $db->query("DELETE FROM script_packages WHERE script_id = ?", [$scriptId]);
                    
                    // Yenilerini ekle
                    if (!empty($_POST['packages']) && is_array($_POST['packages'])) {
                        foreach ($_POST['packages'] as $pkg) {
                            if (!empty($pkg['days']) && !empty($pkg['price'])) {
                                $db->insert('script_packages', [
                                    'script_id' => $scriptId,
                                    'duration_days' => intval($pkg['days']),
                                    'price_usdt' => floatval($pkg['price']),
                                    'is_default' => !empty($pkg['default']) ? 1 : 0
                                ]);
                            }
                        }
                    }
                    Helper::flash('success', 'Paketler güncellendi');
                }
                break;
                
            case 'toggle_status':
                $scriptId = intval($_POST['script_id'] ?? 0);
                $script = $db->fetch("SELECT status FROM scripts WHERE id = ?", [$scriptId]);
                if ($script) {
                    $newStatus = $script['status'] === 'active' ? 'inactive' : 'active';
                    $db->update('scripts', ['status' => $newStatus], 'id = ?', [$scriptId]);
                    Helper::flash('success', 'Durum güncellendi');
                }
                break;
                
            case 'delete':
                $scriptId = intval($_POST['script_id'] ?? 0);
                // Kiralama varsa silme
                $rentalCount = $db->fetch("SELECT COUNT(*) as total FROM rentals WHERE script_id = ?", [$scriptId])['total'];
                if ($rentalCount > 0) {
                    Helper::flash('error', 'Bu scripte ait kiralamalar var, silinemez');
                } else {
                    $db->query("DELETE FROM script_packages WHERE script_id = ?", [$scriptId]);
                    $db->query("DELETE FROM script_domains WHERE script_id = ?", [$scriptId]);
                    $db->query("DELETE FROM scripts WHERE id = ?", [$scriptId]);
                    Helper::flash('success', 'Script silindi');
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Scriptleri çek
$scripts = $db->fetchAll("
    SELECT s.*, c.name as category_name,
        (SELECT COUNT(*) FROM rentals WHERE script_id = s.id AND status = 'active') as active_rentals,
        (SELECT COUNT(*) FROM script_domains WHERE script_id = s.id) as domain_count,
        (SELECT COUNT(*) FROM script_packages WHERE script_id = s.id) as package_count
    FROM scripts s
    LEFT JOIN script_categories c ON s.category_id = c.id
    ORDER BY s.created_at DESC
");

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h5 class="mb-0">Script Listesi</h5>
        <button class="btn-admin btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addScriptModal">
            <i class="bi bi-plus-lg"></i> Yeni Script Ekle
        </button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Script</th>
                    <th>Kategori</th>
                    <th>Paket</th>
                    <th>SSH</th>
                    <th>Kiralama</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scripts as $s): ?>
                <tr>
                    <td>#<?php echo $s['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($s['name'] ?? ''); ?></strong>
                        <div class="small text-muted"><?php echo htmlspecialchars($s['slug'] ?? ''); ?></div>
                    </td>
                    <td><?php echo $s['category_name'] ? htmlspecialchars($s['category_name']) : '<span class="text-muted">-</span>'; ?></td>
                    <td><span class="badge bg-info"><?php echo $s['package_count']; ?> paket</span></td>
                    <td>
                        <?php if ($s['ssh_host']): ?>
                            <span class="badge bg-success"><i class="bi bi-server"></i> <?php echo htmlspecialchars($s['ssh_host']); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Yok</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-primary"><?php echo $s['active_rentals']; ?> aktif</span></td>
                    <td>
                        <span class="badge bg-<?php echo $s['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo $s['status'] == 'active' ? 'Aktif' : 'Pasif'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $s['id']; ?>">
                                <i class="bi bi-pencil"></i> Düzenle
                            </button>
                            <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#packagesModal<?php echo $s['id']; ?>">
                                <i class="bi bi-box-seam"></i> Paketler
                            </button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="script_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-<?php echo $s['status'] == 'active' ? 'secondary' : 'success'; ?> me-1">
                                    <?php echo $s['status'] == 'active' ? 'Pasif' : 'Aktif'; ?>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Bu scripti silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="script_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" <?php echo $s['active_rentals'] > 0 ? 'disabled' : ''; ?>>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo $s['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Script Düzenle: <?php echo htmlspecialchars($s['name'] ?? ''); ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="script_id" value="<?php echo $s['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Script Adı *</label>
                                            <input type="text" name="name" class="form-control form-control-dark" value="<?php echo htmlspecialchars($s['name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Kategori</label>
                                            <select name="category_id" class="form-select form-control-dark">
                                                <option value="">Kategori Seçin</option>
                                                <?php foreach ($categories as $c): ?>
                                                <option value="<?php echo $c['id']; ?>" <?php echo $s['category_id'] == $c['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($c['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Açıklama</label>
                                        <textarea name="description" class="form-control form-control-dark" rows="3"><?php echo htmlspecialchars($s['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Görsel URL</label>
                                        <input type="text" name="image" class="form-control form-control-dark" value="<?php echo htmlspecialchars($s['image'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Durum</label>
                                        <select name="status" class="form-select form-control-dark">
                                            <option value="active" <?php echo $s['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="inactive" <?php echo $s['status'] == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                            <option value="maintenance" <?php echo $s['status'] == 'maintenance' ? 'selected' : ''; ?>>Bakımda</option>
                                        </select>
                                    </div>
                                    
                                    <hr class="border-secondary my-4">
                                    <h6 class="text-warning mb-3"><i class="bi bi-server"></i> SSH Bağlantı Ayarları</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SSH Host</label>
                                            <input type="text" name="ssh_host" class="form-control form-control-dark" value="<?php echo htmlspecialchars($s['ssh_host'] ?? ''); ?>" placeholder="örn: 192.168.1.100 veya sunucu.com">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SSH Port</label>
                                            <input type="number" name="ssh_port" class="form-control form-control-dark" value="<?php echo $s['ssh_port'] ?? 22; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SSH Kullanıcı</label>
                                            <input type="text" name="ssh_user" class="form-control form-control-dark" value="<?php echo htmlspecialchars($s['ssh_user'] ?? ''); ?>" placeholder="root">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SSH Şifre (veya Key kullanın)</label>
                                            <input type="password" name="ssh_pass" class="form-control form-control-dark" value="<?php echo htmlspecialchars($s['ssh_pass'] ?? ''); ?>" placeholder="Değiştirmek için yazın">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">SSH Key Path (Önerilen)</label>
                                        <input type="text" name="ssh_key_path" class="form-control form-control-dark" value="<?php echo htmlspecialchars($s['ssh_key_path'] ?? ''); ?>" placeholder="/home/user/.ssh/id_rsa">
                                        <div class="form-text text-muted">Şifre yerine SSH key kullanmak daha güvenlidir</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kurulum Komutu</label>
                                        <textarea name="setup_command" class="form-control form-control-dark" rows="3" placeholder="cd /var/www && git pull origin master"><?php echo htmlspecialchars($s['setup_command'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-primary">Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Packages Modal -->
                <div class="modal fade" id="packagesModal<?php echo $s['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Paket Yönetimi: <?php echo htmlspecialchars($s['name'] ?? ''); ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="update_packages">
                                    <input type="hidden" name="script_id" value="<?php echo $s['id']; ?>">
                                    
                                    <?php
                                    $packages = $db->fetchAll("SELECT * FROM script_packages WHERE script_id = ? ORDER BY duration_days", [$s['id']]);
                                    if (empty($packages)) $packages = [['duration_days' => 30, 'price_usdt' => 0, 'is_default' => 1]];
                                    ?>
                                    
                                    <div id="packageContainer<?php echo $s['id']; ?>">
                                        <?php foreach ($packages as $i => $pkg): ?>
                                        <div class="row package-row mb-3">
                                            <div class="col-4">
                                                <input type="number" name="packages[<?php echo $i; ?>][days]" class="form-control form-control-dark" value="<?php echo $pkg['duration_days']; ?>" placeholder="Gün" required>
                                            </div>
                                            <div class="col-4">
                                                <input type="number" name="packages[<?php echo $i; ?>][price]" class="form-control form-control-dark" step="0.01" value="<?php echo $pkg['price_usdt']; ?>" placeholder="Fiyat" required>
                                            </div>
                                            <div class="col-3">
                                                <div class="form-check mt-2">
                                                    <input type="checkbox" name="packages[<?php echo $i; ?>][default]" class="form-check-input" value="1" <?php echo $pkg['is_default'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label text-white-50">Varsayılan</label>
                                                </div>
                                            </div>
                                            <div class="col-1">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.package-row').remove()">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-sm btn-success w-100" onclick="addPackage<?php echo $s['id']; ?>()">
                                        <i class="bi bi-plus-lg"></i> Paket Ekle
                                    </button>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-warning">Paketleri Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <script>
                function addPackage<?php echo $s['id']; ?>() {
                    const container = document.getElementById('packageContainer<?php echo $s['id']; ?>');
                    const index = container.children.length;
                    const html = `
                        <div class="row package-row mb-3">
                            <div class="col-4">
                                <input type="number" name="packages[${index}][days]" class="form-control form-control-dark" placeholder="Gün" required>
                            </div>
                            <div class="col-4">
                                <input type="number" name="packages[${index}][price]" class="form-control form-control-dark" step="0.01" placeholder="Fiyat" required>
                            </div>
                            <div class="col-3">
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="packages[${index}][default]" class="form-check-input" value="1">
                                    <label class="form-check-label text-white-50">Varsayılan</label>
                                </div>
                            </div>
                            <div class="col-1">
                                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.package-row').remove()">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', html);
                }
                </script>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Script Modal -->
<div class="modal fade" id="addScriptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Script Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Script Adı *</label>
                            <input type="text" name="name" class="form-control form-control-dark" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" class="form-select form-control-dark">
                                <option value="">Kategori Seçin</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control form-control-dark" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Görsel URL</label>
                        <input type="text" name="image" class="form-control form-control-dark" placeholder="https://...">
                    </div>
                    
                    <hr class="border-secondary my-4">
                    <h6 class="text-warning mb-3"><i class="bi bi-server"></i> SSH Bağlantı Ayarları</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SSH Host</label>
                            <input type="text" name="ssh_host" class="form-control form-control-dark" placeholder="örn: 192.168.1.100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SSH Port</label>
                            <input type="number" name="ssh_port" class="form-control form-control-dark" value="22">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SSH Kullanıcı</label>
                            <input type="text" name="ssh_user" class="form-control form-control-dark" placeholder="root">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SSH Şifre</label>
                            <input type="password" name="ssh_pass" class="form-control form-control-dark">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">SSH Key Path</label>
                        <input type="text" name="ssh_key_path" class="form-control form-control-dark" placeholder="/home/user/.ssh/id_rsa">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kurulum Komutu</label>
                        <textarea name="setup_command" class="form-control form-control-dark" rows="3" placeholder="cd /var/www && git pull origin master"></textarea>
                    </div>
                    
                    <hr class="border-secondary my-4">
                    <h6 class="text-info mb-3"><i class="bi bi-box-seam"></i> Paketler (Fiyatlandırma)</h6>
                    
                    <div id="newPackageContainer">
                        <div class="row package-row mb-3">
                            <div class="col-4">
                                <input type="number" name="packages[0][days]" class="form-control form-control-dark" placeholder="Gün (örn: 30)" required>
                            </div>
                            <div class="col-4">
                                <input type="number" name="packages[0][price]" class="form-control form-control-dark" step="0.01" placeholder="Fiyat (USDT)" required>
                            </div>
                            <div class="col-3">
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="packages[0][default]" class="form-check-input" value="1" checked>
                                    <label class="form-check-label text-white-50">Varsayılan</label>
                                </div>
                            </div>
                            <div class="col-1">
                                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.package-row').remove()">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-success w-100 mb-3" onclick="addNewPackage()">
                        <i class="bi bi-plus-lg"></i> Paket Ekle
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Script Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addNewPackage() {
    const container = document.getElementById('newPackageContainer');
    const index = container.children.length;
    const html = `
        <div class="row package-row mb-3">
            <div class="col-4">
                <input type="number" name="packages[${index}][days]" class="form-control form-control-dark" placeholder="Gün" required>
            </div>
            <div class="col-4">
                <input type="number" name="packages[${index}][price]" class="form-control form-control-dark" step="0.01" placeholder="Fiyat" required>
            </div>
            <div class="col-3">
                <div class="form-check mt-2">
                    <input type="checkbox" name="packages[${index}][default]" class="form-check-input" value="1">
                    <label class="form-check-label text-white-50">Varsayılan</label>
                </div>
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.package-row').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
</script>

<?php require 'templates/footer.php'; ?>