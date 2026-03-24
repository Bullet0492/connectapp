<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
$ig_id    = (int)($_POST['ig_id'] ?? 0);
$label    = trim($_POST['label'] ?? '');

if (!$klant_id || !$label) {
    flash_set('fout', 'Label is verplicht.');
    header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=wachtwoorden');
    exit;
}

$ww_nieuw = $_POST['wachtwoord'] ?? '';
$user = huidig_gebruiker();

if ($ig_id) {
    // Bewerken: wachtwoord alleen bijwerken als nieuw wachtwoord ingevuld
    if ($ww_nieuw !== '') {
        $ww_enc = encrypt_wachtwoord($ww_nieuw);
        db()->prepare("UPDATE inloggegevens SET categorie=?, label=?, gebruikersnaam=?, wachtwoord_enc=?, url=?, notities=? WHERE id=? AND klant_id=?")
           ->execute([
               $_POST['categorie'] ?? 'overig',
               $label,
               trim($_POST['gebruikersnaam'] ?? ''),
               $ww_enc,
               trim($_POST['url'] ?? ''),
               trim($_POST['notities'] ?? ''),
               $ig_id,
               $klant_id,
           ]);
    } else {
        db()->prepare("UPDATE inloggegevens SET categorie=?, label=?, gebruikersnaam=?, url=?, notities=? WHERE id=? AND klant_id=?")
           ->execute([
               $_POST['categorie'] ?? 'overig',
               $label,
               trim($_POST['gebruikersnaam'] ?? ''),
               trim($_POST['url'] ?? ''),
               trim($_POST['notities'] ?? ''),
               $ig_id,
               $klant_id,
           ]);
    }
    log_actie('inloggegevens_bijgewerkt', 'Label: ' . $label . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Inloggegevens bijgewerkt.');
} else {
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : null;
    db()->prepare("INSERT INTO inloggegevens (klant_id,categorie,label,gebruikersnaam,wachtwoord_enc,url,notities,aangemaakt_door)
        VALUES (?,?,?,?,?,?,?,?)")
       ->execute([
           $klant_id,
           $_POST['categorie'] ?? 'overig',
           $label,
           trim($_POST['gebruikersnaam'] ?? ''),
           $ww_enc,
           trim($_POST['url'] ?? ''),
           trim($_POST['notities'] ?? ''),
           $user['id'],
       ]);
    log_actie('inloggegevens_aangemaakt', 'Label: ' . $label . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Inloggegevens toegevoegd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=wachtwoorden');
exit;
