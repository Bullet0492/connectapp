<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

sessie_start();

if (is_ingelogd()) {
    header('Location: ' . basis_url() . '/index.php');
    exit;
}

$fout = '';

// ─── Stap 2: 2FA-code controleren ────────────────────────────────────────────
if (is_2fa_pending()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
        csrf_check();
        $resultaat = verifieer_2fa($_POST['totp_code'] ?? '');
        if ($resultaat === 'ok') {
            header('Location: ' . basis_url() . '/index.php');
            exit;
        } elseif ($resultaat === 'verlopen') {
            $fout = 'De verificatiecode is verlopen. Log opnieuw in.';
            session_unset(); session_destroy(); sessie_start();
        } elseif ($resultaat === 'geblokkeerd') {
            $fout = 'Te veel mislukte pogingen. Wacht 15 minuten.';
        } else {
            $fout = 'Onjuiste code. Controleer uw authenticator-app en probeer opnieuw.';
        }
    }

    if (is_2fa_pending()):
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificatiecode - Connect App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        .btn-primary { background-color: #185E9B; border-color: #185E9B; }
        .btn-primary:hover { background-color: #134d7e; border-color: #134d7e; }
        .totp-input { font-size: 2rem; font-weight: 700; letter-spacing: .5rem; text-align: center; }
    </style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card shadow" style="width:100%;max-width:380px">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <img src="<?= basis_url() ?>/images/logo.png" alt="Connect4IT" style="max-height:70px;" onerror="this.style.display='none'">
        </div>
        <h5 class="card-title text-center mb-1">Verificatiecode</h5>
        <p class="text-muted text-center small mb-4">Open uw authenticator-app en voer de 6-cijferige code in.</p>

        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= h($fout) ?></div>
        <?php endif; ?>

        <form method="post" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-4">
                <input type="text" name="totp_code" class="form-control totp-input"
                       maxlength="6" pattern="\d{6}" inputmode="numeric"
                       placeholder="000000" autofocus autocomplete="one-time-code" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Verifiëren</button>
            <div class="text-center">
                <a href="login.php" class="text-muted small">Annuleren en opnieuw inloggen</a>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const inp = document.querySelector('[name="totp_code"]');
    inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g, '');
        if (inp.value.length === 6) inp.closest('form').submit();
    });
</script>
</body>
</html>
<?php
    endif;
    if (is_2fa_pending()) exit;
}

// ─── Stap 1: Wachtwoord controleren ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login     = trim($_POST['login'] ?? '');
    $ww        = $_POST['wachtwoord'] ?? '';
    $resultaat = login($login, $ww);

    if ($resultaat === 'ok') {
        header('Location: ' . basis_url() . '/index.php');
        exit;
    } elseif ($resultaat === '2fa_vereist') {
        header('Location: ' . basis_url() . '/login.php');
        exit;
    } elseif ($resultaat === 'geblokkeerd') {
        $fout = 'Te veel mislukte inlogpogingen. Probeer het over 15 minuten opnieuw.';
    } else {
        $fout = 'Onjuiste gebruikersnaam, e-mailadres of wachtwoord.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen - Connect App</title>
    <link rel="manifest" href="/app/manifest.json">
    <meta name="theme-color" content="#185E9B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Connect4IT">
    <link rel="apple-touch-icon" href="/app/images/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        .btn-primary { background-color: #185E9B; border-color: #185E9B; }
        .btn-primary:hover { background-color: #134d7e; border-color: #134d7e; }
    </style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">

<div class="card shadow" style="width:100%;max-width:400px">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <img src="<?= basis_url() ?>/images/logo.png" alt="Connect4IT logo" style="max-height:80px;" onerror="this.style.display='none'">
        </div>
        <h4 class="card-title text-center mb-4">Connect App</h4>

        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= h($fout) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Gebruikersnaam of e-mailadres</label>
                <input type="text" name="login" class="form-control" required autofocus
                       value="<?= h($_POST['login'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Wachtwoord</label>
                <input type="password" name="wachtwoord" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Inloggen</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/app/sw.js'); }</script>
</body>
</html>
