<?php
$title = 'Domain Yönetimi';
$db = Database::getInstance();

// CSRF token
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

$scripts = $db->fetchAll("SELECT id, name FROM scripts WHERE status = 'active'");

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        Helper::flash('error', 'Güvenlik hatası');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $db->insert('script_domains', [
                    'script_id' => $_POST['script_id'],
                    'domain' => $_POST['domain'],
                    'status' => 'available'
                ]);
                Helper::flash('success', 'Domain eklendi');
                break;
                
            case 'edit':
                $db->update('script_domains', [
                    'domain' => $_POST['domain'],
                    'script_id' => $_POST['script_id']
                ], 'id = ?', [$_POST['domain_id']]);
                Helper::flash('success', 'Domain güncellendi');
                break;
                
            case 'toggle_status':
                $domain = $db->fetch("SELECT status FROM script_domains WHERE id = ?", [$_POST['domain_id']]);
                if ($domain) {
                    $newStatus = $domain['status'] === 'available' ? 'maintenance' : 'available';
                    $db->update('script_domains', ['status' => $newStatus], 'id = ?', [$_POST['domain_id']]);
                    Helper::flash('success', 'Durum güncellendi');
                }
                break;
                
            case 'delete':
                $db->query("DELETE FROM script_domains WHERE id = ?", [$_POST['domain_id']]);
                Helper::flash('success', 'Domain silindi');
                break;
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

require dirname(__FILE__) . '/templates/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Yeni Domain Ekle</h5>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label text-white-50">Script</label>
                        <select name="script_id" class="form-select form-control-dark" required>
                            <?php foreach ($scripts as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white-50">Domain</label>
                        <input type="text" name="domain" class="form-control form-control-dark" placeholder="ornek.com" required>
                    </div>
                    <button type="submit" class="btn-admin btn-primary-custom w-100">
                        <i class="bi bi-plus-lg"></i> Ekle
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="mb-0">Domain Havuzu</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
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
                            <td>#<?php echo $d['id']; ?></td>
                            <td>
                                <code class="text-info fs-6"><?php echo htmlspecialchars($d['domain']); ?></code>
                            </td>
                            <td><?php echo htmlspecialchars($d['script_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $d['status'] == 'available' ? 'success' : 
                                         ($d['status'] == 'in_use' ? 'warning' : 'info'); 
                                ?>">
                                    <?php echo $d['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $d['assigned_user'] ?: '-'; ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($d['status'] != 'in_use'): ?>
                                    <form method="POST" class="d-inline me-1">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="domain_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <?php echo $d['status'] == 'available' ? 'Bakıma Al' : 'Aktif Et'; ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $d['id']; ?>">
                                        Düzenle
                                    </button>
                                    
                                    <?php if ($d['status'] != 'in_use'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="domain_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?php echo $d['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
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
                                                <input type="text" name="domain" class="form-control form-control-dark" value="<?php echo htmlspecialchars($d['domain']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Script</label>
                                                <select name="script_id" class="form-select form-control-dark" required>
                                                    <?php foreach ($scripts as $s): ?>
                                                    <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $d['script_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($s['name']); ?>
                                                    </option>
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
                        
                        <?php if (empty($domains)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                Henüz domain eklenmemiş
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>