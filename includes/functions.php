<?php
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $bericht): void {
    sessie_start();
    $_SESSION['flash'] = ['type' => $type, 'bericht' => $bericht];
}

function flash_get(): ?array {
    sessie_start();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flash_html(): string {
    $flash = flash_get();
    if (!$flash) return '';
    $type = $flash['type'] === 'succes' ? 'success' : ($flash['type'] === 'fout' ? 'danger' : 'info');
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
        . h($flash['bericht'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function csrf_token(): string {
    sessie_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            die('Ongeldig verzoek.');
        }
    }
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function pagina_reeks(int $huidig, int $totaal): array {
    if ($totaal <= 9) return range(1, $totaal);
    $toon = array_unique(array_merge(
        [1, 2],
        range(max(2, $huidig - 2), min($totaal - 1, $huidig + 2)),
        [$totaal - 1, $totaal]
    ));
    sort($toon);
    $toon = array_values(array_filter($toon, fn($p) => $p >= 1 && $p <= $totaal));
    $result = [];
    $vorige = 0;
    foreach ($toon as $p) {
        if ($p - $vorige > 1) $result[] = '...';
        $result[] = $p;
        $vorige = $p;
    }
    return $result;
}

function log_actie(string $actie, string $details = ''): void {
    try {
        $user = huidig_gebruiker();
        db()->prepare("INSERT INTO logboek (user_id, user_naam, actie, details) VALUES (?, ?, ?, ?)")
            ->execute([$user['id'], $user['naam'], $actie, $details]);
    } catch (Exception $e) {
        // Stilvallen als logboek tabel niet bestaat
    }
}

function volgende_qr_nummer(): string {
    $jaar = date('Y');
    $stmt = db()->prepare(
        "SELECT qr_code FROM apparaten WHERE qr_code LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute(["QR$jaar.%"]);
    $laatste = $stmt->fetchColumn();
    if ($laatste) {
        $volgnummer = (int) substr($laatste, strrpos($laatste, '.') + 1);
        $volgnummer++;
    } else {
        $volgnummer = 1;
    }
    return sprintf('QR%s.%03d', $jaar, $volgnummer);
}

// ─── Encryptie helpers (AES-256-CBC) ───────────────────────────────────────

function encrypt_wachtwoord(string $plaintext): string {
    $key = hex2bin(ENCRYPT_KEY);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function decrypt_wachtwoord(string $ciphertext): string {
    $key  = hex2bin(ENCRYPT_KEY);
    $data = base64_decode($ciphertext);
    $iv   = substr($data, 0, 16);
    $enc  = substr($data, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv) ?: '';
}
