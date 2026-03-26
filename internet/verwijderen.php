<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

db()->prepare('DELETE FROM klant_internet WHERE klant_id=?')->execute([$klant_id]);

log_actie('internet_verwijderd', 'Klant ID: ' . $klant_id);
flash_set('succes', 'Internet aansluiting verwijderd.');
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=internet');
exit;
