<?php
$paginatitel = 'Gebruikers';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$db = db();
$huidig_id = huidig_gebruiker()['id'];
$fout_reset = '';

// POST: rol wijzigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rol_user_id'])) {
    csrf_check();
    $rol_id    = (int)$_POST['rol_user_id'];
    $nieuwe_rol = ($_POST['nieuwe_rol'] ?? '') === 'admin' ? 'admin' : 'gebruiker';
    if ($rol_id !== (int)$huidig_id) {
        $db->prepare('UPDATE users SET rol = ? WHERE id = ?')->execute([$nieuwe_rol, $rol_id]);
        log_actie('rol_gewijzigd', 'Gebruiker ID: ' . $rol_id . ' → ' . $nieuwe_rol);
        flash_set('succes', 'Rol bijgewerkt.');
    }
    header('Location: index.php');
    exit;
}

// POST: wachtwoord resetten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_id'])) {
    csrf_check();
    $reset_id = (int)$_POST['reset_user_id'];
    $nieuw_ww = $_POST['reset_wachtwoord'] ?? '';
    if (strlen($nieuw_ww) < 8) {
        $fout_reset = 'Wachtwoord moet minimaal 8 tekens zijn.';
    } else {
        $hash = password_hash($nieuw_ww, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET wachtwoord = ? WHERE id = ?')->execute([$hash, $reset_id]);
        log_actie('wachtwoord_gereset', 'Gebruiker ID: ' . $reset_id);
        flash_set('succes', 'Wachtwoord succesvol gewijzigd.');
        header('Location: index.php');
        exit;
    }
}

// POST: één login-poging deblokkeren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deblokkeer_id'])) {
    csrf_check();
    $deblok_id = (int)$_POST['deblokkeer_id'];
    $db->prepare('DELETE FROM login_pogingen WHERE id = ?')->execute([$deblok_id]);
    log_actie('login_deblokkeerd', 'Login_pogingen ID: ' . $deblok_id);
    flash_set('succes', 'Deblokkeerd.');
    header('Location: index.php');
    exit;
}

// POST: alle blokkades opheffen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deblokkeer_alles'])) {
    csrf_check();
    $db->exec('DELETE FROM login_pogingen');
    log_actie('login_deblokkeerd', 'Alle blokkades opgeheven');
    flash_set('succes', 'Alle blokkades opgeheven.');
    header('Location: index.php');
    exit;
}

// POST: gebruiker verwijderen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verwijder_user_id'])) {
    csrf_check();
    $del_id = (int)$_POST['verwijder_user_id'];
    if ($del_id !== (int)$huidig_id) {
        $g = $db->prepare('SELECT naam FROM users WHERE id = ?');
        $g->execute([$del_id]);
        $del_user = $g->fetch();
        if ($del_user) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
            log_actie('gebruiker_verwijderd', 'Naam: ' . $del_user['naam']);
            flash_set('succes', 'Gebruiker verwijderd.');
        }
    }
    header('Location: index.php');
    exit;
}

$gebruikers = $db->query("SELECT id, naam, IFNULL(gebruikersnaam,'') AS gebruikersnaam, email, rol, aangemaakt_op FROM users ORDER BY naam")->fetchAll();

// Login-pogingen en blokkades — tonen alleen actieve rijen (geblokkeerd of recent)
try {
    $login_pogingen = $db->query("SELECT id, ip, login, pogingen, geblokkeerd_tot, laatste_poging
                                  FROM login_pogingen
                                  WHERE (geblokkeerd_tot IS NOT NULL AND geblokkeerd_tot > NOW())
                                     OR laatste_poging > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                                  ORDER BY laatste_poging DESC")->fetchAll();
} catch (PDOException $e) {
    // Migrate_login_pogingen nog niet gedraaid? Probeer zonder login-kolom.
    $login_pogingen = $db->query("SELECT id, ip, '' AS login, pogingen, geblokkeerd_tot, laatste_poging
                                  FROM login_pogingen
                                  WHERE (geblokkeerd_tot IS NOT NULL AND geblokkeerd_tot > NOW())
                                     OR laatste_poging > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                                  ORDER BY laatste_poging DESC")->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <h4 class="fw-bold mb-0">Gebruikers</h4>
    <a href="toevoegen.php" class="btn btn-primary">+ Gebruiker toevoegen</a>
</div>

<?php if ($fout_reset): ?>
<div class="alert alert-danger"><?= h($fout_reset) ?></div>
<?php endif; ?>

<div class="bg-white rounded-3 border">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Naam</th>
                <th>Inlognaam</th>
                <th>E-mail</th>
                <th>Rol</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($gebruikers as $g): ?>
        <tr>
            <td>
                <?= h($g['naam']) ?>
                <?= $g['id'] == $huidig_id ? '<span class="badge bg-secondary ms-1">Jij</span>' : '' ?>
            </td>
            <td class="text-muted small"><?= h($g['gebruikersnaam'] ?: '—') ?></td>
            <td><?= h($g['email']) ?></td>
            <td>
                <?php if ($g['id'] == $huidig_id): ?>
                    <span class="badge <?= $g['rol'] === 'admin' ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>">
                        <?= $g['rol'] === 'admin' ? 'Admin' : 'Gebruiker' ?>
                    </span>
                <?php else: ?>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="rol_user_id" value="<?= $g['id'] ?>">
                        <input type="hidden" name="nieuwe_rol" value="<?= $g['rol'] === 'admin' ? 'gebruiker' : 'admin' ?>">
                        <button type="submit" class="badge border-0 btn p-0 <?= $g['rol'] === 'admin' ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>"
                                style="font-size:12px;"
                                onclick="return confirm('Rol wijzigen naar <?= $g['rol'] === 'admin' ? 'Gebruiker' : 'Admin' ?>?')">
                            <?= $g['rol'] === 'admin' ? 'Admin' : 'Gebruiker' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#modalReset"
                        onclick="document.getElementById('reset_user_id').value=<?= $g['id'] ?>; document.getElementById('reset_naam').textContent='<?= h($g['naam']) ?>'">
                    <i class="ri-key-line"></i>
                </button>
                <?php if ($g['id'] != $huidig_id): ?>
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="verwijder_user_id" value="<?= $g['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Gebruiker <?= h($g['naam']) ?> verwijderen?')">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Login pogingen / Blokkades -->
<div class="d-flex justify-content-between align-items-center mt-5 mb-3">
    <h5 class="fw-bold mb-0"><i class="ri-lock-line me-1"></i> Login pogingen</h5>
    <?php if (!empty($login_pogingen)): ?>
    <form method="post" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="deblokkeer_alles" value="1">
        <button type="submit" class="btn btn-outline-danger btn-sm"
                onclick="return confirm('Alle blokkades en login-pogingen wissen?')">
            <i class="ri-delete-bin-line"></i> Alles wissen
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (empty($login_pogingen)): ?>
<div class="bg-white rounded-3 border p-4 text-center text-muted">
    <i class="ri-shield-check-line text-success" style="font-size:24px;"></i>
    <div class="small mt-1">Geen actieve login-pogingen of blokkades.</div>
</div>
<?php else: ?>
<div class="bg-white rounded-3 border">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>IP</th>
                <th>Login</th>
                <th>Pogingen</th>
                <th>Status</th>
                <th>Laatste poging</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($login_pogingen as $lp):
            $geblokkeerd = $lp['geblokkeerd_tot'] !== null && strtotime($lp['geblokkeerd_tot']) > time();
            $resterend = $geblokkeerd ? max(0, strtotime($lp['geblokkeerd_tot']) - time()) : 0;
        ?>
        <tr <?= $geblokkeerd ? 'style="background:#fef2f2;"' : '' ?>>
            <td><code style="font-size:12px;"><?= h($lp['ip']) ?></code></td>
            <td class="small"><?= h($lp['login'] ?: '—') ?></td>
            <td><span class="badge bg-secondary"><?= (int)$lp['pogingen'] ?></span></td>
            <td>
                <?php if ($geblokkeerd): ?>
                    <span class="badge bg-danger">
                        <i class="ri-lock-line"></i> Geblokkeerd nog <?= ceil($resterend / 60) ?> min
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-dark border">Actief telt</span>
                <?php endif; ?>
            </td>
            <td class="small text-muted"><?= h(date('d-m-Y H:i', strtotime($lp['laatste_poging']))) ?></td>
            <td class="text-end">
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="deblokkeer_id" value="<?= (int)$lp['id'] ?>">
                    <button type="submit" class="btn btn-sm <?= $geblokkeerd ? 'btn-danger' : 'btn-outline-secondary' ?>"
                            onclick="return confirm('Deblokkeren / pogingen wissen voor dit IP+login?')">
                        <i class="ri-shield-cross-line"></i> Deblokkeer
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal: Wachtwoord resetten -->
<div class="modal fade" id="modalReset" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Wachtwoord resetten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small">Nieuw wachtwoord voor <strong id="reset_naam"></strong></p>
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="reset_user_id" id="reset_user_id" value="">
                    <div class="mb-3">
                        <input type="password" name="reset_wachtwoord" class="form-control rounded-3" placeholder="Nieuw wachtwoord" required minlength="8" autocomplete="new-password">
                        <div class="invalid-feedback">Minimaal 8 tekens.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
