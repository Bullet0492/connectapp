<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/totp.php';

function sessie_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!DEVELOPMENT_MODE) {
            ini_set('session.cookie_secure',   '1'); // Alleen via HTTPS
        }
        ini_set('session.cookie_httponly', '1'); // Niet toegankelijk via JavaScript
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime',  '7200'); // 2 uur
        session_start();
    }
}

function is_ingelogd(): bool {
    sessie_start();
    if (!isset($_SESSION['user_id'])) return false;

    // Sessie-timeout: 2 uur inactiviteit
    if (isset($_SESSION['laatste_activiteit']) && (time() - $_SESSION['laatste_activiteit']) > 7200) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['laatste_activiteit'] = time();
    return true;
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

/**
 * Stap 1: Controleer wachtwoord.
 * Geeft terug: 'ok' | '2fa_vereist' | 'geblokkeerd' | 'fout'
 */
function login(string $login, string $wachtwoord): string {
    if (login_is_geblokkeerd()) {
        return 'geblokkeerd';
    }

    $stmt = db()->prepare('SELECT id, naam, wachtwoord, rol, totp_actief FROM users WHERE email = ? OR gebruikersnaam = ?');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($wachtwoord, $user['wachtwoord'])) {
        reset_login_pogingen();
        sessie_start();

        if (!empty($user['totp_actief'])) {
            // Wachtwoord klopt maar 2FA nog vereist — sla user_id tijdelijk op
            session_regenerate_id(true);
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['2fa_expires'] = time() + 300; // 5 minuten om code in te voeren
            return '2fa_vereist';
        }

        // Geen 2FA: direct inloggen
        session_regenerate_id(true);
        $_SESSION['user_id']            = $user['id'];
        $_SESSION['user_naam']          = $user['naam'];
        $_SESSION['user_rol']           = $user['rol'];
        $_SESSION['login_methode']      = 'wachtwoord';
        $_SESSION['laatste_activiteit'] = time();
        db()->prepare('UPDATE users SET laatste_login = NOW() WHERE id = ?')->execute([$user['id']]);
        return 'ok';
    }

    registreer_mislukte_poging();
    return 'fout';
}

/**
 * Controleert of er een 2FA-check uitstaat voor dit sessie.
 */
function is_2fa_pending(): bool {
    sessie_start();
    return isset($_SESSION['2fa_user_id']) && time() < ($_SESSION['2fa_expires'] ?? 0);
}

/**
 * Stap 2: Verifieer de 6-cijferige TOTP-code.
 * Geeft terug: 'ok' | 'verlopen' | 'geblokkeerd' | 'fout'
 */
function verifieer_2fa(string $code): string {
    sessie_start();

    if (!is_2fa_pending()) {
        return 'verlopen';
    }
    if (login_is_geblokkeerd()) {
        return 'geblokkeerd';
    }

    $stmt = db()->prepare('SELECT id, naam, rol, totp_secret FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['2fa_user_id']]);
    $user = $stmt->fetch();

    if (!$user || empty($user['totp_secret'])) {
        return 'fout';
    }

    $secret = totp_decrypt_secret($user['totp_secret']);

    if (!totp_verifieer($secret, $code)) {
        registreer_mislukte_poging();
        return 'fout';
    }

    // Code correct: schoon de pending-sessie op en log volledig in
    unset($_SESSION['2fa_user_id'], $_SESSION['2fa_expires']);
    reset_login_pogingen();

    session_regenerate_id(true);
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['user_naam']          = $user['naam'];
    $_SESSION['user_rol']           = $user['rol'];
    $_SESSION['login_methode']      = 'wachtwoord+2fa';
    $_SESSION['laatste_activiteit'] = time();
    db()->prepare('UPDATE users SET laatste_login = NOW() WHERE id = ?')->execute([$user['id']]);

    return 'ok';
}

function logout(): void {
    sessie_start();
    session_unset();
    session_destroy();
    header('Location: ' . basis_url() . '/login.php');
    exit;
}

function basis_url(): string {
    return BASE_URL;
}

