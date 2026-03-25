<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

sessie_start();

if (is_ingelogd()) {
    header('Location: ' . basis_url() . '/index.php');
    exit;
}

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login = trim($_POST['login'] ?? '');
    $ww    = $_POST['wachtwoord'] ?? '';

    $resultaat = login($login, $ww);
    if ($resultaat === 'ok') {
        header('Location: ' . basis_url() . '/index.php');
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
</body>
</html>
