<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);

if ($id) {
    db()->prepare('DELETE FROM service_historie WHERE id = ?')->execute([$id]);
    log_actie('service_verwijderd', 'ID: ' . $id . ', Klant ID: ' . $klant_id);
    flash_set('succes', 'Service-entry verwijderd.');
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=service');
exit;
