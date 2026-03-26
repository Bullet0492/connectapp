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
    <link rel="manifest" href="/app/manifest.json">
    <meta name="theme-color" content="#185E9B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Connect4IT">
    <link rel="apple-touch-icon" href="/app/images/logo.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 36px 32px;
            width: 100%;
            max-width: 380px;
        }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { max-height: 70px; }
        h5 { font-size: 18px; font-weight: 700; text-align: center; color: #212529; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #6c757d; text-align: center; margin-bottom: 28px; }
        .alert {
            background: #fff3f3; border: 1px solid #f5c6cb; color: #721c24;
            border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 18px;
        }
        input[type=text] {
            width: 100%; padding: 14px 12px;
            border: 1px solid #ced4da; border-radius: 8px;
            font-size: 2rem; font-weight: 700; letter-spacing: .5rem; text-align: center;
            outline: none; transition: border-color .15s;
        }
        input[type=text]:focus { border-color: #185E9B; box-shadow: 0 0 0 3px rgba(24,94,155,.15); }
        .mb { margin-bottom: 20px; }
        button {
            width: 100%; padding: 12px;
            background: #185E9B; color: #fff; border: none;
            border-radius: 8px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: background .15s;
            margin-bottom: 14px;
        }
        button:hover { background: #134d7e; }
        .link-wrap { text-align: center; }
        .link-wrap a { font-size: 13px; color: #6c757d; text-decoration: none; }
        .link-wrap a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <img src="<?= basis_url() ?>/images/logo.png" alt="Connect4IT" onerror="this.style.display='none'">
    </div>
    <h5>Verificatiecode</h5>
    <p class="sub">Open uw authenticator-app en voer de 6-cijferige code in.</p>

    <?php if ($fout): ?>
        <div class="alert"><?= h($fout) ?></div>
    <?php endif; ?>

    <form method="post" novalidate autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb">
            <input type="text" name="totp_code"
                   maxlength="6" pattern="\d{6}" inputmode="numeric"
                   placeholder="000000" autofocus autocomplete="one-time-code" required>
        </div>
        <button type="submit">Verifiëren</button>
        <div class="link-wrap">
            <a href="login.php">Annuleren en opnieuw inloggen</a>
        </div>
    </form>
</div>
<script>
    const inp = document.querySelector('[name="totp_code"]');
    inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g, '');
        if (inp.value.length === 6) inp.closest('form').submit();
    });
    if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/app/sw.js'); }
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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 36px 32px;
            width: 100%;
            max-width: 400px;
        }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { max-height: 80px; }
        h4 { font-size: 20px; font-weight: 700; text-align: center; color: #212529; margin-bottom: 28px; }
        .alert {
            background: #fff3f3; border: 1px solid #f5c6cb; color: #721c24;
            border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 18px;
        }
        .field { margin-bottom: 18px; }
        label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        input[type=text], input[type=password] {
            width: 100%; padding: 11px 14px;
            border: 1px solid #ced4da; border-radius: 8px;
            font-size: 15px; outline: none; transition: border-color .15s;
        }
        input[type=text]:focus, input[type=password]:focus {
            border-color: #185E9B;
            box-shadow: 0 0 0 3px rgba(24,94,155,.15);
        }
        button {
            width: 100%; padding: 12px;
            background: #185E9B; color: #fff; border: none;
            border-radius: 8px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: background .15s; margin-top: 4px;
        }
        button:hover { background: #134d7e; }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">
        <img src="<?= basis_url() ?>/images/logo.png" alt="Connect4IT logo" onerror="this.style.display='none'">
    </div>
    <h4>Connect App</h4>

    <?php if ($fout): ?>
        <div class="alert"><?= h($fout) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label>Gebruikersnaam of e-mailadres</label>
            <input type="text" name="login" required autofocus
                   value="<?= h($_POST['login'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Wachtwoord</label>
            <input type="password" name="wachtwoord" required>
        </div>
        <button type="submit">Inloggen</button>
    </form>
</div>

<script>if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/app/sw.js'); }</script>
</body>
</html>
