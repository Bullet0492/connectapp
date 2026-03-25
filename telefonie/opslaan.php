<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$type = trim($_POST['type'] ?? 'geen'); // 'yeastar', 'simpbx', 'geen'

if ($type === 'yeastar') {
    $admin_url      = trim($_POST['admin_url'] ?? '');
    $admin_gebruiker = trim($_POST['admin_gebruiker'] ?? '');
    $ww_nieuw       = $_POST['admin_wachtwoord'] ?? '';

    // Deactiveer simpbx
    db()->prepare('UPDATE klant_simpbx SET actief=0 WHERE klant_id=?')->execute([$klant_id]);

    // Yeastar: één record per klant
    $bestaand = db()->prepare('SELECT id, admin_wachtwoord_enc FROM klant_yeastar WHERE klant_id=? ORDER BY id LIMIT 1');
    $bestaand->execute([$klant_id]);
    $rij = $bestaand->fetch();
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : ($rij['admin_wachtwoord_enc'] ?? null);

    if ($rij) {
        db()->prepare('UPDATE klant_yeastar SET admin_url=?, admin_gebruiker=?, admin_wachtwoord_enc=? WHERE id=?')
           ->execute([$admin_url, $admin_gebruiker, $ww_enc, $rij['id']]);
        // Verwijder eventuele extra rijen
        db()->prepare('DELETE FROM klant_yeastar WHERE klant_id=? AND id != ?')->execute([$klant_id, $rij['id']]);
    } else {
        db()->prepare('INSERT INTO klant_yeastar (klant_id, admin_url, admin_gebruiker, admin_wachtwoord_enc) VALUES (?,?,?,?)')
           ->execute([$klant_id, $admin_url, $admin_gebruiker, $ww_enc]);
    }
    log_actie('telefonie_yeastar_opgeslagen', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Yeastar gegevens opgeslagen.');

} elseif ($type === 'simpbx') {
    // Verwijder yeastar records
    db()->prepare('DELETE FROM klant_yeastar WHERE klant_id=?')->execute([$klant_id]);

    // Simpbx activeren
    $bestaand = db()->prepare('SELECT id FROM klant_simpbx WHERE klant_id=?');
    $bestaand->execute([$klant_id]);
    if ($bestaand->fetch()) {
        db()->prepare('UPDATE klant_simpbx SET actief=1 WHERE klant_id=?')->execute([$klant_id]);
    } else {
        db()->prepare('INSERT INTO klant_simpbx (klant_id, actief) VALUES (?,1)')->execute([$klant_id]);
    }
    log_actie('telefonie_simpbx_opgeslagen', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Simpbx ingesteld.');

} else {
    // Geen — alles deactiveren
    db()->prepare('DELETE FROM klant_yeastar WHERE klant_id=?')->execute([$klant_id]);
    db()->prepare('UPDATE klant_simpbx SET actief=0 WHERE klant_id=?')->execute([$klant_id]);
    log_actie('telefonie_verwijderd', 'Klant ID: ' . $klant_id);
    flash_set('succes', 'Telefonie verwijderd.');
}

header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=telefonie');
exit;
