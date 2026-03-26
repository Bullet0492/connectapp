<?php
require_once __DIR__ . '/includes/db.php';
$db = db();
$db->exec("ALTER TABLE klant_simpbx ADD COLUMN IF NOT EXISTS naam VARCHAR(100) NULL");
$db->exec("ALTER TABLE klant_ziggo  ADD COLUMN IF NOT EXISTS naam VARCHAR(100) NULL");
echo "migrate12 klaar: naam-kolom toegevoegd aan klant_simpbx en klant_ziggo.";
