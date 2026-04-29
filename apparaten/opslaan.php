<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id    = (int)($_POST['klant_id'] ?? 0);
$apparaat_id = (int)($_POST['apparaat_id'] ?? 0);
$type        = trim($_POST['type'] ?? '');

if (!$klant_id || !$type) {
    flash_set('fout', 'Type is verplicht.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=apparaten');
    exit;
}

$data = [
    'klant_id'      => $klant_id,
    'type'          => $type,
    'merk'          => trim($_POST['merk'] ?? ''),
    'model'         => trim($_POST['model'] ?? ''),
    'serienummer'   => trim($_POST['serienummer'] ?? ''),
    'aanschafdatum' => trim($_POST['aanschafdatum'] ?? '') ?: null,
    'locatie'       => trim($_POST['locatie'] ?? ''),
    'mac_adres'     => trim($_POST['mac_adres'] ?? ''),
    'ip_adres'      => trim($_POST['ip_adres'] ?? ''),
    'firmware'      => trim($_POST['firmware'] ?? ''),
    'status'        => trim($_POST['status'] ?? 'actief'),
    'notities'      => trim($_POST['notities'] ?? ''),
    'wifi_ssid'     => trim($_POST['wifi_ssid'] ?? '') ?: null,
    'gast_ssid'     => trim($_POST['gast_ssid'] ?? '') ?: null,
];

// WiFi wachtwoorden: leeg = bestaande encrypted waarde behouden (bij update)
$wifi_ww_nieuw = $_POST['wifi_wachtwoord'] ?? '';
$gast_ww_nieuw = $_POST['gast_wachtwoord'] ?? '';
$bestaande_wifi_ww = null;
$bestaande_gast_ww = null;
if ($apparaat_id) {
    try {
        $b = db()->prepare('SELECT wifi_wachtwoord_enc, gast_wachtwoord_enc FROM apparaten WHERE id=? AND klant_id=?');
        $b->execute([$apparaat_id, $klant_id]);
        $bestaand = $b->fetch();
        if ($bestaand) {
            $bestaande_wifi_ww = $bestaand['wifi_wachtwoord_enc'] ?? null;
            $bestaande_gast_ww = $bestaand['gast_wachtwoord_enc'] ?? null;
        }
    } catch (PDOException $e) { /* migrate_wifi nog niet gedraaid */ }
}
$data['wifi_wachtwoord_enc'] = $wifi_ww_nieuw !== '' ? encrypt_wachtwoord($wifi_ww_nieuw) : $bestaande_wifi_ww;
$data['gast_wachtwoord_enc'] = $gast_ww_nieuw !== '' ? encrypt_wachtwoord($gast_ww_nieuw) : $bestaande_gast_ww;

if ($apparaat_id) {
    db()->prepare("UPDATE apparaten SET type=:type, merk=:merk, model=:model, serienummer=:serienummer,
        aanschafdatum=:aanschafdatum, locatie=:locatie, mac_adres=:mac_adres,
        ip_adres=:ip_adres, firmware=:firmware, status=:status, notities=:notities,
        wifi_ssid=:wifi_ssid, wifi_wachtwoord_enc=:wifi_wachtwoord_enc,
        gast_ssid=:gast_ssid, gast_wachtwoord_enc=:gast_wachtwoord_enc
        WHERE id=:id AND klant_id=:klant_id")
       ->execute(array_merge($data, ['id' => $apparaat_id]));
    log_actie('apparaat_bijgewerkt', 'Apparaat ID: ' . $apparaat_id . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Apparaat bijgewerkt.');
} else {
    $data['qr_code'] = volgende_qr_nummer();
    db()->prepare("INSERT INTO apparaten (klant_id,qr_code,type,merk,model,serienummer,aanschafdatum,locatie,mac_adres,ip_adres,firmware,status,notities,wifi_ssid,wifi_wachtwoord_enc,gast_ssid,gast_wachtwoord_enc)
        VALUES (:klant_id,:qr_code,:type,:merk,:model,:serienummer,:aanschafdatum,:locatie,:mac_adres,:ip_adres,:firmware,:status,:notities,:wifi_ssid,:wifi_wachtwoord_enc,:gast_ssid,:gast_wachtwoord_enc)")
       ->execute($data);
    log_actie('apparaat_aangemaakt', 'QR: ' . $data['qr_code'] . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Apparaat toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=apparaten');
exit;
