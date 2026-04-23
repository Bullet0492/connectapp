<?php
/**
 * Migratie: voeg label kolom toe aan sct_secrets zodat de verzender
 * in het overzicht ziet voor wie elk bericht bedoeld is.
 *
 * Draai één keer op productie:
 *   https://www.connect4it.nl/app/migrate_sct_label.php
 * Beveilig daarna weer in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

function kolom_bestaat(PDO $pdo, string $tabel, string $kolom): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$tabel, $kolom]);
    return (bool)$stmt->fetchColumn();
}

if (kolom_bestaat($pdo, 'sct_secrets', 'label')) {
    echo "[SKIP] kolom bestaat al: label\n";
} else {
    try {
        $pdo->exec("ALTER TABLE sct_secrets ADD COLUMN label VARCHAR(150) NULL AFTER notify_email");
        echo "[OK] kolom toegevoegd: label\n";
    } catch (PDOException $e) {
        echo "[FOUT] label: " . $e->getMessage() . "\n";
    }
}

echo "\nKlaar.\n";
echo "Blokkeer daarna dit bestand in .htaccess:\n\n";
echo "<FilesMatch \"^migrate_sct_label\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
