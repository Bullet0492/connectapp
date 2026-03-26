<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$klant_id = (int)($_GET['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$o365 = db()->prepare('SELECT id FROM klant_o365 WHERE klant_id = ?');
$o365->execute([$klant_id]);
$rij = $o365->fetch();

if ($rij) {
    db()->prepare('DELETE FROM klant_o365_gebruikers WHERE klant_id = ?')->execute([$klant_id]);
    db()->prepare('DELETE FROM klant_o365_licenties WHERE klant_id = ?')->execute([$klant_id]);
    db()->prepare('DELETE FROM klant_o365 WHERE klant_id = ?')->execute([$klant_id]);
    log_actie('o365_verwijderd', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Office 365 gegevens verwijderd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=o365');
exit;
