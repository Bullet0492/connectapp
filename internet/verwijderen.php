<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
csrf_check();

$klant_id    = (int)($_POST['klant_id'] ?? 0);
$internet_id = (int)($_POST['internet_id'] ?? 0);
if (!$klant_id || !$internet_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

// Was deze rij primair?
$st = db()->prepare('SELECT is_primair FROM klant_internet WHERE id = ? AND klant_id = ?');
$st->execute([$internet_id, $klant_id]);
$was_primair = (int)($st->fetchColumn() ?: 0) === 1;

db()->prepare('DELETE FROM klant_internet WHERE id = ? AND klant_id = ?')->execute([$internet_id, $klant_id]);

// Als de primaire verbinding net is verwijderd: maak de eerste resterende primair
if ($was_primair) {
    $st2 = db()->prepare('SELECT id FROM klant_internet WHERE klant_id = ? ORDER BY id ASC LIMIT 1');
    $st2->execute([$klant_id]);
    $nieuwe_primair = $st2->fetchColumn();
    if ($nieuwe_primair) {
        db()->prepare('UPDATE klant_internet SET is_primair = 1 WHERE id = ?')->execute([$nieuwe_primair]);
    }
}

log_actie('internet_verwijderd', 'Klant ID: ' . $klant_id . ' / verbinding ' . $internet_id);
flash_set('succes', 'Internet verbinding verwijderd.');
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=internet');
exit;
