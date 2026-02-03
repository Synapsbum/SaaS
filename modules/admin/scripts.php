<?php
$title = 'Script Yönetimi';
$db = Database::getInstance();

// Script ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        // Fotoğraf yükleme
        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../assets/images/scripts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $targetFile = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imagePath = 'assets/images/scripts/' . $filename;
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
        $packages = [
            ['days' => 1, 'price' => $_POST['price_1day']],
            ['days' => 3, 'price' => $_POST['price_3day']],
            ['days' => 7, 'price' => $_POST['price_7day']]
        ];
        
        foreach ($packages as $pkg) {
            if ($pkg['price'] > 0) {
                $db->insert('script_packages', [
                    'script_id' => $scriptId,
                    'duration_days' => $pkg['days'],
                    'price_usdt' => $pkg['price']
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
        
        // Yeni fotoğraf varsa
        $updateData = [
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'ssh_host' => $_POST['ssh_host'],
            'ssh_user' => $_POST['ssh_user'],
            'ssh_pass' => $_POST['ssh_pass'],
            'setup_command' => $_POST['setup_command'],
            'status' => $_POST['status']
        ];
        
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../assets/images/scripts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $targetFile = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                // Eski fotoğrafı sil
                $oldScript = $db->fetch("SELECT image FROM scripts WHERE id = ?", [$scriptId]);
                if ($oldScript['image'] && file_exists(__DIR__ . '/../../' . $oldScript['image'])) {
                    unlink(__DIR__ . '/../../' . $oldScript['image']);
                }
                $updateData['image'] = 'assets/images/scripts/' . $filename;
            }
        }
        
        $db->update('scripts', $updateData, 'id = ?', [$scriptId]);
        
        // Paket fiyatlarını güncelle
        $db->query("DELETE FROM script_packages WHERE script_id = ?", [$scriptId]);
        
        $packages = [
            ['days' => 1, 'price' => $_POST['price_1day']],
            ['days' => 3, 'price' => $_POST['price_3day']],
            ['days' => 7, 'price' => $_POST['price_7day']]
        ];
        
        foreach ($packages as $pkg) {
            if ($pkg['price'] > 0) {
                $db->insert('script_packages', [
                    'script_id' => $scriptId,
                    'duration_days' => $pkg['days'],
                    'price_usdt' => $pkg['price']
                ]);
            }
        }
        
        Helper::flash('success', 'Script güncellendi');
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Script silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $scriptId = $_POST['script_id'];
    
    // Fotoğrafı sil
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

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6>Yeni Script Ekle</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-2">
                        <label class="form-label">Script Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Fotoğraf</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">SSH Host</label>
                        <input type="text" name="ssh_host" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">SSH Kullanıcı</label>
                        <input type="text" name="ssh_user" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">SSH Şifre</label>
                        <input type="password" name="ssh_pass" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Kurulum Komutu</label>
                        <textarea name="setup_command" class="form-control" rows="2" placeholder="{DOMAIN} {USER_ID} {DURATION}"></textarea>
                    </div>
                    
                    <h6 class="mt-3">Fiyatlar (USDT)</h6>
                    <div class="row">
                        <div class="col-4">
                            <input type="number" name="price_1day" class="form-control" placeholder="1 Gün" step="0.01">
                        </div>
                        <div class="col-4">
                            <input type="number" name="price_3day" class="form-control" placeholder="3 Gün" step="0.01">
                        </div>
                        <div class="col-4">
                            <input type="number" name="price_7day" class="form-control" placeholder="7 Gün" step="0.01">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mt-3">Ekle</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6>Scriptler</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Ad</th>
                            <th>Durum</th>
                            <th>SSH</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scripts as $s): 
                            $packages = $db->fetchAll("SELECT * FROM script_packages WHERE script_id = ?", [$s['id']]);
                        ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td>
                                <?php if ($s['image']): ?>
                                <img src="<?php echo Helper::url($s['image']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                <div class="bg-secondary" style="width: 50px; height: 50px; border-radius: 4px;"></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $s['name']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $s['status'] == 'active' ? 'success' : ($s['status'] == 'maintenance' ? 'warning' : 'secondary'); ?>">
                                    <?php echo $s['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $s['ssh_host'] ?: '-'; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $s['id']; ?>">Düzenle</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="script_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Düzenle Modal -->
                        <div class="modal fade" id="editModal<?php echo $s['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content bg-dark">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Script Düzenle</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="script_id" value="<?php echo $s['id']; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Script Adı</label>
                                                        <input type="text" name="name" class="form-control" value="<?php echo $s['name']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Açıklama</label>
                                                        <textarea name="description" class="form-control" rows="2"><?php echo $s['description']; ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Yeni Fotoğraf (Boş bırakılırsa değişmez)</label>
                                                        <input type="file" name="image" class="form-control" accept="image/*">
                                                        <?php if ($s['image']): ?>
                                                        <small class="text-muted">Mevcut: <a href="<?php echo Helper::url($s['image']); ?>" target="_blank">Görüntüle</a></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Durum</label>
                                                        <select name="status" class="form-control">
                                                            <option value="active" <?php echo $s['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                                            <option value="inactive" <?php echo $s['status'] == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                                            <option value="maintenance" <?php echo $s['status'] == 'maintenance' ? 'selected' : ''; ?>>Bakım</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">SSH Host</label>
                                                        <input type="text" name="ssh_host" class="form-control" value="<?php echo $s['ssh_host']; ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">SSH Kullanıcı</label>
                                                        <input type="text" name="ssh_user" class="form-control" value="<?php echo $s['ssh_user']; ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">SSH Şifre (Boş bırakılırsa değişmez)</label>
                                                        <input type="password" name="ssh_pass" class="form-control" placeholder="Değiştirmek için yazın">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Kurulum Komutu</label>
                                                        <textarea name="setup_command" class="form-control" rows="2"><?php echo $s['setup_command']; ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <h6 class="mt-3">Paket Fiyatları (USDT)</h6>
                                            <div class="row">
                                                <?php 
                                                $prices = [1 => 0, 3 => 0, 7 => 0];
                                                foreach ($packages as $p) {
                                                    $prices[$p['duration_days']] = $p['price_usdt'];
                                                }
                                                ?>
                                                <div class="col-4">
                                                    <label>1 Gün</label>
                                                    <input type="number" name="price_1day" class="form-control" value="<?php echo $prices[1]; ?>" step="0.01">
                                                </div>
                                                <div class="col-4">
                                                    <label>3 Gün</label>
                                                    <input type="number" name="price_3day" class="form-control" value="<?php echo $prices[3]; ?>" step="0.01">
                                                </div>
                                                <div class="col-4">
                                                    <label>7 Gün</label>
                                                    <input type="number" name="price_7day" class="form-control" value="<?php echo $prices[7]; ?>" step="0.01">
                                                </div>
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>