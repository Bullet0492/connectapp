<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if (!$klant_id) {
    flash_set('fout', 'Ongeldig verzoek.');
    header('Location: ' . basis_url() . '/klanten/index.php');
    exit;
}

if (!isset($_FILES['bestand']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
    flash_set('fout', 'Upload mislukt of geen bestand geselecteerd.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=bestanden');
    exit;
}

$max_grootte = 10 * 1024 * 1024; // 10 MB
$toegestane_ext = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip','rar','txt','cfg','conf','p12','crt','pem'];

$originele_naam = $_FILES['bestand']['name'];
$ext = strtolower(pathinfo($originele_naam, PATHINFO_EXTENSION));
$grootte = $_FILES['bestand']['size'];

if (!in_array($ext, $toegestane_ext)) {
    flash_set('fout', 'Bestandstype niet toegestaan: .' . $ext);
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=bestanden');
    exit;
}

if ($grootte > $max_grootte) {
    flash_set('fout', 'Bestand is te groot (max. 10 MB).');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=bestanden');
    exit;
}

// Unieke bestandsnaam genereren
$unieke_naam = 'klant_' . $klant_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$upload_pad  = __DIR__ . '/../uploads/bestanden/' . $unieke_naam;

if (!move_uploaded_file($_FILES['bestand']['tmp_name'], $upload_pad)) {
    flash_set('fout', 'Opslaan mislukt.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=bestanden');
    exit;
}

$user = huidig_gebruiker();
db()->prepare("INSERT INTO klant_bestanden (klant_id,bestandsnaam,originele_naam,bestandstype,bestandsgrootte,aangemaakt_door) VALUES (?,?,?,?,?,?)")
   ->execute([$klant_id, $unieke_naam, $originele_naam, $ext, $grootte, $user['id']]);

log_actie('bestand_geupload', 'Bestand: ' . $originele_naam . ', Klant ID: ' . $klant_id);
flash_set('succes', 'Bestand geüpload: ' . $originele_naam);
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=bestanden');
exit;
