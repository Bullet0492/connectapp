<?php
$paginatitel = 'Sessie instellingen';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db   = db();
$user = huidig_gebruiker();
$fout = '';
$ok   = false;

$opties = [
    120   => '2 uur (standaard)',
    480   => '8 uur',
    1440  => '1 dag',
    10080 => '7 dagen',
    0     => 'Nooit (alleen handmatig uitloggen)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $keuze = (int)($_POST['timeout_minuten'] ?? 120);
    if (!array_key_exists($keuze, $opties)) {
        $fout = 'Ongeldige keuze.';
    } else {
        $db->prepare('UPDATE users SET sessie_timeout_minuten = ? WHERE id = ?')->execute([$keuze, $user['id']]);
        $_SESSION['timeout_minuten'] = $keuze;
        log_actie('sessie_timeout_gewijzigd', 'Gebruiker: ' . $user['naam'] . ' → ' . ($opties[$keuze] ?? $keuze));
        $ok = true;
    }
}

$rij = $db->prepare('SELECT sessie_timeout_minuten FROM users WHERE id = ?');
$rij->execute([$user['id']]);
$huidige = (int)($rij->fetchColumn() ?: 120);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Sessie instellingen</h4>
    <p class="text-muted mb-0">Bepaal hoe lang u ingelogd blijft bij inactiviteit.</p>
</div>

<div class="bg-white rounded-3 border p-4" style="max-width:560px;">
    <?php if ($ok): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="ri-checkbox-circle-line"></i>
        Instelling opgeslagen. Werkt direct in de huidige sessie.
    </div>
    <?php endif; ?>
    <?php if ($fout): ?>
    <div class="alert alert-danger"><?= h($fout) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label fw-medium">Automatisch uitloggen na inactiviteit</label>
            <select name="timeout_minuten" class="form-select rounded-3">
                <?php foreach ($opties as $minuten => $label): ?>
                <option value="<?= $minuten ?>" <?= $huidige === $minuten ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text text-muted">
                Bij "Nooit" blijft uw sessie staan tot u handmatig uitlogt of de browser-cookie verloopt (30 dagen).
                Tip: zet alleen op "Nooit" op een eigen, beveiligd apparaat.
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <a href="<?= $base ?>/index.php" class="btn btn-outline-secondary flex-grow-1 rounded-3">Annuleren</a>
            <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">
                <i class="ri-save-line me-1"></i>Opslaan
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
