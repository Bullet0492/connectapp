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
$pppoe_gebruiker = trim($_POST['pppoe_gebruiker'] ?? '');
$pppoe_ww_nieuw  = $_POST['pppoe_wachtwoord'] ?? '';
$vlan_in         = trim($_POST['vlan_id'] ?? '');
$vlan_id         = $vlan_in !== '' ? (int)$vlan_in : null;

$bestaand = db()->prepare('SELECT id, pppoe_wachtwoord_enc FROM klant_internet WHERE klant_id = ?');
$bestaand->execute([$klant_id]);
$rij = $bestaand->fetch();

// Wachtwoord leeg = bestaande encrypted waarde behouden
$pppoe_ww_enc = $pppoe_ww_nieuw !== '' ? encrypt_wachtwoord($pppoe_ww_nieuw) : ($rij['pppoe_wachtwoord_enc'] ?? null);

if ($rij) {
    db()->prepare("UPDATE klant_internet SET provider=?, provider_anders=?, type=?, snelheid_down=?, snelheid_up=?, ip_adres=?, backup_4g=?, contract_datum=?, notities=?, pppoe_gebruiker=?, pppoe_wachtwoord_enc=?, vlan_id=? WHERE klant_id=?")
       ->execute([$provider, $provider_anders, $type, $snelheid_down, $snelheid_up, $ip_adres, $backup_4g, $contract_datum, $notities, $pppoe_gebruiker ?: null, $pppoe_ww_enc, $vlan_id, $klant_id]);
    log_actie('internet_bijgewerkt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Internet gegevens bijgewerkt.');
} else {
    db()->prepare("INSERT INTO klant_internet (klant_id, provider, provider_anders, type, snelheid_down, snelheid_up, ip_adres, backup_4g, contract_datum, notities, pppoe_gebruiker, pppoe_wachtwoord_enc, vlan_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$klant_id, $provider, $provider_anders, $type, $snelheid_down, $snelheid_up, $ip_adres, $backup_4g, $contract_datum, $notities, $pppoe_gebruiker ?: null, $pppoe_ww_enc, $vlan_id]);
    log_actie('internet_aangemaakt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Internet gegevens opgeslagen.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=internet');
exit;
