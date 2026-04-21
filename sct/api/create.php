<?php
/**
 * SCT API — aanmaken secret.
 * Auth vereist. Verwacht JSON body.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/sct_functions.php';

header('Content-Type: application/json; charset=utf-8');

sessie_start();
if (!is_ingelogd()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'fout' => 'Niet ingelogd.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'fout' => 'Methode niet toegestaan.']);
    exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige JSON.']);
    exit;
}

// CSRF (zelfde token als in sessie)
if (!hash_equals(csrf_token(), (string)($in['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'fout' => 'CSRF-fout. Vernieuw de pagina.']);
    exit;
}

$ciphertext = (string)($in['ciphertext'] ?? '');
$iv         = (string)($in['iv'] ?? '');
$retentie   = (int)($in['retentie_uren'] ?? 0);
$wachtwoord = $in['wachtwoord'] ?? null;
$notify     = trim((string)($in['notify_email'] ?? ''));

// Validatie
if ($ciphertext === '' || strlen($ciphertext) > SCT_MAX_CIPHERTEXT) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ciphertext ontbreekt of te groot.']);
    exit;
}
if ($iv === '' || strlen($iv) > 64) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'IV ongeldig.']);
    exit;
}
if (!array_key_exists($retentie, SCT_RETENTIE_OPTIES)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige bewaartermijn.']);
    exit;
}
if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Notificatie-mailadres ongeldig.']);
    exit;
}

$heeft_ww = is_string($wachtwoord) && $wachtwoord !== '';
$ww_hash  = $heeft_ww ? password_hash($wachtwoord, PASSWORD_DEFAULT) : null;

$gebruiker = huidig_gebruiker();

// Afzender-email ophalen uit users tabel voor notificaties naar verzender (toekomstig gebruik)
$sender_email = null;
try {
    $s = db()->prepare('SELECT email FROM users WHERE id = ?');
    $s->execute([$gebruiker['id']]);
    $sender_email = $s->fetchColumn() ?: null;
} catch (PDOException $e) {
    // ignore
}

$id = sct_genereer_id();
$aangemaakt = date('Y-m-d H:i:s');
$verloopt   = date('Y-m-d H:i:s', time() + $retentie * 3600);

try {
    db()->prepare(
        'INSERT INTO sct_secrets
            (id, ciphertext, iv, has_password, password_hash, sender_user_id, sender_naam, sender_email,
             notify_email, retentie_uren, aangemaakt_op, verloopt_op)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id, $ciphertext, $iv, $heeft_ww ? 1 : 0, $ww_hash,
        $gebruiker['id'], $gebruiker['naam'], $sender_email,
        $notify !== '' ? $notify : null, $retentie, $aangemaakt, $verloopt,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'fout' => 'Opslaan mislukt.']);
    exit;
}

sct_log($id, 'aangemaakt');
log_actie('sct_aangemaakt', "id={$id}, retentie={$retentie}u, wachtwoord=" . ($heeft_ww ? 'ja' : 'nee'));

echo json_encode([
    'ok'          => true,
    'id'          => $id,
    'verloopt_op' => date('d-m-Y H:i', strtotime($verloopt)),
    'base_url'    => rtrim(BASE_URL, '/'),
]);
