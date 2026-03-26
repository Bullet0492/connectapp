<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id   = (int)($_POST['klant_id'] ?? 0);
$service_id = (int)($_POST['service_id'] ?? 0);
$datum      = trim($_POST['datum'] ?? '');
$omschrijving = trim($_POST['omschrijving'] ?? '');

if (!$klant_id || !$datum || !$omschrijving) {
    flash_set('fout', 'Datum en omschrijving zijn verplicht.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=service');
    exit;
}

$user = huidig_gebruiker();
$apparaat_id = (int)($_POST['apparaat_id'] ?? 0) ?: null;

$data_update = [
    'klant_id'      => $klant_id,
    'apparaat_id'   => $apparaat_id,
    'datum'         => $datum,
    'type'          => trim($_POST['type'] ?? 'bezoek'),
    'omschrijving'  => $omschrijving,
    'opgelost_door' => trim($_POST['opgelost_door'] ?? ''),
];
$data = array_merge($data_update, ['aangemaakt_door' => $user['id']]);

if ($service_id) {
    db()->prepare("UPDATE service_historie SET datum=:datum, type=:type, apparaat_id=:apparaat_id, omschrijving=:omschrijving, opgelost_door=:opgelost_door WHERE id=:id AND klant_id=:klant_id")
       ->execute(array_merge($data_update, ['id' => $service_id]));
    log_actie('service_bijgewerkt', 'Klant ID: ' . $klant_id . ', datum: ' . $datum);
    flash_set('succes', 'Service-entry bijgewerkt.');
} else {
    db()->prepare("INSERT INTO service_historie (klant_id,apparaat_id,datum,type,omschrijving,opgelost_door,aangemaakt_door) VALUES (:klant_id,:apparaat_id,:datum,:type,:omschrijving,:opgelost_door,:aangemaakt_door)")
       ->execute($data);
    log_actie('service_aangemaakt', 'Klant ID: ' . $klant_id . ', type: ' . $data['type']);
    flash_set('succes', 'Service-entry toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=service');
exit;
