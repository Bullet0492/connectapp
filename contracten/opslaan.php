<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id    = (int)($_POST['klant_id'] ?? 0);
$contract_id = (int)($_POST['contract_id'] ?? 0);
$omschrijving = trim($_POST['omschrijving'] ?? '');

if (!$klant_id || !$omschrijving) {
    flash_set('fout', 'Omschrijving is verplicht.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=contract');
    exit;
}

$data = [
    'klant_id'    => $klant_id,
    'omschrijving'=> $omschrijving,
    'sla_niveau'  => trim($_POST['sla_niveau'] ?? 'standaard'),
    'start_datum' => trim($_POST['start_datum'] ?? '') ?: null,
    'eind_datum'  => trim($_POST['eind_datum'] ?? '') ?: null,
    'notities'    => trim($_POST['notities'] ?? ''),
];

if ($contract_id) {
    db()->prepare("UPDATE contracten SET omschrijving=:omschrijving, sla_niveau=:sla_niveau, start_datum=:start_datum, eind_datum=:eind_datum, notities=:notities WHERE id=:id AND klant_id=:klant_id")
       ->execute(array_merge($data, ['id' => $contract_id]));
    log_actie('contract_bijgewerkt', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Contract bijgewerkt.');
} else {
    db()->prepare("INSERT INTO contracten (klant_id,omschrijving,sla_niveau,start_datum,eind_datum,notities) VALUES (:klant_id,:omschrijving,:sla_niveau,:start_datum,:eind_datum,:notities)")
       ->execute($data);
    log_actie('contract_aangemaakt', 'Klant ID: ' . $klant_id . ', SLA: ' . $data['sla_niveau']);
    flash_set('succes', 'Contract toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=contract');
exit;
