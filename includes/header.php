<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
vereist_login();
$gebruiker = huidig_gebruiker();
$base = basis_url();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($paginatitel) ? h($paginatitel) . ' - ' : '' ?>Connect App</title>
    <link rel="manifest" href="/app/manifest.json">
    <meta name="theme-color" content="#185E9B">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Connect4IT">
    <link rel="apple-touch-icon" href="/app/images/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background-color: #f8f9fa; margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 220px;
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            z-index: 200;
            transition: transform .25s ease;
        }
        .sidebar-logo { padding: 20px 16px 12px; border-bottom: 1px solid #f0f0f0; }
        .sidebar-logo img { height: 36px; }
        .sidebar-user {
            padding: 12px 16px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-user .avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: #185E9B;
            color: #fff;
            font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; letter-spacing: .5px;
        }
        .sidebar-user .user-info .user-naam { font-size: 13px; font-weight: 600; color: #212529; line-height: 1.2; }
        .sidebar-user .user-info .user-rol  { font-size: 10px; color: #6c757d; text-transform: capitalize; }
        .sidebar-nav { flex: 1; padding: 12px 10px; list-style: none; margin: 0; overflow-y: auto; }
        .sidebar-nav li a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            text-decoration: none; color: #495057;
            font-size: 14px; font-weight: 500;
            transition: background .15s;
        }
        .sidebar-nav li a:hover { background: #f8f9fa; }
        .sidebar-nav li a.active { background: #fff4f0; color: #e8621a; }
        .sidebar-logout { padding: 16px; border-top: 1px solid #f0f0f0; }
        .sidebar-logout a { display: flex; align-items: center; gap: 8px; color: #6c757d; text-decoration: none; font-size: 14px; margin-bottom: 8px; }
        .sidebar-logout a:last-child { margin-bottom: 0; }
        .sidebar-logout a:hover { color: #343a40; }

        /* Overlay (mobiel) */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 199; }
        .sidebar-overlay.open { display: block; }

        /* Mobiele topbar */
        .mobile-topbar {
            display: none; position: fixed; top: 0; left: 0; right: 0;
            height: 56px; background: #fff; border-bottom: 1px solid #e9ecef;
            align-items: center; padding: 0 16px; z-index: 150; gap: 12px;
        }
        .mobile-topbar .hamburger { background: none; border: none; padding: 4px; cursor: pointer; color: #495057; }
        .mobile-topbar .brand { font-size: 15px; font-weight: 700; color: #185E9B; }

        /* Main content */
        .main-wrapper { margin-left: 220px; min-height: 100vh; padding: 32px; }

        /* Buttons */
        .btn-primary { background-color: #185E9B; border-color: #185E9B; }
        .btn-primary:hover { background-color: #134d7e; border-color: #134d7e; }
        .btn-outline-secondary { border-color: #dee2e6; color: #495057; }
        .btn-outline-secondary:hover { background-color: #f8f9fa; }

        /* Validatie */
        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid { border-color: #dc3545; }
        .invalid-feedback { display: none; font-size: 12px; color: #dc3545; margin-top: 3px; }
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback { display: block; }

        /* Tabs */
        .nav-tabs .nav-link { color: #6c757d; font-size: 14px; }
        .nav-tabs .nav-link.active { color: #185E9B; font-weight: 600; }

        /* Badge kleuren */
        .badge-netwerk   { background: #d1ecf1; color: #0c5460; }
        .badge-server    { background: #d4edda; color: #155724; }
        .badge-cloud     { background: #cce5ff; color: #004085; }
        .badge-portaal   { background: #fff3cd; color: #856404; }
        .badge-overig    { background: #e2e3e5; color: #383d41; }
        .badge-actief    { background: #d4edda; color: #155724; }
        .badge-defect    { background: #f8d7da; color: #721c24; }
        .badge-retour    { background: #fff3cd; color: #856404; }

        /* Responsive */
        @media (max-width: 767px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .mobile-topbar { display: flex; }
            .main-wrapper { margin-left: 0; padding: 16px; padding-top: 72px; }
            body { overflow-x: hidden; }
            .p-4 { padding: 1rem !important; }
            .modal-dialog { margin: 0.5rem; }
            .filter-tabs { overflow-x: auto; white-space: nowrap; flex-wrap: nowrap !important; }
        }
        @media (max-width: 480px) {
            .main-wrapper { padding: 12px; padding-top: 68px; }
            h4.fw-bold { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<!-- Mobiele topbar -->
<div class="mobile-topbar">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
        <i class="ri-menu-line" style="font-size:22px;"></i>
    </button>
    <img src="<?= $base ?>/images/logo.png" alt="Connect4IT" style="height:32px;" onerror="this.style.display='none'">
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="<?= $base ?>/klanten/index.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <img src="<?= $base ?>/images/logo.png" alt="Connect4IT" style="height:36px;" onerror="this.style.display='none'">
            <span style="font-size:15px;font-weight:700;color:#185E9B;letter-spacing:.3px;">Connect4IT</span>
        </a>
    </div>
    <ul class="sidebar-nav">
        <li>
            <a href="<?= $base ?>/klanten/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/klanten/') !== false ? 'active' : '' ?>">
                <i class="ri-building-2-line" style="font-size:17px;"></i>
                Klanten
            </a>
        </li>
        <li>
            <a href="<?= $base ?>/sct/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/sct/') !== false ? 'active' : '' ?>">
                <i class="ri-shield-keyhole-line" style="font-size:17px;"></i>
                Veilig versturen
            </a>
        </li>
        <?php if ($gebruiker['rol'] === 'admin'): ?>
        <li style="padding: 10px 12px 2px; pointer-events:none;">
            <span style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#adb5bd;">Beheer</span>
        </li>
        <li>
            <a href="<?= $base ?>/gebruikers/index.php" class="<?= (strpos($_SERVER['PHP_SELF'], '/gebruikers/') !== false && strpos($_SERVER['PHP_SELF'], 'logboek') === false) ? 'active' : '' ?>">
                <i class="ri-shield-user-line" style="font-size:17px;"></i>
                Gebruikers
            </a>
        </li>
        <li>
            <a href="<?= $base ?>/gebruikers/logboek.php" class="<?= strpos($_SERVER['PHP_SELF'], 'logboek') !== false ? 'active' : '' ?>">
                <i class="ri-clipboard-line" style="font-size:17px;"></i>
                Logboek
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <div class="sidebar-user">
        <div class="avatar"><?= strtoupper(mb_substr($gebruiker['naam'], 0, 1)) ?><?= strtoupper(mb_substr(strstr($gebruiker['naam'], ' ') ?: '', 1, 1)) ?></div>
        <div class="user-info">
            <div class="user-naam"><?= h($gebruiker['naam']) ?></div>
            <div class="user-rol"><?= h($gebruiker['rol']) ?></div>
        </div>
    </div>
    <div class="sidebar-logout">
        <a href="<?= $base ?>/gebruikers/wachtwoord.php">
            <i class="ri-lock-password-line" style="font-size:17px;"></i>
            Wachtwoord wijzigen
        </a>
        <a href="<?= $base ?>/gebruikers/2fa.php">
            <i class="ri-shield-keyhole-line" style="font-size:17px;"></i>
            2FA beveiliging
        </a>
        <a href="<?= $base ?>/logout.php">
            <i class="ri-logout-box-r-line" style="font-size:17px;"></i>
            Uitloggen
        </a>
    </div>
</div>

<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/app/sw.js');
}
</script>

<div class="main-wrapper">
    <?= flash_html() ?>
