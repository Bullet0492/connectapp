<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id   = (int)($_POST['klant_id'] ?? 0);
$yeastar_id = (int)($_POST['yeastar_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$ww_nieuw = $_POST['admin_wachtwoord'] ?? '';

$velden = [
    'model'           => trim($_POST['model'] ?? ''),
    'ip_adres'        => trim($_POST['ip_adres'] ?? ''),
    'poort'           => trim($_POST['poort'] ?? '8088'),
    'firmware'        => trim($_POST['firmware'] ?? ''),
    'admin_url'       => trim($_POST['admin_url'] ?? ''),
    'admin_gebruiker' => trim($_POST['admin_gebruiker'] ?? 'admin'),
    'notities'        => trim($_POST['notities'] ?? ''),
];

if ($yeastar_id) {
    $bestaand = db()->prepare('SELECT admin_wachtwoord_enc FROM klant_yeastar WHERE id = ? AND klant_id = ?');
    $bestaand->execute([$yeastar_id, $klant_id]);
    $rij = $bestaand->fetch();
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : ($rij['admin_wachtwoord_enc'] ?? null);
    db()->prepare("UPDATE klant_yeastar SET model=?, ip_adres=?, poort=?, firmware=?, admin_url=?, admin_gebruiker=?, admin_wachtwoord_enc=?, notities=? WHERE id=? AND klant_id=?")
       ->execute([$velden['model'], $velden['ip_adres'], $velden['poort'], $velden['firmware'], $velden['admin_url'], $velden['admin_gebruiker'], $ww_enc, $velden['notities'], $yeastar_id, $klant_id]);
    log_actie('yeastar_bijgewerkt', 'ID: ' . $yeastar_id);
    flash_set('succes', 'Yeastar centrale bijgewerkt.');
} else {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : null;
    db()->prepare("INSERT INTO klant_yeastar (klant_id, model, ip_adres, poort, firmware, admin_url, admin_gebruiker, admin_wachtwoord_enc, notities) VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$klant_id, $velden['model'], $velden['ip_adres'], $velden['poort'], $velden['firmware'], $velden['admin_url'], $velden['admin_gebruiker'], $ww_enc, $velden['notities']]);
    log_actie('yeastar_aangemaakt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Yeastar centrale toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=telefonie');
exit;
