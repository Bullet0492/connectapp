<?php
/**
 * Landing page bij scannen QR-code op apparaat
 * URL: /qr/apparaat.php?qr=QR2026.001
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$qr_code = trim($_GET['qr'] ?? '');
if (!$qr_code) { header('Location: ' . basis_url() . '/index.php'); exit; }

$stmt = db()->prepare("SELECT a.*, k.naam AS klant_naam, k.bedrijf, k.id AS klant_id FROM apparaten a LEFT JOIN klanten k ON k.id = a.klant_id WHERE a.qr_code = ?");
$stmt->execute([$qr_code]);
$a = $stmt->fetch();

if (!$a) {
    // QR-code niet gevonden
    header('Location: ' . basis_url() . '/apparaten/index.php');
    exit;
}

// Redirect naar klantdetail tab apparaten
header('Location: ' . basis_url() . '/klanten/detail.php?id=' . $a['klant_id'] . '&tab=apparaten');
exit;
