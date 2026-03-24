<?php
$paginatitel = 'Gebruiker toevoegen';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $naam  = trim($_POST['naam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ww    = $_POST['wachtwoord'] ?? '';
    $rol   = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'gebruiker';

    if (!$naam || !$email || !$ww) {
        $fout = 'Alle velden zijn verplicht.';
    } elseif (strlen($ww) < 8) {
        $fout = 'Wachtwoord moet minimaal 8 tekens zijn.';
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $fout = 'Dit e-mailadres is al in gebruik.';
        } else {
            $hash = password_hash($ww, PASSWORD_DEFAULT);
            $gebruikersnaam = strtolower(str_replace(' ', '.', $naam));
            db()->prepare('INSERT INTO users (naam, gebruikersnaam, email, wachtwoord, rol) VALUES (?, ?, ?, ?, ?)')
               ->execute([$naam, $gebruikersnaam, $email, $hash, $rol]);
            log_actie('gebruiker_aangemaakt', 'Naam: ' . $naam . ', e-mail: ' . $email);
            flash_set('succes', "Gebruiker '$naam' aangemaakt.");
            header('Location: index.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Gebruiker toevoegen</h4>
</div>

<div class="bg-white rounded-3 border p-4" style="max-width:500px">
    <?php if ($fout): ?>
        <div class="alert alert-danger"><?= h($fout) ?></div>
    <?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label fw-medium">Naam <span class="text-danger">*</span></label>
            <input type="text" name="naam" class="form-control rounded-3" required value="<?= h($_POST['naam'] ?? '') ?>">
            <div class="form-text text-muted">Inlognaam wordt automatisch: voornaam.achternaam</div>
            <div class="invalid-feedback">Vul een naam in.</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium">E-mailadres <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control rounded-3" required value="<?= h($_POST['email'] ?? '') ?>">
            <div class="invalid-feedback">Vul een geldig e-mailadres in.</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium">Wachtwoord <span class="text-danger">*</span></label>
            <input type="password" name="wachtwoord" class="form-control rounded-3" required minlength="8" autocomplete="new-password">
            <div class="form-text text-muted">Minimaal 8 tekens.</div>
            <div class="invalid-feedback">Vul een wachtwoord in (minimaal 8 tekens).</div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-medium">Rol</label>
            <select name="rol" class="form-select rounded-3">
                <option value="gebruiker">Gebruiker</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary flex-grow-1 rounded-3">Annuleren</a>
            <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Aanmaken</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
