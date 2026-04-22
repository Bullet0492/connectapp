<?php
/**
 * SCT API — aanmaken secret (tekst of bestand).
 * Auth vereist. Accepteert JSON (tekst) of multipart/form-data (bestand).
 */
header('Content-Type: application/json; charset=utf-8');

// Vangnet: elke fatal error / exception → JSON response
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'fout' => 'Serverfout: ' . $e->getMessage()]);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) http_response_code(500);
        echo json_encode(['ok' => false, 'fout' => 'Fatale fout: ' . $err['message']]);
    }
});

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/sct_functions.php';

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

// Bepaal type op basis van content-type
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$is_multipart = stripos($content_type, 'multipart/form-data') !== false;

if ($is_multipart) {
    $in = $_POST;
    $type = 'file';
} else {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Ongeldige JSON.']);
        exit;
    }
    $type = (string)($in['type'] ?? 'text');
}

// CSRF
if (!hash_equals(csrf_token(), (string)($in['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'fout' => 'CSRF-fout. Vernieuw de pagina.']);
    exit;
}

$retentie   = (int)($in['retentie_uren'] ?? 0);
$wachtwoord = $in['wachtwoord'] ?? null;
$notify     = trim((string)($in['notify_email'] ?? ''));

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

$sender_email = null;
try {
    $s = db()->prepare('SELECT email FROM users WHERE id = ?');
    $s->execute([$gebruiker['id']]);
    $sender_email = $s->fetchColumn() ?: null;
} catch (PDOException $e) {
    // ignore
}

$id         = sct_genereer_id();
$aangemaakt = date('Y-m-d H:i:s');
$verloopt   = date('Y-m-d H:i:s', time() + $retentie * 3600);

if ($type === 'file') {
    // ── Bestand branch ────────────────────────────────────────────────────
    $iv       = (string)($in['iv'] ?? '');
    $meta_ct  = (string)($in['meta_ct'] ?? '');
    $meta_iv  = (string)($in['meta_iv'] ?? '');
    $mimetype = substr((string)($in['mimetype'] ?? 'application/octet-stream'), 0, 150);
    $orig_bytes = (int)($in['origineel_bytes'] ?? 0);

    if ($iv === '' || strlen($iv) > 64) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'IV ongeldig.']);
        exit;
    }
    if ($meta_ct === '' || strlen($meta_ct) > 4096) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Metadata ongeldig.']);
        exit;
    }
    if ($meta_iv === '' || strlen($meta_iv) > 64) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Metadata IV ongeldig.']);
        exit;
    }
    if ($orig_bytes <= 0 || $orig_bytes > SCT_MAX_BESTAND) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Bestand te groot of ongeldige grootte.']);
        exit;
    }

    if (!isset($_FILES['bestand']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['bestand']['error'] ?? 'ontbreekt';
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Upload mislukt (' . $err . ').']);
        exit;
    }

    // AES-GCM voegt 16 bytes tag toe → ciphertext = plaintext + 16
    $max_ct_bytes = SCT_MAX_BESTAND + 64;
    if ($_FILES['bestand']['size'] > $max_ct_bytes) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Versleuteld bestand te groot.']);
        exit;
    }

    // Opslaan op disk
    $opslag_dir = sct_storage_dir();
    if (!is_dir($opslag_dir)) {
        @mkdir($opslag_dir, 0700, true);
    }
    if (!is_writable($opslag_dir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'fout' => 'Opslaglocatie niet schrijfbaar.']);
        exit;
    }

    $pad = sct_storage_pad($id);
    if (!move_uploaded_file($_FILES['bestand']['tmp_name'], $pad)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'fout' => 'Opslaan op disk mislukt.']);
        exit;
    }
    @chmod($pad, 0600);
    $ct_bytes = filesize($pad);

    try {
        db()->prepare(
            'INSERT INTO sct_secrets
                (id, type, ciphertext, iv, bestandsnaam_ct, bestandsnaam_iv, mimetype, bestandsgrootte,
                 opslag_pad, has_password, password_hash,
                 sender_user_id, sender_naam, sender_email, notify_email,
                 retentie_uren, aangemaakt_op, verloopt_op)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id, 'file', $iv, $meta_ct, $meta_iv, $mimetype, $ct_bytes,
            basename($pad), $heeft_ww ? 1 : 0, $ww_hash,
            $gebruiker['id'], $gebruiker['naam'], $sender_email,
            $notify !== '' ? $notify : null, $retentie, $aangemaakt, $verloopt,
        ]);
    } catch (PDOException $e) {
        @unlink($pad);
        http_response_code(500);
        echo json_encode(['ok' => false, 'fout' => 'Opslaan mislukt.']);
        exit;
    }

    sct_log($id, 'aangemaakt');
    log_actie('sct_aangemaakt', "id={$id}, type=file, bytes={$ct_bytes}, retentie={$retentie}u, wachtwoord=" . ($heeft_ww ? 'ja' : 'nee'));

    echo json_encode([
        'ok'          => true,
        'id'          => $id,
        'type'        => 'file',
        'verloopt_op' => date('d-m-Y H:i', strtotime($verloopt)),
        'base_url'    => rtrim(BASE_URL, '/'),
    ]);
    exit;
}

// ── Tekst branch (default) ────────────────────────────────────────────────
$ciphertext = (string)($in['ciphertext'] ?? '');
$iv         = (string)($in['iv'] ?? '');

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

try {
    db()->prepare(
        'INSERT INTO sct_secrets
            (id, type, ciphertext, iv, has_password, password_hash,
             sender_user_id, sender_naam, sender_email, notify_email,
             retentie_uren, aangemaakt_op, verloopt_op)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id, 'text', $ciphertext, $iv, $heeft_ww ? 1 : 0, $ww_hash,
        $gebruiker['id'], $gebruiker['naam'], $sender_email,
        $notify !== '' ? $notify : null, $retentie, $aangemaakt, $verloopt,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'fout' => 'Opslaan mislukt.']);
    exit;
}

sct_log($id, 'aangemaakt');
log_actie('sct_aangemaakt', "id={$id}, type=text, retentie={$retentie}u, wachtwoord=" . ($heeft_ww ? 'ja' : 'nee'));

echo json_encode([
    'ok'          => true,
    'id'          => $id,
    'type'        => 'text',
    'verloopt_op' => date('d-m-Y H:i', strtotime($verloopt)),
    'base_url'    => rtrim(BASE_URL, '/'),
]);
