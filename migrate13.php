<?php
require_once __DIR__ . '/includes/db.php';
$db = db();
$db->exec("ALTER TABLE inloggegevens MODIFY categorie VARCHAR(100) NOT NULL DEFAULT 'router'");
echo "migrate13 klaar: inloggegevens.categorie gewijzigd van ENUM naar VARCHAR(100).";
