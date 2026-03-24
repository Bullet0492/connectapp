<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);

if ($id) {
    $stmt = db()->prepare('SELECT qr_code FROM apparaten WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if ($app) {
        db()->prepare('DELETE FROM apparaten WHERE id = ?')->execute([$id]);
        log_actie('apparaat_verwijderd', 'QR: ' . $app['qr_code']);
        flash_set('succes', 'Apparaat verwijderd.');
    }
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=apparaten');
exit;
