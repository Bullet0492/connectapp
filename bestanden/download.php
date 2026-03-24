<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . basis_url() . '/index.php'); exit; }

$stmt = db()->prepare('SELECT * FROM klant_bestanden WHERE id = ?');
$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) { flash_set('fout', 'Bestand niet gevonden.'); header('Location: ' . basis_url() . '/index.php'); exit; }

$pad = __DIR__ . '/../uploads/bestanden/' . $b['bestandsnaam'];
if (!file_exists($pad)) { flash_set('fout', 'Bestand bestaat niet meer op server.'); header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $b['klant_id'] . '&tab=bestanden'); exit; }

log_actie('bestand_gedownload', 'Bestand: ' . $b['originele_naam'] . ', Klant ID: ' . $b['klant_id']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($b['originele_naam']) . '"');
header('Content-Length: ' . filesize($pad));
readfile($pad);
exit;
