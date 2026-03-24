<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);

if ($id) {
    $stmt = db()->prepare('SELECT label FROM inloggegevens WHERE id = ?');
    $stmt->execute([$id]);
    $ig = $stmt->fetch();
    if ($ig) {
        db()->prepare('DELETE FROM inloggegevens WHERE id = ?')->execute([$id]);
        log_actie('inloggegevens_verwijderd', 'Label: ' . $ig['label'] . ', Klant ID: ' . $klant_id);
        flash_set('succes', 'Inloggegevens verwijderd.');
    }
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=wachtwoorden');
exit;
