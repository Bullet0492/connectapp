<?php
/**
 * Migratie: voeg intranet_id kolom toe aan klanten tabel.
 *
 * Draai één keer op productie:
 *   https://www.connect4it.nl/app/migrate_intranet.php
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

if (kolom_bestaat($pdo, 'klanten', 'intranet_id')) {
    echo "[SKIP] kolom bestaat al: intranet_id\n";
} else {
    try {
        $pdo->exec("ALTER TABLE klanten ADD COLUMN intranet_id VARCHAR(100) NULL AFTER intra_id");
        echo "[OK] kolom toegevoegd: intranet_id\n";
    } catch (PDOException $e) {
        echo "[FOUT] intranet_id: " . $e->getMessage() . "\n";
    }
}

echo "\nKlaar.\n";
echo "Blokkeer daarna dit bestand in .htaccess:\n\n";
echo "<FilesMatch \"^migrate_intranet\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
