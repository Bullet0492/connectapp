<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id     = (int)($_POST['klant_id'] ?? 0);
$gebruiker_id = (int)($_POST['gebruiker_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$ww_nieuw = $_POST['wachtwoord'] ?? '';

$velden = [
    'naam'          => trim($_POST['naam'] ?? ''),
    'email'         => trim($_POST['email'] ?? ''),
    'rol'           => trim($_POST['rol'] ?? 'Gebruiker'),
    'licentie_type' => trim($_POST['licentie_type'] ?? ''),
    'notities'      => trim($_POST['notities'] ?? ''),
];

if ($gebruiker_id) {
    $bestaand = db()->prepare('SELECT wachtwoord_enc FROM klant_o365_gebruikers WHERE id=? AND klant_id=?');
    $bestaand->execute([$gebruiker_id, $klant_id]);
    $rij = $bestaand->fetch();
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : ($rij['wachtwoord_enc'] ?? null);
    db()->prepare('UPDATE klant_o365_gebruikers SET naam=?, email=?, wachtwoord_enc=?, rol=?, licentie_type=?, notities=? WHERE id=? AND klant_id=?')
       ->execute([$velden['naam'], $velden['email'], $ww_enc, $velden['rol'], $velden['licentie_type'], $velden['notities'], $gebruiker_id, $klant_id]);
    log_actie('o365_gebruiker_bijgewerkt', 'Klant ID: ' . $klant_id . ', gebruiker: ' . $velden['email']);
} else {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : null;
    db()->prepare('INSERT INTO klant_o365_gebruikers (klant_id, naam, email, wachtwoord_enc, rol, licentie_type, notities) VALUES (?,?,?,?,?,?,?)')
       ->execute([$klant_id, $velden['naam'], $velden['email'], $ww_enc, $velden['rol'], $velden['licentie_type'], $velden['notities']]);
    log_actie('o365_gebruiker_aangemaakt', 'Klant ID: ' . $klant_id . ', gebruiker: ' . $velden['email']);
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=o365');
exit;
