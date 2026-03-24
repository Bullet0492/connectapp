<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $stmt = db()->prepare('SELECT naam FROM klanten WHERE id = ?');
    $stmt->execute([$id]);
    $klant = $stmt->fetch();
    if ($klant) {
        db()->prepare('DELETE FROM klanten WHERE id = ?')->execute([$id]);
        log_actie('klant_verwijderd', 'Naam: ' . $klant['naam'] . ', ID: ' . $id);
        flash_set('succes', 'Klant verwijderd.');
    }
}
header('Location: index.php');
exit;
