<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
header('Content-Type: application/json');
if (!is_ingelogd()) { echo json_encode(['error' => 'Niet ingelogd']); exit; }
if (!hash_equals(csrf_token(), $_GET['csrf'] ?? '')) { echo json_encode(['error' => 'Ongeldig']); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT admin_wachtwoord_enc FROM klant_o365 WHERE klant_id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || empty($row['admin_wachtwoord_enc'])) { echo json_encode(['ww' => '']); exit; }
log_actie('o365_wachtwoord_bekeken', 'Klant ID: ' . $id);
echo json_encode(['ww' => decrypt_wachtwoord($row['admin_wachtwoord_enc'])]);
