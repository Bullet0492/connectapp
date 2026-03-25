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
    'garantie_tot'  => trim($_POST['garantie_tot'] ?? '') ?: null,
    'locatie'       => trim($_POST['locatie'] ?? ''),
    'mac_adres'     => trim($_POST['mac_adres'] ?? ''),
    'ip_adres'      => trim($_POST['ip_adres'] ?? ''),
    'firmware'      => trim($_POST['firmware'] ?? ''),
    'status'        => trim($_POST['status'] ?? 'actief'),
    'notities'      => trim($_POST['notities'] ?? ''),
];

if ($apparaat_id) {
    db()->prepare("UPDATE apparaten SET type=:type, merk=:merk, model=:model, serienummer=:serienummer,
        aanschafdatum=:aanschafdatum, garantie_tot=:garantie_tot, locatie=:locatie, mac_adres=:mac_adres,
        ip_adres=:ip_adres, firmware=:firmware, status=:status, notities=:notities
        WHERE id=:id AND klant_id=:klant_id")
       ->execute(array_merge($data, ['id' => $apparaat_id]));
    log_actie('apparaat_bijgewerkt', 'Apparaat ID: ' . $apparaat_id . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Apparaat bijgewerkt.');
} else {
    $data['qr_code'] = volgende_qr_nummer();
    db()->prepare("INSERT INTO apparaten (klant_id,qr_code,type,merk,model,serienummer,aanschafdatum,garantie_tot,locatie,mac_adres,ip_adres,firmware,status,notities)
        VALUES (:klant_id,:qr_code,:type,:merk,:model,:serienummer,:aanschafdatum,:garantie_tot,:locatie,:mac_adres,:ip_adres,:firmware,:status,:notities)")
       ->execute($data);
    log_actie('apparaat_aangemaakt', 'QR: ' . $data['qr_code'] . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Apparaat toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=apparaten');
exit;
