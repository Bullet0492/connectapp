<?php
/**
 * SCT API — secret ophalen + direct verwijderen.
 * PUBLIEK (geen auth). Rate-limited op IP.
 */
header('Content-Type: application/json; charset=utf-8');

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

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/sct_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'fout' => 'Methode niet toegestaan.']);
    exit;
}

// Rate limit
if (sct_rate_limited()) {
    sct_log('-', 'rate_limit');
    http_response_code(429);
    echo json_encode(['ok' => false, 'fout' => 'Te veel pogingen. Probeer het over ' . SCT_RATELIMIT_VENSTER_MIN . ' minuten opnieuw.']);
    exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige JSON.']);
    exit;
}

$id = (string)($in['id'] ?? '');
if (!preg_match('/^[a-z0-9]{24}$/i', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige bericht-ID.']);
    exit;
}

// Lazy cleanup
sct_verwijder_verlopen();

$pdo = db();

// Transactie: lees + verwijder atomair
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM sct_secrets WHERE id = ? AND verloopt_op > NOW() FOR UPDATE');
    $stmt->execute([$id]);
    $secret = $stmt->fetch();

    if (!$secret) {
        $pdo->rollBack();
        sct_log($id, 'niet_gevonden');
        http_response_code(404);
        echo json_encode(['ok' => false, 'fout' => 'Bericht niet gevonden, al gelezen of verlopen.']);
        exit;
    }

    // Wachtwoord check
    if ($secret['has_password']) {
        $ww = (string)($in['wachtwoord'] ?? '');
        if ($ww === '' || !sct_controleer_wachtwoord($ww, $secret['password_hash'])) {
            $pdo->rollBack();
            sct_log($id, 'fout_wachtwoord');
            http_response_code(401);
            echo json_encode(['ok' => false, 'fout' => 'Onjuist wachtwoord.']);
            exit;
        }
    }

    // Verwijder het secret onmiddellijk
    $pdo->prepare('DELETE FROM sct_secrets WHERE id = ?')->execute([$id]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'fout' => 'Ophalen mislukt.']);
    exit;
}

sct_log($id, 'bekeken');

// Notificatie naar afzender (buiten transactie)
if (!empty($secret['notify_email'])) {
    sct_stuur_notificatie($secret['notify_email'], $id, ip_adres());
}

echo json_encode([
    'ok'         => true,
    'ciphertext' => $secret['ciphertext'],
    'iv'         => $secret['iv'],
]);
