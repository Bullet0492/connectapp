<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

sessie_start();
vereist_login();
csrf_check();

$klant_id = (int)($_POST['klant_id'] ?? 0);
if ($klant_id <= 0) {
    http_response_code(400);
    die('Geen klant opgegeven.');
}

$scanner        = trim($_POST['scanner'] ?? 'geen');
$scanner_anders = trim($_POST['scanner_anders'] ?? '');
$licentie       = trim($_POST['licentie'] ?? '');
$uninstall_code = trim($_POST['uninstall_code'] ?? '');
$vervaldatum    = trim($_POST['vervaldatum'] ?? '');
$notities       = trim($_POST['notities'] ?? '');

$toegestaan = ['geen','kaspersky','bitdefender','anders'];
if (!in_array($scanner, $toegestaan, true)) $scanner = 'geen';
if ($scanner !== 'anders') $scanner_anders = null;

// Encrypted velden: alleen opslaan als ze ingevuld zijn. Bij leeg: bestaande waarde behouden.
$db = db();
$bestaande = $db->prepare('SELECT licentie_encrypted, uninstall_code_encrypted FROM klant_virusscanner WHERE klant_id = ?');
$bestaande->execute([$klant_id]);
$oude = $bestaande->fetch() ?: ['licentie_encrypted' => null, 'uninstall_code_encrypted' => null];

$licentie_enc = $licentie !== '' ? encrypt_wachtwoord($licentie) : $oude['licentie_encrypted'];
$uninstall_enc = ($scanner === 'bitdefender' && $uninstall_code !== '') ? encrypt_wachtwoord($uninstall_code) : ($scanner === 'bitdefender' ? $oude['uninstall_code_encrypted'] : null);

$stmt = $db->prepare("
    INSERT INTO klant_virusscanner (klant_id, scanner, scanner_anders, licentie_encrypted, uninstall_code_encrypted, vervaldatum, notities)
    VALUES (:klant_id, :scanner, :scanner_anders, :licentie_encrypted, :uninstall_code_encrypted, :vervaldatum, :notities)
    ON DUPLICATE KEY UPDATE
        scanner = VALUES(scanner),
        scanner_anders = VALUES(scanner_anders),
        licentie_encrypted = VALUES(licentie_encrypted),
        uninstall_code_encrypted = VALUES(uninstall_code_encrypted),
        vervaldatum = VALUES(vervaldatum),
        notities = VALUES(notities)
");
$stmt->execute([
    ':klant_id'                => $klant_id,
    ':scanner'                 => $scanner,
    ':scanner_anders'          => $scanner_anders,
    ':licentie_encrypted'      => $licentie_enc,
    ':uninstall_code_encrypted' => $uninstall_enc,
    ':vervaldatum'             => $vervaldatum !== '' ? $vervaldatum : null,
    ':notities'                => $notities !== '' ? $notities : null,
]);

log_actie('virusscanner_opgeslagen', 'Klant ID: ' . $klant_id . ', scanner: ' . $scanner);
flash_set('succes', 'Virusscanner-gegevens opgeslagen.');
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $klant_id . '&tab=virusscanner');
exit;
