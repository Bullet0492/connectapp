<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
$lic_id   = (int)($_POST['licentie_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$type  = trim($_POST['licentie_type'] ?? '');
$aantal = (int)($_POST['aantal'] ?? 1);

if ($lic_id) {
    db()->prepare('UPDATE klant_o365_licenties SET licentie_type=?, aantal=? WHERE id=? AND klant_id=?')
       ->execute([$type, $aantal, $lic_id, $klant_id]);
} else {
    db()->prepare('INSERT INTO klant_o365_licenties (klant_id, licentie_type, aantal) VALUES (?,?,?)')
       ->execute([$klant_id, $type, $aantal]);
}

log_actie('o365_licentie_opgeslagen', 'Klant ID: ' . $klant_id);
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=o365');
exit;
