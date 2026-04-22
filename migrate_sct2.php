<?php
/**
 * Migratie voor SCT — bestand-ondersteuning.
 *
 * Draai één keer op productie:
 *   https://www.connect4it.nl/app/migrate_sct2.php
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

$toevoegen = [
    'type'              => "ADD COLUMN type VARCHAR(10) NOT NULL DEFAULT 'text' AFTER id",
    'bestandsnaam_ct'   => "ADD COLUMN bestandsnaam_ct TEXT NULL AFTER iv",
    'bestandsnaam_iv'   => "ADD COLUMN bestandsnaam_iv VARCHAR(64) NULL AFTER bestandsnaam_ct",
    'mimetype'          => "ADD COLUMN mimetype VARCHAR(150) NULL AFTER bestandsnaam_iv",
    'bestandsgrootte'   => "ADD COLUMN bestandsgrootte BIGINT NULL AFTER mimetype",
    'opslag_pad'        => "ADD COLUMN opslag_pad VARCHAR(255) NULL AFTER bestandsgrootte",
];

foreach ($toevoegen as $kolom => $clause) {
    if (kolom_bestaat($pdo, 'sct_secrets', $kolom)) {
        echo "[SKIP] kolom bestaat al: {$kolom}\n";
        continue;
    }
    try {
        $pdo->exec("ALTER TABLE sct_secrets {$clause}");
        echo "[OK] kolom toegevoegd: {$kolom}\n";
    } catch (PDOException $e) {
        echo "[FOUT] {$kolom}: " . $e->getMessage() . "\n";
    }
}

// Bij bestaande tekst-records: type op 'text' zetten (al de default, maar voor de zekerheid)
try {
    $pdo->exec("UPDATE sct_secrets SET type = 'text' WHERE type IS NULL OR type = ''");
    echo "[OK] bestaande records gemarkeerd als type=text\n";
} catch (PDOException $e) {
    echo "[FOUT] update bestaande records: " . $e->getMessage() . "\n";
}

// Ciphertext mag NULL worden voor file-records (die staan op disk)
try {
    $pdo->exec("ALTER TABLE sct_secrets MODIFY ciphertext LONGTEXT NULL");
    echo "[OK] ciphertext nullable gemaakt\n";
} catch (PDOException $e) {
    echo "[INFO] ciphertext kolom al nullable of: " . $e->getMessage() . "\n";
}

echo "\nKlaar.\n";
echo "Blokkeer daarna dit bestand in .htaccess:\n\n";
echo "<FilesMatch \"^migrate_sct2\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
