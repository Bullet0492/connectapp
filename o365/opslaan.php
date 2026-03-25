<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$ww_nieuw = $_POST['admin_wachtwoord'] ?? '';

$bestaand = db()->prepare('SELECT id, admin_wachtwoord_enc FROM klant_o365 WHERE klant_id = ?');
$bestaand->execute([$klant_id]);
$rij = $bestaand->fetch();

$velden = [
    'tenant_naam'  => trim($_POST['tenant_naam'] ?? ''),
    'tenant_id'    => trim($_POST['tenant_id'] ?? ''),
    'admin_email'  => trim($_POST['admin_email'] ?? ''),
    'notities'     => trim($_POST['notities'] ?? ''),
];

if ($rij) {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : $rij['admin_wachtwoord_enc'];
    db()->prepare("UPDATE klant_o365 SET tenant_naam=?, tenant_id=?, admin_email=?, admin_wachtwoord_enc=?, notities=? WHERE klant_id=?")
       ->execute([$velden['tenant_naam'], $velden['tenant_id'], $velden['admin_email'], $ww_enc, $velden['notities'], $klant_id]);
    log_actie('o365_bijgewerkt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Office 365 gegevens bijgewerkt.');
} else {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : null;
    db()->prepare("INSERT INTO klant_o365 (klant_id, tenant_naam, tenant_id, admin_email, admin_wachtwoord_enc, notities) VALUES (?,?,?,?,?,?)")
       ->execute([$klant_id, $velden['tenant_naam'], $velden['tenant_id'], $velden['admin_email'], $ww_enc, $velden['notities']]);
    log_actie('o365_aangemaakt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Office 365 gegevens opgeslagen.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=o365');
exit;
