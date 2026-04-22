<?php
/**
 * SCT API — secret ophalen + direct verwijderen.
 * PUBLIEK (geen auth). Rate-limited op IP.
 *
 * Response-types:
 * - type=text  → application/json met ciphertext (base64url) + iv (base64url).
 * - type=file  → application/octet-stream met ciphertext-bytes; metadata zit
 *                in response headers X-SCT-Iv / X-SCT-Meta-Ct / X-SCT-Meta-Iv /
 *                X-SCT-Mimetype / X-SCT-Type.
 */

// Default: JSON (foutpad). Bij file-success switchen we naar binary.
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function ($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'fout' => 'Serverfout: ' . $e->getMessage()]);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
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

    $type = $secret['type'] ?? 'text';

    if ($type === 'file') {
        // Bestand van disk lezen VOORDAT we iets verwijderen.
        $pad = sct_storage_pad($id);
        if (!is_file($pad)) {
            $pdo->rollBack();
            sct_log($id, 'niet_gevonden');
            http_response_code(410);
            echo json_encode(['ok' => false, 'fout' => 'Bestand ontbreekt op server.']);
            exit;
        }

        // Pak metadata nu nog uit $secret; na DELETE is het DB-record weg.
        $iv       = (string)$secret['iv'];
        $meta_ct  = (string)$secret['bestandsnaam_ct'];
        $meta_iv  = (string)$secret['bestandsnaam_iv'];
        $mimetype = (string)($secret['mimetype'] ?: 'application/octet-stream');
        $grootte  = (int)filesize($pad);
        $notify   = $secret['notify_email'];

        // DB weg, disk weg — maar file pas NA het streamen unlinken zodat
        // we een read-error nog kunnen melden.
        $pdo->prepare('DELETE FROM sct_secrets WHERE id = ?')->execute([$id]);
        $pdo->commit();

        sct_log($id, 'bekeken');

        // Stream als binary
        if (ob_get_level() > 0) @ob_end_clean();
        header_remove('Content-Type');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $grootte);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-SCT-Type: file');
        header('X-SCT-Iv: ' . $iv);
        header('X-SCT-Meta-Ct: ' . $meta_ct);
        header('X-SCT-Meta-Iv: ' . $meta_iv);
        header('X-SCT-Mimetype: ' . $mimetype);

        $fp = fopen($pad, 'rb');
        if ($fp) {
            while (!feof($fp)) {
                echo fread($fp, 65536);
            }
            fclose($fp);
        }
        @unlink($pad);

        if (!empty($notify)) {
            sct_stuur_notificatie($notify, $id, ip_adres());
        }
        exit;
    }

    // Tekst branch
    $pdo->prepare('DELETE FROM sct_secrets WHERE id = ?')->execute([$id]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'fout' => 'Ophalen mislukt.']);
    exit;
}

sct_log($id, 'bekeken');

if (!empty($secret['notify_email'])) {
    sct_stuur_notificatie($secret['notify_email'], $id, ip_adres());
}

echo json_encode([
    'ok'         => true,
    'type'       => 'text',
    'ciphertext' => $secret['ciphertext'],
    'iv'         => $secret['iv'],
]);
