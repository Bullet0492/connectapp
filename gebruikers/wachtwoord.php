<?php
$paginatitel = 'Wachtwoord wijzigen';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db   = db();
$user = huidig_gebruiker();
$fout = '';
$ok   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $huidig   = $_POST['huidig_wachtwoord'] ?? '';
    $nieuw    = $_POST['nieuw_wachtwoord'] ?? '';
    $bevestig = $_POST['bevestig_wachtwoord'] ?? '';

    $stmt = $db->prepare('SELECT wachtwoord FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($huidig, $row['wachtwoord'])) {
        $fout = 'Het huidige wachtwoord klopt niet.';
    } elseif (strlen($nieuw) < 8) {
        $fout = 'Het nieuwe wachtwoord moet minimaal 8 tekens bevatten.';
    } elseif ($nieuw !== $bevestig) {
        $fout = 'De twee nieuwe wachtwoorden komen niet overeen.';
    } else {
        $hash = password_hash($nieuw, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET wachtwoord = ? WHERE id = ?')->execute([$hash, $user['id']]);
        log_actie('wachtwoord_gewijzigd', 'Gebruiker: ' . $user['naam']);
        $ok = true;
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Wachtwoord wijzigen</h4>
    <p class="text-muted mb-0">Wijzig het wachtwoord van uw account</p>
</div>

<div class="bg-white rounded-3 border p-4" style="max-width:480px;">
    <?php if ($ok): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="ri-checkbox-circle-line"></i>
        Wachtwoord succesvol gewijzigd.
    </div>
    <?php endif; ?>
    <?php if ($fout): ?>
    <div class="alert alert-danger"><?= h($fout) ?></div>
    <?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label fw-medium">Huidig wachtwoord <span class="text-danger">*</span></label>
            <input type="password" name="huidig_wachtwoord" class="form-control rounded-3" required autocomplete="current-password">
            <div class="invalid-feedback">Vul uw huidige wachtwoord in.</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium">Nieuw wachtwoord <span class="text-danger">*</span></label>
            <input type="password" name="nieuw_wachtwoord" class="form-control rounded-3" required minlength="8" autocomplete="new-password">
            <div class="form-text text-muted">Minimaal 8 tekens.</div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-medium">Bevestig nieuw wachtwoord <span class="text-danger">*</span></label>
            <input type="password" name="bevestig_wachtwoord" class="form-control rounded-3" required autocomplete="new-password">
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $base ?>/index.php" class="btn btn-outline-secondary flex-grow-1 rounded-3">Annuleren</a>
            <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">
                <i class="ri-lock-password-line me-1"></i>Wachtwoord wijzigen
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
