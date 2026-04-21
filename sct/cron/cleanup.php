<?php
/**
 * SCT cron cleanup — verwijdert verlopen secrets + oude access-log regels.
 *
 * Aanroep (CLI, aanbevolen):
 *   /usr/bin/php /var/www/app/sct/cron/cleanup.php
 *
 * Aanroep via HTTP (bv. cron-as-a-service):
 *   https://www.connect4it.nl/app/sct/cron/cleanup.php?token=<SCT_CRON_TOKEN>
 *
 * Definieer SCT_CRON_TOKEN in config.php:
 *   define('SCT_CRON_TOKEN', '<lange-random-string>');
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/sct_functions.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    $verwacht = defined('SCT_CRON_TOKEN') ? SCT_CRON_TOKEN : '';
    $token = (string)($_GET['token'] ?? '');
    if ($verwacht === '' || !hash_equals($verwacht, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$aantal = sct_verwijder_verlopen();

// Oude log entries opruimen (> 90 dagen)
try {
    $log_opgeruimd = db()->exec("DELETE FROM sct_access_log WHERE tijdstip < DATE_SUB(NOW(), INTERVAL 90 DAY)");
} catch (PDOException $e) {
    $log_opgeruimd = 0;
}

echo "[SCT cron] " . date('Y-m-d H:i:s') . "\n";
echo "  Verwijderde verlopen secrets : {$aantal}\n";
echo "  Opgeruimde log-regels (>90d) : {$log_opgeruimd}\n";
