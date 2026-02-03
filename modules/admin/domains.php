<?php
$title = 'Domain Yönetimi';
$db = Database::getInstance();

$scripts = $db->fetchAll("SELECT id, name FROM scripts WHERE status = 'active'");

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateToken($_POST['csrf_token'] ?? '')) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        // Domain ekle
        if ($_POST['action'] === 'add') {
            $db->insert('script_domains', [
                'script_id' => $_POST['script_id'],
                'domain' => $_POST['domain'],
                'status' => 'available'
            ]);
            Helper::flash('success', 'Domain eklendi');
        }
        
        // Domain sil
        if ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM script_domains WHERE id = ?", [$_POST['domain_id']]);
            Helper::flash('success', 'Domain silindi');
        }
        
        // Durum değiştir
        if ($_POST['action'] === 'toggle_status') {
            $domain = $db->fetch("SELECT status FROM script_domains WHERE id = ?", [$_POST['domain_id']]);
            $newStatus = $domain['status'] === 'available' ? 'maintenance' : 'available';
            $db->update('script_domains', ['status' => $newStatus], 'id = ?', [$_POST['domain_id']]);
            Helper::flash('success', 'Durum güncellendi');
        }
        
        // Domain düzenle
        if ($_POST['action'] === 'edit') {
            $db->update('script_domains', [
                'domain' => $_POST['domain'],
                'script_id' => $_POST['script_id']
            ], 'id = ?', [$_POST['domain_id']]);
            Helper::flash('success', 'Domain güncellendi');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Domainleri çek
$domains = $db->fetchAll("
    SELECT d.*, s.name as script_name, u.username as assigned_user
    FROM script_domains d 
    JOIN scripts s ON d.script_id = s.id 
    LEFT JOIN users u ON d.current_user_id = u.id
    ORDER BY d.id DESC
");

require 'templates/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6>Yeni Domain Ekle</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Script</label>
                        <select name="script_id" class="form-control" required>
                            <?php foreach ($scripts as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Domain</label>
                        <input type="text" name="domain" class="form-control" placeholder="ornek.com" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ekle</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6>Domain Havuzu</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Domain</th>
                            <th>Script</th>
                            <th>Durum</th>
                            <th>Kullanıcı</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $d): ?>
                        <tr>
                            <td><?php echo $d['id']; ?></td>
                            <td><?php echo $d['domain']; ?></td>
                            <td><?php echo $d['script_name']; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $d['status'] == 'available' ? 'success' : 
                                         ($d['status'] == 'in_use' ? 'warning' : 
                                         ($d['status'] == 'maintenance' ? 'info' : 'danger')); 
                                ?>"><?php echo $d['status']; ?></span>
                            </td>
                            <td><?php echo $d['assigned_user'] ?: '-'; ?></td>
                            <td>
                                <!-- Durum Değiştir -->
                                <?php if ($d['status'] != 'in_use'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="domain_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <?php echo $d['status'] == 'available' ? 'Bakıma Al' : 'Aktif Et'; ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <!-- Düzenle Modal Butonu -->
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $d['id']; ?>">
                                    Düzenle
                                </button>
                                
                                <!-- Sil -->
                                <?php if ($d['status'] != 'in_use'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="domain_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- Düzenle Modal -->
                        <div class="modal fade" id="editModal<?php echo $d['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content bg-dark">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Domain Düzenle</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="domain_id" value="<?php echo $d['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Domain</label>
                                                <input type="text" name="domain" class="form-control" value="<?php echo $d['domain']; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Script</label>
                                                <select name="script_id" class="form-control" required>
                                                    <?php foreach ($scripts as $s): ?>
                                                    <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $d['script_id'] ? 'selected' : ''; ?>><?php echo $s['name']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
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