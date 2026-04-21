<?php
/**
 * SCT helpers — Secret Connect4IT Transmission
 *
 * Ontwerpprincipe: server is zero-knowledge.
 * - De AES-GCM sleutel zit uitsluitend in het URL-fragment (#key=...) en bereikt
 *   de server nooit.
 * - Een optioneel wachtwoord wordt als extra gate-check gebruikt (bcrypt hash op
 *   server) en is NIET onderdeel van de decryptie zelf.
 * - Na een succesvolle lees-operatie wordt de ciphertext onmiddellijk DELETED.
 *   De sct_access_log blijft staan als forensisch spoor.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

/** Toegestane retentie-waarden (uren). */
const SCT_RETENTIE_OPTIES = [
    1   => '1 uur',
    4   => '4 uur',
    24  => '1 dag',
    48  => '2 dagen',
    168 => '7 dagen',
];

/** Max grootte ciphertext in base64 (ruwweg 64 KB plaintext). */
const SCT_MAX_CIPHERTEXT = 131072;

/** Rate limit: max pogingen per IP binnen venster. */
const SCT_RATELIMIT_POGINGEN = 20;
const SCT_RATELIMIT_VENSTER_MIN = 15;

/**
 * Genereer een uniek 24-karakter token (base32-achtig, URL-veilig).
 */
function sct_genereer_id(): string {
    $alfabet = 'abcdefghijkmnopqrstuvwxyz23456789'; // geen 0/1/l/o voor leesbaarheid
    $len = strlen($alfabet);
    for ($poging = 0; $poging < 5; $poging++) {
        $id = '';
        for ($i = 0; $i < 24; $i++) {
            $id .= $alfabet[random_int(0, $len - 1)];
        }
        $stmt = db()->prepare('SELECT 1 FROM sct_secrets WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) return $id;
    }
    throw new RuntimeException('Kon geen uniek SCT id genereren');
}

/**
 * Log een actie in sct_access_log.
 */
function sct_log(string $secret_id, string $actie, ?string $ip = null, ?string $ua = null): void {
    $ip = $ip ?? ip_adres();
    $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strlen($ua) > 500) $ua = substr($ua, 0, 500);
    try {
        db()->prepare(
            'INSERT INTO sct_access_log (secret_id, ip, user_agent, actie, tijdstip)
             VALUES (?, ?, ?, ?, NOW())'
        )->execute([$secret_id, $ip, $ua, $actie]);
    } catch (PDOException $e) {
        // niet fataal — laat foutafhandeling aan aanroeper
    }
}

/**
 * Controleer of het huidige IP teveel pogingen heeft binnen het venster.
 * Returnt true als geblokkeerd.
 */
function sct_rate_limited(): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM sct_access_log
         WHERE ip = ?
           AND actie IN ('fout_wachtwoord','niet_gevonden','bekeken')
           AND tijdstip > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->execute([ip_adres(), SCT_RATELIMIT_VENSTER_MIN]);
    return (int) $stmt->fetchColumn() >= SCT_RATELIMIT_POGINGEN;
}

/**
 * Verwijder alle verlopen secrets en log ze.
 * Wordt aangeroepen door cron en "lazy" bij elke leesactie.
 */
function sct_verwijder_verlopen(): int {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM sct_secrets WHERE verloopt_op < NOW()');
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM sct_secrets WHERE id IN ($placeholders)")->execute($ids);
    foreach ($ids as $id) {
        sct_log($id, 'verlopen', '0.0.0.0', 'cron');
    }
    return count($ids);
}

/**
 * Verstuur notificatiemail naar afzender dat het bericht gelezen is.
 * Vervang dit later desgewenst door PHPMailer.
 */
function sct_stuur_notificatie(string $aan, string $secret_id, string $lezer_ip): bool {
    if (!filter_var($aan, FILTER_VALIDATE_EMAIL)) return false;

    $onderwerp = '[SCT] Uw beveiligd bericht is gelezen';
    $tijdstip = date('d-m-Y H:i:s');
    $body  = "Uw beveiligd bericht via SCT (Secret Connect4IT Transmission) is zojuist gelezen.\n\n";
    $body .= "Bericht-ID : {$secret_id}\n";
    $body .= "Gelezen op : {$tijdstip}\n";
    $body .= "IP lezer   : {$lezer_ip}\n\n";
    $body .= "Het bericht is nu verwijderd van de server en kan niet opnieuw bekeken worden.\n\n";
    $body .= "-- \nConnect4IT SCT\n" . BASE_URL . "/sct/\n";

    $headers = [
        'From: SCT <geen-antwoord@connect4it.nl>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: SCT',
    ];

    return @mail($aan, $onderwerp, $body, implode("\r\n", $headers));
}

/**
 * Safe equal-length comparison voor wachtwoord-check zonder timing leaks.
 */
function sct_controleer_wachtwoord(string $plaintext, string $hash): bool {
    return password_verify($plaintext, $hash);
}

/**
 * Haal een secret uit de DB zonder te verwijderen. Returnt null als niet gevonden/verlopen.
 */
function sct_haal_secret(string $id): ?array {
    $stmt = db()->prepare('SELECT * FROM sct_secrets WHERE id = ? AND verloopt_op > NOW()');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
