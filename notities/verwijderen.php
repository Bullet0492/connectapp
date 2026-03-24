<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);

if ($id) {
    $stmt = db()->prepare('SELECT titel FROM klant_notities WHERE id = ?');
    $stmt->execute([$id]);
    $n = $stmt->fetch();
    if ($n) {
        db()->prepare('DELETE FROM klant_notities WHERE id = ?')->execute([$id]);
        log_actie('notitie_verwijderd', 'Titel: ' . $n['titel'] . ', Klant ID: ' . $klant_id);
        flash_set('succes', 'Notitie verwijderd.');
    }
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=notities');
exit;
