<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id   = (int)($_POST['klant_id'] ?? 0);
$contact_id = (int)($_POST['contact_id'] ?? 0);
$naam       = trim($_POST['naam'] ?? '');

if (!$klant_id || !$naam) {
    flash_set('fout', 'Naam is verplicht.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=contacten');
    exit;
}

$data = [
    'klant_id' => $klant_id,
    'naam'     => $naam,
    'functie'  => trim($_POST['functie'] ?? ''),
    'email'    => trim($_POST['email'] ?? ''),
    'telefoon' => trim($_POST['telefoon'] ?? ''),
    'notities' => trim($_POST['notities'] ?? ''),
];

if ($contact_id) {
    db()->prepare("UPDATE contactpersonen SET naam=:naam, functie=:functie, email=:email, telefoon=:telefoon, notities=:notities WHERE id=:id AND klant_id=:klant_id")
       ->execute(array_merge($data, ['id' => $contact_id]));
    log_actie('contact_bijgewerkt', 'Naam: ' . $naam . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Contactpersoon bijgewerkt.');
} else {
    db()->prepare("INSERT INTO contactpersonen (klant_id,naam,functie,email,telefoon,notities) VALUES (:klant_id,:naam,:functie,:email,:telefoon,:notities)")
       ->execute($data);
    log_actie('contact_aangemaakt', 'Naam: ' . $naam . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Contactpersoon toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=contacten');
exit;
