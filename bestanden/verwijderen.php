<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$id       = (int)($_GET['id'] ?? 0);
$klant_id = (int)($_GET['klant_id'] ?? 0);

if ($id) {
    $stmt = db()->prepare('SELECT * FROM klant_bestanden WHERE id = ?');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if ($b) {
        $pad = __DIR__ . '/../uploads/bestanden/' . $b['bestandsnaam'];
        if (file_exists($pad)) unlink($pad);
        db()->prepare('DELETE FROM klant_bestanden WHERE id = ?')->execute([$id]);
        log_actie('bestand_verwijderd', 'Bestand: ' . $b['originele_naam'] . ', Klant ID: ' . $klant_id);
        flash_set('succes', 'Bestand verwijderd.');
    }
}
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=bestanden');
exit;
