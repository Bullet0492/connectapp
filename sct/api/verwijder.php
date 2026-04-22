<?php
/**
 * SCT API — handmatig verwijderen door afzender.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/sct_functions.php';

sessie_start();
vereist_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Methode niet toegestaan.');
}

$id = trim((string)($_POST['id'] ?? ''));
$gebruiker = huidig_gebruiker();

if (!preg_match('/^[a-z0-9]{24}$/i', $id)) {
    flash_set('fout', 'Ongeldige bericht-ID.');
    header('Location: ../overzicht.php');
    exit;
}

$stmt = db()->prepare('DELETE FROM sct_secrets WHERE id = ? AND sender_user_id = ?');
$stmt->execute([$id, $gebruiker['id']]);

if ($stmt->rowCount() > 0) {
    $pad = sct_storage_pad($id);
    if (is_file($pad)) @unlink($pad);
    sct_log($id, 'verlopen');
    log_actie('sct_ingetrokken', 'id=' . $id);
    flash_set('succes', 'Bericht ingetrokken. De link is direct ongeldig.');
} else {
    flash_set('fout', 'Bericht niet gevonden of al verwijderd.');
}

header('Location: ../overzicht.php');
exit;
