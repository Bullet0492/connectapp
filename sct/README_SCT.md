# SCT — Secret Connect4IT Transmission

Privnote-achtige module binnen ConnectApp voor het versturen van eenmalig-leesbare,
end-to-end versleutelde berichten.

## Architectuur in één oogopslag

- **Versleuteling in de browser** (WebCrypto / AES-256-GCM). De plaintext bereikt
  de server nooit.
- **Decryptiesleutel** wordt meegegeven in het *URL-fragment* (`#key=…`). Fragmenten
  worden door browsers NIET meegestuurd in HTTP-requests → server is zero-knowledge.
- **Optioneel wachtwoord** = extra server-side gate (bcrypt). Validatie van het
  wachtwoord gebeurt vóór de ciphertext wordt vrijgegeven. Het wachtwoord is geen
  onderdeel van de AES-sleutel — dat voorkomt brute-force op een offline payload.
- **Read-once**: zodra `read.php` de ciphertext uitserveert (na evt. wachtwoord-check),
  wordt de record in dezelfde DB-transactie `DELETE`d.
- **Retentie**: 1 uur / 4 uur / 1 dag / 2 dagen / 7 dagen. Verlopen records worden
  "lazy" opgeruimd bij elke leesactie en daarnaast door een cron.
- **Rate limiting**: max 20 leesacties per IP per 15 minuten (configureerbaar in
  `sct_functions.php`).

## Bestanden

```
sct/
├── index.php              Aanmaakformulier (auth)
├── overzicht.php          Dashboard eigen berichten (auth)
├── v.php                  Publieke bekijkpagina
├── api/
│   ├── create.php         Opslaan ciphertext (auth + CSRF)
│   ├── read.php           Ciphertext ophalen + verwijderen (publiek)
│   └── verwijder.php      Intrekken door afzender (auth + CSRF)
├── assets/
│   ├── sct-create.js      Client-side encryptie
│   └── sct-read.js        Client-side decryptie
├── cron/cleanup.php       Cron voor verlopen records
└── includes/sct_functions.php
migrate_sct.php            (project-root) DB-migratie
```

## Deployment

### 1. DB migratie
```
https://www.connect4it.nl/app/migrate_sct.php
```
(alleen admin-login kan dit starten). Blokkeer het bestand daarna in `.htaccess`:
```apache
<FilesMatch "^migrate_sct\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### 2. Cron token in config.php
Voeg toe (genereer eenmalig een sterke random string):
```php
define('SCT_CRON_TOKEN', '<bin2hex(random_bytes(32))>');
```

### 3. Cron instellen
**CLI (aanbevolen):**
```
*/15 * * * * /usr/bin/php /var/www/app/sct/cron/cleanup.php >/dev/null 2>&1
```

**HTTP alternatief:**
```
*/15 * * * * curl -s "https://www.connect4it.nl/app/sct/cron/cleanup.php?token=<SCT_CRON_TOKEN>" >/dev/null
```

### 4. SMTP
Notificatiemails gaan nu via `mail()`. Voor betrouwbare bezorging (SPF/DKIM):
vervang `sct_stuur_notificatie()` in `sct/includes/sct_functions.php` door
PHPMailer met de VPS SMTP-relay.

## Security notities

- HTTPS is verplicht. Zonder TLS kan een MITM de fragment + ciphertext beiden
  onderscheppen.
- De bestaande CSP in `.htaccess` (zie hoofdprojectroot) staat `'unsafe-inline'`
  toe voor scripts. WebCrypto werkt goed binnen die policy.
- `sct_access_log` blijft 90 dagen staan (configureerbaar in `cleanup.php`) voor
  forensisch onderzoek — IP + user-agent van elke openingspoging.
- Ciphertext max 128 KB base64 (≈ 64 KB plaintext). Pas `SCT_MAX_CIPHERTEXT` aan
  indien nodig.

## Bekende trade-offs

- **Read-once is absoluut**: als de ontvanger de pagina per ongeluk te vroeg
  sluit, is het bericht weg. Dit is het privnote-model en inherent aan "één keer
  leesbaar".
- **Geen server-side replay**: we kunnen niet detecteren of iemand de plaintext
  na decryptie doorstuurt. Dat is buiten onze scope; voeg alleen wachtwoord +
  korte retentie toe als extra gate.
- **Notificatiemail ≠ bewijs van ontvangst**: rate-limited pogingen met fout
  wachtwoord triggeren géén mail. Alleen een succesvolle `bekeken`-actie doet dat.
