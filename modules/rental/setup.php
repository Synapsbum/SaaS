<?php
$title = 'Kurulum';
$user = $auth->user();
$db = Database::getInstance();

$rentalId = $id ?? 0;
$rental = $db->fetch("
    SELECT r.*, s.name AS script_name
    FROM rentals r
    JOIN scripts s ON r.script_id = s.id
    WHERE r.id = ? AND r.user_id = ?
", [$rentalId, $user['id']]);

if (!$rental) die('Yetkisiz');

/* ================= CSRF ================= */
$csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';

/* ================= STEP ================= */
$step = 1;
if ($rental['status'] === 'setup_config')  $step = 2;
if ($rental['status'] === 'setup_deploy')  $step = 3;

/* ================= DOMAINS ================= */
$domains = $db->fetchAll(
    "SELECT * FROM script_domains WHERE script_id=? AND status='available'",
    [$rental['script_id']]
);

require 'templates/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-8">
<div class="admin-card">
<div class="admin-card-header">
<h5><?php echo htmlspecialchars($rental['script_name']); ?> – Kurulum (Adım <?php echo $step; ?>/3)</h5>
</div>

<div class="admin-card-body">

<div class="progress mb-4" style="height:10px;">
<div class="progress-bar bg-success" style="width: <?php echo ($step/3)*100; ?>%"></div>
</div>

<?php if ($step == 1): ?>

<h6>1. Domain Seçimi</h6>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
<?php foreach ($domains as $d): ?>
<label class="d-block mb-2">
<input type="radio" name="domain_id" value="<?php echo (int)$d['id']; ?>" required>
<?php echo htmlspecialchars($d['domain']); ?>
</label>
<?php endforeach; ?>
<button class="btn btn-primary w-100">Devam Et</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['domain_id'])) {
    $db->query(
        "UPDATE script_domains SET status='in_use', current_user_id=? WHERE id=?",
        [$user['id'], (int)$_POST['domain_id']]
    );
    $db->query(
        "UPDATE rentals SET status='setup_config', setup_data=? WHERE id=?",
        [json_encode(['domain'=>$domains[array_key_first($domains)]['domain']]), $rentalId]
    );
    header("Refresh:0");
}
?>

<?php elseif ($step == 2): ?>

<h6>2. Sistem Hazırlanıyor</h6>
<p class="text-muted">Altyapı kontrolleri tamamlandı. Kuruluma geçebilirsiniz.</p>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
<button class="btn btn-primary w-100">Devam Et</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $db->query("UPDATE rentals SET status='setup_deploy' WHERE id=?", [$rentalId]);
    header("Refresh:0");
}
?>

<?php else: ?>

<h6>3. Kurulum (Canlı)</h6>

<div class="alert alert-info">
Kurulum aşamaları anlık olarak görüntülenecektir.
</div>

<pre id="liveLog" class="bg-dark text-light p-3"
     style="height:260px; overflow:auto;"></pre>

<button id="startDeploy" class="btn btn-success w-100 mt-3">
Kurulumu Başlat
</button>

<a href="/rental" class="btn btn-outline-light w-100 mt-3 d-none" id="exitBtn">
Listeye Dön
</a>

<script>
let poller = null;

document.getElementById('startDeploy').onclick = function () {
    this.disabled = true;

    fetch('modules/rental/ajax/start_deploy.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'rental_id=<?php echo (int)$rentalId; ?>'
    });

    poller = setInterval(() => {
        fetch('modules/rental/ajax/poll_deploy.php?rental_id=<?php echo (int)$rentalId; ?>')
        .then(r=>r.json())
        .then(d=>{
            document.getElementById('liveLog').textContent = d.log;
            document.getElementById('liveLog').scrollTop =
                document.getElementById('liveLog').scrollHeight;

            if (d.status === 'success' || d.status === 'failed') {
                clearInterval(poller);
                document.getElementById('exitBtn').classList.remove('d-none');
            }
        });
    }, 1500);
};
</script>

<?php endif; ?>

</div>
</div>
</div>
</div>

<?php require 'templates/footer.php'; ?>
