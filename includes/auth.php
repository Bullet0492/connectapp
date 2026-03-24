<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function sessie_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_ingelogd(): bool {
    sessie_start();
    return isset($_SESSION['user_id']);
}

function vereist_login(): void {
    if (!is_ingelogd()) {
        header('Location: ' . basis_url() . '/login.php');
        exit;
    }
}

function vereist_admin(): void {
    vereist_login();
    if (($_SESSION['user_rol'] ?? '') !== 'admin') {
        header('Location: ' . basis_url() . '/index.php');
        exit;
    }
}

function huidig_gebruiker(): array {
    sessie_start();
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'naam' => $_SESSION['user_naam'] ?? '',
        'rol'  => $_SESSION['user_rol'] ?? '',
    ];
}

function ip_adres(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function login_is_geblokkeerd(): bool {
    $stmt = db()->prepare('SELECT geblokkeerd_tot FROM login_pogingen WHERE ip = ?');
    $stmt->execute([ip_adres()]);
    $rij = $stmt->fetch();
    if ($rij && $rij['geblokkeerd_tot'] !== null) {
        return new DateTime($rij['geblokkeerd_tot']) > new DateTime();
    }
    return false;
}

function registreer_mislukte_poging(): void {
    $ip           = ip_adres();
    $max          = 5;
    $blok_minuten = 15;

    $stmt = db()->prepare('SELECT id, pogingen, geblokkeerd_tot FROM login_pogingen WHERE ip = ?');
    $stmt->execute([$ip]);
    $rij = $stmt->fetch();

    if ($rij) {
        $nieuw           = $rij['pogingen'] + 1;
        $geblokkeerd_tot = $rij['geblokkeerd_tot'];
        if ($nieuw >= $max && $geblokkeerd_tot === null) {
            $geblokkeerd_tot = (new DateTime())->modify("+{$blok_minuten} minutes")->format('Y-m-d H:i:s');
        }
        db()->prepare('UPDATE login_pogingen SET pogingen = ?, geblokkeerd_tot = ?, laatste_poging = NOW() WHERE id = ?')
            ->execute([$nieuw, $geblokkeerd_tot, $rij['id']]);
    } else {
        db()->prepare('INSERT INTO login_pogingen (ip, pogingen, laatste_poging) VALUES (?, 1, NOW())')
            ->execute([$ip]);
    }

    db()->exec("DELETE FROM login_pogingen WHERE (geblokkeerd_tot IS NOT NULL AND geblokkeerd_tot < NOW()) OR (geblokkeerd_tot IS NULL AND laatste_poging < DATE_SUB(NOW(), INTERVAL 1 HOUR))");
}

function reset_login_pogingen(): void {
    db()->prepare('DELETE FROM login_pogingen WHERE ip = ?')
        ->execute([ip_adres()]);
}

function login(string $login, string $wachtwoord): string {
    if (login_is_geblokkeerd()) {
        return 'geblokkeerd';
    }

    $stmt = db()->prepare('SELECT id, naam, wachtwoord, rol FROM users WHERE email = ? OR gebruikersnaam = ?');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($wachtwoord, $user['wachtwoord'])) {
        reset_login_pogingen();
        sessie_start();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_naam'] = $user['naam'];
        $_SESSION['user_rol']  = $user['rol'];
        return 'ok';
    }

    registreer_mislukte_poging();
    return 'fout';
}

function logout(): void {
    sessie_start();
    session_destroy();
    header('Location: ' . basis_url() . '/login.php');
    exit;
}

function basis_url(): string {
    return BASE_URL;
}
