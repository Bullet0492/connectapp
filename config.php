<?php
/**
 * CONFIGURATIE - pas dit aan voor jouw server
 *
 * BASE_URL: de URL naar de map waar de app staat, zonder trailing slash.
 * Voorbeelden:
 *   'http://localhost/App'              (lokaal XAMPP)
 *   'https://jouwdomein.nl/klantenapp'  (op de VPS)
 */
define('BASE_URL', 'https://www.connect4it.nl/app');

/**
 * ENCRYPTIE SLEUTEL voor wachtwoordkluis
 * Genereer een unieke sleutel met: bin2hex(random_bytes(32))
 * Wijzig deze na eerste installatie en bewaar veilig!
 */
define('ENCRYPT_KEY', '8db04c5e673e89e429acfa576dfcb783febacafab8bbf2804eddfae0d82f1278');
