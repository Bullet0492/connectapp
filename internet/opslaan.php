<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$provider        = trim($_POST['provider'] ?? '');
$provider_anders = trim($_POST['provider_anders'] ?? '');
$type            = trim($_POST['type'] ?? '');
$snelheid_down   = trim($_POST['snelheid_down'] ?? '');
$snelheid_up     = trim($_POST['snelheid_up'] ?? '');
$ip_adres        = trim($_POST['ip_adres'] ?? '');
$backup_4g       = isset($_POST['backup_4g']) ? 1 : 0;
$contract_datum  = trim($_POST['contract_datum'] ?? '') ?: null;
$notities        = trim($_POST['notities'] ?? '');
$wifi_ssid       = trim($_POST['wifi_ssid'] ?? '');
$wifi_ww_nieuw   = $_POST['wifi_wachtwoord'] ?? '';
$gast_ssid       = trim($_POST['gast_ssid'] ?? '');
$gast_ww_nieuw   = $_POST['gast_wachtwoord'] ?? '';

$bestaand = db()->prepare('SELECT id, wifi_wachtwoord_enc, gast_wachtwoord_enc FROM klant_internet WHERE klant_id = ?');
$bestaand->execute([$klant_id]);
$rij = $bestaand->fetch();

// Wachtwoord leeg = bestaande encrypted waarde behouden
$wifi_ww_enc = $wifi_ww_nieuw !== '' ? encrypt_wachtwoord($wifi_ww_nieuw) : ($rij['wifi_wachtwoord_enc'] ?? null);
$gast_ww_enc = $gast_ww_nieuw !== '' ? encrypt_wachtwoord($gast_ww_nieuw) : ($rij['gast_wachtwoord_enc'] ?? null);

if ($rij) {
    db()->prepare("UPDATE klant_internet SET provider=?, provider_anders=?, type=?, snelheid_down=?, snelheid_up=?, ip_adres=?, backup_4g=?, contract_datum=?, notities=?, wifi_ssid=?, wifi_wachtwoord_enc=?, gast_ssid=?, gast_wachtwoord_enc=? WHERE klant_id=?")
       ->execute([$provider, $provider_anders, $type, $snelheid_down, $snelheid_up, $ip_adres, $backup_4g, $contract_datum, $notities, $wifi_ssid ?: null, $wifi_ww_enc, $gast_ssid ?: null, $gast_ww_enc, $klant_id]);
    log_actie('internet_bijgewerkt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Internet gegevens bijgewerkt.');
} else {
    db()->prepare("INSERT INTO klant_internet (klant_id, provider, provider_anders, type, snelheid_down, snelheid_up, ip_adres, backup_4g, contract_datum, notities, wifi_ssid, wifi_wachtwoord_enc, gast_ssid, gast_wachtwoord_enc) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$klant_id, $provider, $provider_anders, $type, $snelheid_down, $snelheid_up, $ip_adres, $backup_4g, $contract_datum, $notities, $wifi_ssid ?: null, $wifi_ww_enc, $gast_ssid ?: null, $gast_ww_enc]);
    log_actie('internet_aangemaakt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Internet gegevens opgeslagen.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=internet');
exit;
