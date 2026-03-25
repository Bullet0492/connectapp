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

$bestaand = db()->prepare('SELECT id, admin_wachtwoord_enc FROM klant_simpbx WHERE klant_id = ?');
$bestaand->execute([$klant_id]);
$rij = $bestaand->fetch();

$velden = [
    'actief'           => isset($_POST['actief']) ? 1 : 0,
    'aantal_extensies' => (int)($_POST['aantal_extensies'] ?? 0),
    'sip_domein'       => trim($_POST['sip_domein'] ?? ''),
    'admin_url'        => trim($_POST['admin_url'] ?? ''),
    'admin_gebruiker'  => trim($_POST['admin_gebruiker'] ?? ''),
    'notities'         => trim($_POST['notities'] ?? ''),
];

if ($rij) {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : $rij['admin_wachtwoord_enc'];
    db()->prepare("UPDATE klant_simpbx SET actief=?, aantal_extensies=?, sip_domein=?, admin_url=?, admin_gebruiker=?, admin_wachtwoord_enc=?, notities=? WHERE klant_id=?")
       ->execute([$velden['actief'], $velden['aantal_extensies'], $velden['sip_domein'], $velden['admin_url'], $velden['admin_gebruiker'], $ww_enc, $velden['notities'], $klant_id]);
    log_actie('simpbx_bijgewerkt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Simpbx gegevens bijgewerkt.');
} else {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : null;
    db()->prepare("INSERT INTO klant_simpbx (klant_id, actief, aantal_extensies, sip_domein, admin_url, admin_gebruiker, admin_wachtwoord_enc, notities) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$klant_id, $velden['actief'], $velden['aantal_extensies'], $velden['sip_domein'], $velden['admin_url'], $velden['admin_gebruiker'], $ww_enc, $velden['notities']]);
    log_actie('simpbx_aangemaakt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Simpbx gegevens opgeslagen.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=telefonie');
exit;
