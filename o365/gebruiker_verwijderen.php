<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);
if ($id && $klant_id) {
    db()->prepare('DELETE FROM klant_o365_gebruikers WHERE id=? AND klant_id=?')->execute([$id, $klant_id]);
    log_actie('o365_gebruiker_verwijderd', 'ID: ' . $id);
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=o365');
exit;
