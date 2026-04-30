<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id    = (int)($_POST['klant_id'] ?? 0);
$internet_id = (int)($_POST['internet_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$omschrijving    = trim($_POST['omschrijving'] ?? '');
$provider        = trim($_POST['provider'] ?? '');
$provider_anders = trim($_POST['provider_anders'] ?? '');
$type            = trim($_POST['type'] ?? '');
$snelheid_down   = trim($_POST['snelheid_down'] ?? '');
$snelheid_up     = trim($_POST['snelheid_up'] ?? '');
$ip_adres        = trim($_POST['ip_adres'] ?? '');
$backup_4g       = isset($_POST['backup_4g']) ? 1 : 0;
$is_primair      = isset($_POST['is_primair']) ? 1 : 0;
$contract_datum  = trim($_POST['contract_datum'] ?? '') ?: null;
$notities        = trim($_POST['notities'] ?? '');
$pppoe_gebruiker = trim($_POST['pppoe_gebruiker'] ?? '');
$pppoe_ww_nieuw  = $_POST['pppoe_wachtwoord'] ?? '';
$vlan_in         = trim($_POST['vlan_id'] ?? '');
$vlan_id         = $vlan_in !== '' ? (int)$vlan_in : null;

$rij = null;
if ($internet_id) {
    $st = db()->prepare('SELECT id, pppoe_wachtwoord_enc FROM klant_internet WHERE id = ? AND klant_id = ?');
    $st->execute([$internet_id, $klant_id]);
    $rij = $st->fetch();
}

// Wachtwoord leeg = bestaande encrypted waarde behouden
$pppoe_ww_enc = $pppoe_ww_nieuw !== '' ? encrypt_wachtwoord($pppoe_ww_nieuw) : ($rij['pppoe_wachtwoord_enc'] ?? null);

if ($rij) {
    db()->prepare("UPDATE klant_internet SET omschrijving=?, provider=?, provider_anders=?, type=?, snelheid_down=?, snelheid_up=?, ip_adres=?, backup_4g=?, is_primair=?, contract_datum=?, notities=?, pppoe_gebruiker=?, pppoe_wachtwoord_enc=?, vlan_id=? WHERE id=? AND klant_id=?")
       ->execute([$omschrijving ?: null, $provider, $provider_anders, $type, $snelheid_down, $snelheid_up, $ip_adres, $backup_4g, $is_primair, $contract_datum, $notities, $pppoe_gebruiker ?: null, $pppoe_ww_enc, $vlan_id, $internet_id, $klant_id]);
    log_actie('internet_bijgewerkt', 'Klant ID: ' . $klant_id . ' / verbinding ' . $internet_id);
    flash_set('succes', 'Internet verbinding bijgewerkt.');
    $opgeslagen_id = $internet_id;
} else {
    db()->prepare("INSERT INTO klant_internet (klant_id, omschrijving, provider, provider_anders, type, snelheid_down, snelheid_up, ip_adres, backup_4g, is_primair, contract_datum, notities, pppoe_gebruiker, pppoe_wachtwoord_enc, vlan_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$klant_id, $omschrijving ?: null, $provider, $provider_anders, $type, $snelheid_down, $snelheid_up, $ip_adres, $backup_4g, $is_primair, $contract_datum, $notities, $pppoe_gebruiker ?: null, $pppoe_ww_enc, $vlan_id]);
    $opgeslagen_id = (int)db()->lastInsertId();
    log_actie('internet_aangemaakt', 'Klant ID: ' . $klant_id . ' / verbinding ' . $opgeslagen_id);
    flash_set('succes', 'Internet verbinding toegevoegd.');
}

// Als deze rij primair is, zorg dat alle andere voor deze klant niet primair zijn
if ($is_primair && $opgeslagen_id) {
    db()->prepare("UPDATE klant_internet SET is_primair = 0 WHERE klant_id = ? AND id <> ?")
       ->execute([$klant_id, $opgeslagen_id]);
}

// Als er geen enkele primair is voor deze klant, maak deze primair
$check = db()->prepare("SELECT COUNT(*) FROM klant_internet WHERE klant_id = ? AND is_primair = 1");
$check->execute([$klant_id]);
if ((int)$check->fetchColumn() === 0 && $opgeslagen_id) {
    db()->prepare("UPDATE klant_internet SET is_primair = 1 WHERE id = ?")->execute([$opgeslagen_id]);
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=internet');
exit;
