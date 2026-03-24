<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id   = (int)($_POST['klant_id'] ?? 0);
$notitie_id = (int)($_POST['notitie_id'] ?? 0);
$titel      = trim($_POST['titel'] ?? '');

if (!$klant_id || !$titel) {
    flash_set('fout', 'Titel is verplicht.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=notities');
    exit;
}

$user = huidig_gebruiker();

if ($notitie_id) {
    db()->prepare("UPDATE klant_notities SET titel=?, inhoud=? WHERE id=? AND klant_id=?")
       ->execute([$titel, trim($_POST['inhoud'] ?? ''), $notitie_id, $klant_id]);
    log_actie('notitie_bijgewerkt', 'Titel: ' . $titel . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Notitie bijgewerkt.');
} else {
    db()->prepare("INSERT INTO klant_notities (klant_id,titel,inhoud,aangemaakt_door) VALUES (?,?,?,?)")
       ->execute([$klant_id, $titel, trim($_POST['inhoud'] ?? ''), $user['id']]);
    log_actie('notitie_aangemaakt', 'Titel: ' . $titel . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Notitie toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=notities');
exit;
