<?php
/**
 * Migratie voor SCT (Secret Connect4IT Transmission)
 *
 * Draai deze één keer op productie:
 *   https://www.connect4it.nl/app/migrate_sct.php
 *
 * Beveilig daarna in .htaccess (zie onderaan dit bestand) of verwijder het.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$statements = [
    "CREATE TABLE IF NOT EXISTS sct_secrets (
        id VARCHAR(32) NOT NULL PRIMARY KEY,
        ciphertext LONGTEXT NOT NULL,
        iv VARCHAR(64) NOT NULL,
        has_password TINYINT(1) NOT NULL DEFAULT 0,
        password_hash VARCHAR(255) NULL,
        sender_user_id INT NULL,
        sender_naam VARCHAR(255) NULL,
        sender_email VARCHAR(255) NULL,
        notify_email VARCHAR(255) NULL,
        retentie_uren INT NOT NULL,
        aangemaakt_op DATETIME NOT NULL,
        verloopt_op DATETIME NOT NULL,
        INDEX idx_verloopt (verloopt_op),
        INDEX idx_sender (sender_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS sct_access_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        secret_id VARCHAR(32) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(500) NULL,
        actie ENUM('aangemaakt','bekeken','verlopen','fout_wachtwoord','niet_gevonden','rate_limit') NOT NULL,
        tijdstip DATETIME NOT NULL,
        INDEX idx_secret (secret_id),
        INDEX idx_tijdstip (tijdstip),
        INDEX idx_ip (ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($statements as $sql) {
    try {
        db()->exec($sql);
        echo "[OK] " . substr(trim($sql), 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "[FOUT] " . $e->getMessage() . "\n";
    }
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess om dit bestand te blokkeren:\n\n";
echo "<FilesMatch \"^migrate_sct\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
