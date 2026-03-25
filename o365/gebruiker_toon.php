<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
header('Content-Type: application/json');
if (!is_ingelogd()) { echo json_encode(['error' => 'Niet ingelogd']); exit; }
if (!hash_equals(csrf_token(), $_GET['csrf'] ?? '')) { echo json_encode(['error' => 'Ongeldig']); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT wachtwoord_enc FROM klant_o365_gebruikers WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || empty($row['wachtwoord_enc'])) { echo json_encode(['ww' => '']); exit; }
log_actie('o365_gebruiker_wachtwoord_bekeken', 'Gebruiker ID: ' . $id);
echo json_encode(['ww' => decrypt_wachtwoord($row['wachtwoord_enc'])]);
