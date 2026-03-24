<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);

if ($id) {
    $stmt = db()->prepare('SELECT naam FROM contactpersonen WHERE id = ?');
    $stmt->execute([$id]);
    $contact = $stmt->fetch();
    if ($contact) {
        db()->prepare('DELETE FROM contactpersonen WHERE id = ?')->execute([$id]);
        log_actie('contact_verwijderd', 'Naam: ' . $contact['naam']);
        flash_set('succes', 'Contactpersoon verwijderd.');
    }
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=contacten');
exit;
