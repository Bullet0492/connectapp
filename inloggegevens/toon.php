<?php
/**
 * AJAX endpoint: geeft ontsleuteld wachtwoord terug als JSON
 * Alleen voor ingelogde gebruikers, CSRF check via GET param
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

header('Content-Type: application/json');

if (!is_ingelogd()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

// CSRF check via GET (omdat het een AJAX GET request is)
$csrf = $_GET['csrf'] ?? '';
if (!hash_equals(csrf_token(), $csrf)) {
    echo json_encode(['error' => 'Ongeldig verzoek']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Ongeldig ID']);
    exit;
}

$stmt = db()->prepare('SELECT wachtwoord_enc FROM inloggegevens WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || empty($row['wachtwoord_enc'])) {
    echo json_encode(['ww' => '']);
    exit;
}

$plaintext = decrypt_wachtwoord($row['wachtwoord_enc']);
log_actie('wachtwoord_bekeken', 'Inloggegevens ID: ' . $id);

echo json_encode(['ww' => $plaintext]);
