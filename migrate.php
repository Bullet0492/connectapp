<?php
/**
 * Database migratie - eenmalig uitvoeren in de browser
 * URL: http://localhost/App/migrate.php
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

$stappen = [];

// ─── Users tabel ────────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    naam            VARCHAR(100) NOT NULL,
    gebruikersnaam  VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL UNIQUE,
    wachtwoord      VARCHAR(255) NOT NULL,
    rol             ENUM('admin','gebruiker') NOT NULL DEFAULT 'gebruiker',
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "users tabel aangemaakt"];

// ─── Login pogingen ──────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS login_pogingen (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ip              VARCHAR(45) NOT NULL UNIQUE,
    pogingen        INT NOT NULL DEFAULT 1,
    geblokkeerd_tot DATETIME NULL,
    laatste_poging  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)", "login_pogingen tabel aangemaakt"];

// ─── Klanten ─────────────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS klanten (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    naam            VARCHAR(100) NOT NULL,
    bedrijf         VARCHAR(150) DEFAULT '',
    adres           VARCHAR(200) DEFAULT '',
    postcode        VARCHAR(20)  DEFAULT '',
    stad            VARCHAR(100) DEFAULT '',
    telefoon        VARCHAR(50)  DEFAULT '',
    email           VARCHAR(150) DEFAULT '',
    website         VARCHAR(200) DEFAULT '',
    intra_id        VARCHAR(50)  DEFAULT '',
    notities        TEXT,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "klanten tabel aangemaakt"];

// ─── Contactpersonen ─────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS contactpersonen (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    naam            VARCHAR(100) NOT NULL,
    functie         VARCHAR(100) DEFAULT '',
    email           VARCHAR(150) DEFAULT '',
    telefoon        VARCHAR(50)  DEFAULT '',
    notities        TEXT,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "contactpersonen tabel aangemaakt"];

// ─── Apparaten ───────────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS apparaten (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    qr_code         VARCHAR(50)  NOT NULL UNIQUE,
    type            ENUM('desktop','laptop','server','nuc','printer','netwerk','overig') NOT NULL DEFAULT 'overig',
    merk            VARCHAR(100) DEFAULT '',
    model           VARCHAR(100) DEFAULT '',
    serienummer     VARCHAR(150) DEFAULT '',
    aanschafdatum   DATE         NULL,
    garantie_tot    DATE         NULL,
    locatie         VARCHAR(150) DEFAULT '',
    status          ENUM('actief','defect','retour') NOT NULL DEFAULT 'actief',
    notities        TEXT,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "apparaten tabel aangemaakt"];

// ─── Inloggegevens ───────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS inloggegevens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    categorie       ENUM('netwerk','server','cloud','portaal','overig') NOT NULL DEFAULT 'overig',
    label           VARCHAR(150) NOT NULL,
    gebruikersnaam  VARCHAR(200) DEFAULT '',
    wachtwoord_enc  TEXT,
    url             VARCHAR(300) DEFAULT '',
    notities        TEXT,
    aangemaakt_door INT NULL,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "inloggegevens tabel aangemaakt"];

// ─── Klant notities ──────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS klant_notities (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    titel           VARCHAR(200) NOT NULL,
    inhoud          TEXT,
    aangemaakt_door INT NULL,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bijgewerkt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "klant_notities tabel aangemaakt"];

// ─── Logboek ─────────────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS logboek (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    user_naam       VARCHAR(100),
    actie           VARCHAR(100),
    details         TEXT,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "logboek tabel aangemaakt"];

// ─── Uitvoeren ───────────────────────────────────────────────────────────────
echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie</h3><div class="mt-3">';

foreach ($stappen as [$sql, $label]) {
    try {
        $db->exec($sql);
        echo '<div class="text-success mb-1"><i>&#10003;</i> ' . htmlspecialchars($label) . '</div>';
    } catch (Exception $e) {
        echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Standaard admin account aanmaken als er nog geen users zijn
$aantalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($aantalUsers === 0) {
    $hash = password_hash('Admin123!', PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (naam, gebruikersnaam, email, wachtwoord, rol) VALUES (?, ?, ?, ?, 'admin')")
       ->execute(['Beheerder', 'admin', 'admin@connect4it.nl', $hash]);
    echo '<div class="text-info mb-1 mt-2"><strong>Admin account aangemaakt:</strong><br>';
    echo 'Gebruikersnaam: <code>admin</code><br>';
    echo 'Wachtwoord: <code>Admin123!</code><br>';
    echo '<strong class="text-danger">Wijzig dit wachtwoord direct na inloggen!</strong></div>';
}

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong> Ga naar <a href="login.php">login.php</a> om in te loggen.</div>';
echo '</body></html>';
