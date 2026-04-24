<?php
/**
 * CONFIGURATIE - pas dit aan voor jouw server
 *
 * BASE_URL: de URL naar de map waar de app staat, zonder trailing slash.
 * Voorbeelden:
 *   'http://localhost/connectapp'       (lokaal XAMPP)
 *   'https://www.connect4it.nl/app'     (op de VPS)
 */
define('BASE_URL', 'https://www.connect4it.nl/app');

/**
 * DEVELOPMENT MODE
 * Zet op true voor lokale ontwikkeling (schakelt HTTPS-vereiste voor cookies uit).
 * ALTIJD false op productie!
 */
define('DEVELOPMENT_MODE', false);

/**
 * ENCRYPTIE SLEUTEL voor wachtwoordkluis
 * Genereer een unieke sleutel met: bin2hex(random_bytes(32))
 * Wijzig deze na eerste installatie en bewaar veilig!
 */
define('ENCRYPT_KEY', '8db04c5e673e89e429acfa576dfcb783febacafab8bbf2804eddfae0d82f1278');

/**
 * GEDEELD SSO-SECRET voor automatische login vanuit ConnectApp naar Werkbon.
 * Moet EXACT hetzelfde zijn in werkbonapp/config.php.
 * Wijziging = iedereen uit werkbon geklikt tot nieuwe token.
 */
define('SSO_SHARED_SECRET', '434cf1bb025b0d9ce1dab74e7d12a07cf844fb77a0ec6147c147e477b3e01f0a');
define('WERKBON_URL', 'https://www.connect4it.nl/werkbon');

