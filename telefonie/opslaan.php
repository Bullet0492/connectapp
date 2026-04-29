<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$heeft_yeastar = isset($_POST['heeft_yeastar']);
$heeft_simpbx  = isset($_POST['heeft_simpbx']);
$heeft_ziggo   = isset($_POST['heeft_ziggo']);
$heeft_routit  = isset($_POST['heeft_routit']);
$simpbx_naam   = trim($_POST['simpbx_naam'] ?? '');
$ziggo_naam    = trim($_POST['ziggo_naam'] ?? '');
$routit_naam   = trim($_POST['routit_naam'] ?? '');

// ─── Yeastar ─────────────────────────────────────────────────────────────────
if ($heeft_yeastar) {
    $admin_url       = trim($_POST['admin_url'] ?? '');
    $admin_gebruiker = trim($_POST['admin_gebruiker'] ?? '');
    $ww_nieuw        = $_POST['admin_wachtwoord'] ?? '';

    $bestaand = db()->prepare('SELECT id, admin_wachtwoord_enc FROM klant_yeastar WHERE klant_id=? ORDER BY id LIMIT 1');
    $bestaand->execute([$klant_id]);
    $rij = $bestaand->fetch();
    $ww_enc = $ww_nieuw !== '' ? encrypt_wachtwoord($ww_nieuw) : ($rij['admin_wachtwoord_enc'] ?? null);

    if ($rij) {
        db()->prepare('UPDATE klant_yeastar SET admin_url=?, admin_gebruiker=?, admin_wachtwoord_enc=? WHERE id=?')
           ->execute([$admin_url, $admin_gebruiker, $ww_enc, $rij['id']]);
        db()->prepare('DELETE FROM klant_yeastar WHERE klant_id=? AND id != ?')->execute([$klant_id, $rij['id']]);
    } else {
        db()->prepare('INSERT INTO klant_yeastar (klant_id, admin_url, admin_gebruiker, admin_wachtwoord_enc) VALUES (?,?,?,?)')
           ->execute([$klant_id, $admin_url, $admin_gebruiker, $ww_enc]);
    }
} else {
    db()->prepare('DELETE FROM klant_yeastar WHERE klant_id=?')->execute([$klant_id]);
}

// ─── Simpbx ──────────────────────────────────────────────────────────────────
if ($heeft_simpbx) {
    $bestaand = db()->prepare('SELECT id FROM klant_simpbx WHERE klant_id=?');
    $bestaand->execute([$klant_id]);
    if ($bestaand->fetch()) {
        db()->prepare('UPDATE klant_simpbx SET actief=1, naam=? WHERE klant_id=?')->execute([$simpbx_naam ?: null, $klant_id]);
    } else {
        db()->prepare('INSERT INTO klant_simpbx (klant_id, actief, naam) VALUES (?,1,?)')->execute([$klant_id, $simpbx_naam ?: null]);
    }
} else {
    db()->prepare('UPDATE klant_simpbx SET actief=0 WHERE klant_id=?')->execute([$klant_id]);
}

// ─── Ziggo ───────────────────────────────────────────────────────────────────
if ($heeft_ziggo) {
    $bestaand = db()->prepare('SELECT id FROM klant_ziggo WHERE klant_id=?');
    $bestaand->execute([$klant_id]);
    if ($bestaand->fetch()) {
        db()->prepare('UPDATE klant_ziggo SET actief=1, naam=? WHERE klant_id=?')->execute([$ziggo_naam ?: null, $klant_id]);
    } else {
        db()->prepare('INSERT INTO klant_ziggo (klant_id, actief, naam) VALUES (?,1,?)')->execute([$klant_id, $ziggo_naam ?: null]);
    }
} else {
    db()->prepare('UPDATE klant_ziggo SET actief=0 WHERE klant_id=?')->execute([$klant_id]);
}

// ─── RoutIT ──────────────────────────────────────────────────────────────────
if ($heeft_routit) {
    $bestaand = db()->prepare('SELECT id FROM klant_routit WHERE klant_id=?');
    $bestaand->execute([$klant_id]);
    if ($bestaand->fetch()) {
        db()->prepare('UPDATE klant_routit SET actief=1, naam=? WHERE klant_id=?')->execute([$routit_naam ?: null, $klant_id]);
    } else {
        db()->prepare('INSERT INTO klant_routit (klant_id, actief, naam) VALUES (?,1,?)')->execute([$klant_id, $routit_naam ?: null]);
    }
} else {
    db()->prepare('UPDATE klant_routit SET actief=0 WHERE klant_id=?')->execute([$klant_id]);
}

log_actie('telefonie_opgeslagen', 'Klant ID: ' . $klant_id);
flash_set('succes', 'Telefonie opgeslagen.');
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=telefonie');
exit;
