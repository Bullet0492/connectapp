<?php
/**
 * SSO-starter: genereert een kortlopende signed token met de email van
 * de ingelogde ConnectApp-gebruiker en redirect naar werkbon/sso.php.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

sessie_start();
vereist_login();

$user = huidig_gebruiker();

// Haal email op van huidige gebruiker
$stmt = db()->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$email = $stmt->fetchColumn();

if (!$email) {
    http_response_code(400);
    die('Geen emailadres bekend voor deze gebruiker — kan niet automatisch inloggen op werkbon.');
}

// Token: base64url(payload_json) . "." . base64url(hmac_sha256(payload_b64, secret))
$payload = [
    'email' => $email,
    'exp'   => time() + 60,
    'nonce' => bin2hex(random_bytes(16)),
];

function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$payload_b64 = b64url(json_encode($payload));
$sig_b64     = b64url(hash_hmac('sha256', $payload_b64, SSO_SHARED_SECRET, true));
$token       = $payload_b64 . '.' . $sig_b64;

header('Location: ' . WERKBON_URL . '/sso.php?token=' . urlencode($token));
exit;
