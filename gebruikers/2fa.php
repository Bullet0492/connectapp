<?php
$paginatitel = '2FA beveiliging';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$user    = huidig_gebruiker();
$db      = db();
$fout    = '';
$succes  = '';

// Haal actuele 2FA-status op
$row = $db->prepare('SELECT email, totp_actief, totp_secret FROM users WHERE id = ?');
$row->execute([$user['id']]);
$userdata = $row->fetch();
$is_actief = !empty($userdata['totp_actief']);

// ─── Acties ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    // ── 1. Start setup: genereer geheim en toon QR ───────────────────────────
    if ($actie === 'start_setup') {
        $nieuw_secret = totp_genereer_secret();
        $_SESSION['2fa_setup_secret'] = $nieuw_secret;
        $_SESSION['2fa_setup_expires'] = time() + 600; // 10 minuten

    // ── 2. Bevestig setup: verifieer code en sla op ──────────────────────────
    } elseif ($actie === 'bevestig_setup') {
        $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');
        $secret = $_SESSION['2fa_setup_secret'] ?? '';

        if (empty($secret) || time() > ($_SESSION['2fa_setup_expires'] ?? 0)) {
            $fout = 'De setup-sessie is verlopen. Begin opnieuw.';
            unset($_SESSION['2fa_setup_secret'], $_SESSION['2fa_setup_expires']);
        } elseif (!totp_verifieer($secret, $code)) {
            $fout = 'De code klopt niet. Controleer uw authenticator-app en probeer opnieuw.';
        } else {
            $enc = totp_encrypt_secret($secret);
            $db->prepare('UPDATE users SET totp_secret = ?, totp_actief = 1 WHERE id = ?')
               ->execute([$enc, $user['id']]);
            unset($_SESSION['2fa_setup_secret'], $_SESSION['2fa_setup_expires']);
            log_actie('2fa_ingeschakeld', 'Gebruiker: ' . $user['naam']);
            flash_set('succes', '2FA is succesvol ingeschakeld. Uw account is nu extra beveiligd.');
            header('Location: 2fa.php');
            exit;
        }

    // ── 3. Uitschakelen ──────────────────────────────────────────────────────
    } elseif ($actie === 'uitschakelen') {
        $ww   = $_POST['wachtwoord'] ?? '';
        $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');

        $check = $db->prepare('SELECT wachtwoord, totp_secret FROM users WHERE id = ?');
        $check->execute([$user['id']]);
        $chkrow = $check->fetch();

        if (!$chkrow || !password_verify($ww, $chkrow['wachtwoord'])) {
            $fout = 'Onjuist wachtwoord.';
        } elseif (empty($chkrow['totp_secret']) || !totp_verifieer(totp_decrypt_secret($chkrow['totp_secret']), $code)) {
            $fout = 'Onjuiste verificatiecode.';
        } else {
            $db->prepare('UPDATE users SET totp_secret = NULL, totp_actief = 0 WHERE id = ?')
               ->execute([$user['id']]);
            log_actie('2fa_uitgeschakeld', 'Gebruiker: ' . $user['naam']);
            flash_set('succes', '2FA is uitgeschakeld.');
            header('Location: 2fa.php');
            exit;
        }

    // ── 4. Annuleer setup ────────────────────────────────────────────────────
    } elseif ($actie === 'annuleer_setup') {
        unset($_SESSION['2fa_setup_secret'], $_SESSION['2fa_setup_expires']);
        header('Location: 2fa.php');
        exit;
    }
}

// Controleer of we midden in een setup-flow zitten
$setup_geheim = $_SESSION['2fa_setup_secret'] ?? null;
$setup_actief = $setup_geheim && time() < ($_SESSION['2fa_setup_expires'] ?? 0);
$qr_url       = $setup_actief ? totp_qr_url($setup_geheim, $userdata['email']) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Twee-factor authenticatie (2FA)</h4>
    <p class="text-muted mb-0">Extra beveiliging via een authenticator-app op uw telefoon.</p>
</div>

<?= flash_html() ?>
<?php if ($fout): ?>
    <div class="alert alert-danger"><?= h($fout) ?></div>
<?php endif; ?>

<?php if (!$setup_actief): ?>
<!-- ─── Status kaart ──────────────────────────────────────────────────────── -->
<div class="bg-white rounded-3 border p-4 mb-4" style="max-width:520px">
    <div class="d-flex align-items-center gap-3 mb-3">
        <?php if ($is_actief): ?>
            <div style="width:44px;height:44px;border-radius:50%;background:#d1e7dd;display:flex;align-items:center;justify-content:center;">
                <i class="ri-shield-check-line" style="font-size:22px;color:#0a3622;"></i>
            </div>
            <div>
                <div class="fw-semibold text-success">2FA is ingeschakeld</div>
                <div class="text-muted small">Uw account is beveiligd met een authenticator-app.</div>
            </div>
        <?php else: ?>
            <div style="width:44px;height:44px;border-radius:50%;background:#fff3cd;display:flex;align-items:center;justify-content:center;">
                <i class="ri-shield-line" style="font-size:22px;color:#664d03;"></i>
            </div>
            <div>
                <div class="fw-semibold text-warning">2FA is uitgeschakeld</div>
                <div class="text-muted small">Aanbevolen: schakel 2FA in voor extra beveiliging.</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$is_actief): ?>
    <p class="text-muted small mb-3">
        Na het inschakelen hebt u naast uw wachtwoord ook een 6-cijferige code nodig uit een app als
        <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> of <strong>Authy</strong>.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="actie" value="start_setup">
        <button type="submit" class="btn btn-primary rounded-3">
            <i class="ri-shield-keyhole-line me-1"></i> 2FA inschakelen
        </button>
    </form>

    <?php else: ?>
    <hr>
    <p class="text-muted small mb-3">
        Wilt u 2FA uitschakelen? Vul uw wachtwoord en een geldige verificatiecode in ter bevestiging.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="actie" value="uitschakelen">
        <div class="mb-3">
            <label class="form-label fw-medium">Wachtwoord</label>
            <input type="password" name="wachtwoord" class="form-control rounded-3" required autocomplete="current-password">
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium">Verificatiecode (authenticator-app)</label>
            <input type="text" name="totp_code" class="form-control rounded-3 font-monospace"
                   maxlength="6" pattern="\d{6}" inputmode="numeric" placeholder="000000" required>
        </div>
        <button type="submit" class="btn btn-outline-danger rounded-3">
            <i class="ri-shield-off-line me-1"></i> 2FA uitschakelen
        </button>
    </form>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ─── Setup-flow: QR scannen en bevestigen ─────────────────────────────── -->
<div class="bg-white rounded-3 border p-4" style="max-width:520px">
    <h6 class="fw-semibold mb-3">Stap 1 — Installeer een authenticator-app</h6>
    <p class="text-muted small mb-4">
        Download <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> of <strong>Authy</strong>
        op uw telefoon als u die nog niet hebt.
    </p>

    <h6 class="fw-semibold mb-2">Stap 2 — Scan de QR-code</h6>
    <p class="text-muted small mb-3">Open de app, tik op "+" of "Account toevoegen" en scan de QR-code.</p>

    <div class="text-center mb-3">
        <div id="qrCanvas" style="display:inline-block;"></div>
    </div>

    <details class="mb-4">
        <summary class="text-muted small" style="cursor:pointer;">Kan de QR niet scannen? Voer de code handmatig in</summary>
        <div class="mt-2 p-2 bg-light rounded font-monospace small" style="word-break:break-all;letter-spacing:.1rem;">
            <?= h(chunk_split($setup_geheim, 4, ' ')) ?>
        </div>
    </details>

    <h6 class="fw-semibold mb-2">Stap 3 — Bevestig met een code</h6>
    <p class="text-muted small mb-3">Voer de 6-cijferige code in die de app toont om de koppeling te bevestigen.</p>

    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <input type="text" name="totp_code" class="form-control rounded-3 font-monospace text-center"
                   style="font-size:1.5rem;letter-spacing:.4rem;" maxlength="6" pattern="\d{6}"
                   inputmode="numeric" placeholder="000000" autofocus autocomplete="one-time-code" required>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" name="actie" value="annuleer_setup" class="btn btn-outline-secondary rounded-3">Annuleren</button>
            <button type="submit" name="actie" value="bevestig_setup" class="btn btn-primary rounded-3 flex-grow-1">
                <i class="ri-shield-check-line me-1"></i> 2FA activeren
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qrCanvas'), {
        text: <?= json_encode($qr_url) ?>,
        width: 200,
        height: 200,
        correctLevel: QRCode.CorrectLevel.M
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
