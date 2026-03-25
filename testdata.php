<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Testdata</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Testdata laden</h3><div class="mt-3">';

function ok(string $t): void  { echo '<div class="text-success mb-1">&#10003; ' . htmlspecialchars($t) . '</div>'; }
function err(string $t): void { echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($t) . '</div>'; }
function inf(string $t): void { echo '<div class="text-info mb-1">&#8505; ' . htmlspecialchars($t) . '</div>'; }

// ─── Klanten ─────────────────────────────────────────────────────────────────
$klanten = [
    ['Bakkerij De Korrel',      'Bakkerij De Korrel BV',   'Molenstraat 12',   '7411AB', 'Deventer',   '0570-612345', 'info@dekorrel.nl',        'https://www.dekorrel.nl',        'BDK001'],
    ['Autogarage Smit',         'Autogarage Smit VOF',     'Industrieweg 88',  '2700AK', 'Zoetermeer', '079-3214567', 'contact@garagesmit.nl',   'https://www.garagesmit.nl',      'AGS002'],
    ['Tandartspraktijk Jansen', 'Jansen Tandheelkunde',    'Kerkplein 3',      '5038AC', 'Tilburg',    '013-5432100', 'praktijk@jansen-tand.nl', 'https://www.jansen-tand.nl',     'JTH003'],
    ['Transport Van Dijk',      'Van Dijk Transport BV',   'Havenweg 201',     '3089JH', 'Rotterdam',  '010-4123456', 'info@vandijktransport.nl','https://www.vandijktransport.nl', 'VDT004'],
];

$klant_ids = [];
foreach ($klanten as $k) {
    try {
        $chk = $db->prepare('SELECT id FROM klanten WHERE email = ?');
        $chk->execute([$k[6]]);
        $bestaand = $chk->fetchColumn();
        if ($bestaand) {
            inf("Klant bestaat al, overgeslagen: {$k[0]}");
            $klant_ids[] = (int)$bestaand;
        } else {
            $db->prepare('INSERT INTO klanten (naam, bedrijf, adres, postcode, stad, telefoon, email, website, intra_id) VALUES (?,?,?,?,?,?,?,?,?)')->execute($k);
            $klant_ids[] = (int)$db->lastInsertId();
            ok("Klant aangemaakt: {$k[0]}");
        }
    } catch (Exception $e) { err("Klant {$k[0]}: " . $e->getMessage()); $klant_ids[] = 0; }
}

// ─── Contactpersonen ─────────────────────────────────────────────────────────
$contacten = [
    [$klant_ids[0], 'Henk de Korrel',  'Eigenaar',     'henk@dekorrel.nl',       '06-11223344'],
    [$klant_ids[0], 'Lisa Bakker',     'Administratie','lisa@dekorrel.nl',        '06-22334455'],
    [$klant_ids[1], 'Peter Smit',      'Eigenaar',     'peter@garagesmit.nl',     '06-33445566'],
    [$klant_ids[1], 'Karin Smit',      'Administratie','karin@garagesmit.nl',     '06-44332211'],
    [$klant_ids[2], 'Dr. A. Jansen',   'Tandarts',     'ajansen@jansen-tand.nl',  '06-44556677'],
    [$klant_ids[2], 'Sandra Pieterse', 'Receptie',     'receptie@jansen-tand.nl', '06-55667788'],
    [$klant_ids[3], 'Rob van Dijk',    'Directeur',    'rob@vandijktransport.nl', '06-66778899'],
    [$klant_ids[3], 'Mike de Groot',   'IT contactpersoon','mike@vandijktransport.nl','06-77889900'],
];
try {
    foreach ($contacten as $c) {
        $db->prepare('INSERT INTO contactpersonen (klant_id, naam, functie, email, telefoon) VALUES (?,?,?,?,?)')->execute($c);
    }
    ok(count($contacten) . ' contactpersonen aangemaakt');
} catch (Exception $e) { err('Contactpersonen: ' . $e->getMessage()); }

// ─── Apparaten ───────────────────────────────────────────────────────────────
$jaar = date('Y');
// Bepaal hoogste QR-nummer
$laatste = $db->query("SELECT qr_code FROM apparaten WHERE qr_code LIKE 'QR{$jaar}.%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$teller  = $laatste ? ((int)substr($laatste, strrpos($laatste, '.') + 1) + 1) : 1;

$apparaten_data = [
    // klant_id, type, merk, model, serienummer, aanschafdatum, garantie_tot, locatie, status, ip, mac, firmware, notities
    [$klant_ids[0], 'desktop', 'Dell',     'OptiPlex 7090',      'DL-'  . rand(10000,99999), '2022-03-15', '2025-03-15', 'Kantoor',      'actief', '192.168.1.10', 'AA:BB:CC:11:22:33', 'BIOS 1.12.0', 'Balie PC receptie'],
    [$klant_ids[0], 'laptop',  'HP',       'EliteBook 840 G8',   'HP-'  . rand(10000,99999), '2021-11-01', '2024-11-01', 'Mobiel',       'actief', '192.168.1.20', 'AA:BB:CC:11:22:44', 'F.70',        'Laptop eigenaar Henk'],
    [$klant_ids[0], 'netwerk', 'Ubiquiti', 'UniFi USG',          'USG-' . rand(10000,99999), '2020-05-20', '2023-05-20', 'Serverruimte', 'actief', '192.168.1.1',  'AA:BB:CC:11:22:55', '4.4.57',      'Gateway/router'],
    [$klant_ids[0], 'netwerk', 'Ubiquiti', 'UniFi Switch 24',    'USW-' . rand(10000,99999), '2020-05-20', '2023-05-20', 'Serverruimte', 'actief', '192.168.1.2',  'AA:BB:CC:11:22:66', '6.6.55',      '24-poorts switch'],
    [$klant_ids[1], 'desktop', 'Lenovo',   'ThinkCentre M90q',   'LC-'  . rand(10000,99999), '2023-01-10', '2026-01-10', 'Werkplaats',   'actief', '10.0.0.10',    'BB:CC:DD:22:33:44', 'M3IKT3A',     'PC balie garage'],
    [$klant_ids[1], 'server',  'HP',       'ProLiant ML30 G10',  'ML-'  . rand(10000,99999), '2021-06-15', '2024-06-15', 'Serverruimte', 'actief', '10.0.0.2',     'BB:CC:DD:22:33:55', 'U30 2.60',    'Fileserver + boekhouding'],
    [$klant_ids[1], 'netwerk', 'Cisco',    'SG350-28',           'CSG-' . rand(10000,99999), '2021-06-15', '2024-06-15', 'Serverruimte', 'actief', '10.0.0.1',     'BB:CC:DD:22:33:66', '2.5.5.23',    'Core switch'],
    [$klant_ids[2], 'laptop',  'Apple',    'MacBook Pro 14"',    'MBP-' . rand(10000,99999), '2022-09-01', '2025-09-01', 'Behandelkamer','actief', '172.16.0.10',  'CC:DD:EE:33:44:55', 'macOS 14.4',  'Laptop Dr. Jansen'],
    [$klant_ids[2], 'desktop', 'Apple',    'Mac mini M2',        'MCM-' . rand(10000,99999), '2023-03-01', '2026-03-01', 'Receptie',     'actief', '172.16.0.11',  'CC:DD:EE:33:44:66', 'macOS 14.4',  'Receptie balie'],
    [$klant_ids[2], 'netwerk', 'Cisco',    'RV340 Router',       'RV3-' . rand(10000,99999), '2020-12-01', '2023-12-01', 'Serverruimte', 'actief', '172.16.0.1',   'CC:DD:EE:33:44:77', '1.0.03.22',   'Firewall/router'],
    [$klant_ids[3], 'server',  'Dell',     'PowerEdge T340',     'DPE-' . rand(10000,99999), '2021-03-20', '2024-03-20', 'Serverruimte', 'actief', '10.10.0.2',    'DD:EE:FF:44:55:66', 'iDRAC 4.40',  'Primaire fileserver'],
    [$klant_ids[3], 'server',  'Dell',     'PowerEdge T140',     'DPE-' . rand(10000,99999), '2021-03-20', '2024-03-20', 'Serverruimte', 'actief', '10.10.0.3',    'DD:EE:FF:44:55:77', 'iDRAC 4.40',  'Backupserver'],
    [$klant_ids[3], 'desktop', 'HP',       'ProDesk 600 G6',     'HPD-' . rand(10000,99999), '2022-07-05', '2025-07-05', 'Kantoor',      'actief', '10.10.0.11',   'DD:EE:FF:44:55:88', 'P21 v2.10',   'PC directie'],
    [$klant_ids[3], 'netwerk', 'Fortinet', 'FortiGate 60F',      'FGT-' . rand(10000,99999), '2022-01-10', '2025-01-10', 'Serverruimte', 'actief', '10.10.0.1',    'DD:EE:FF:44:55:99', '7.4.3',       'Firewall'],
];

$apparaat_ids = [];
try {
    foreach ($apparaten_data as $a) {
        $qr = sprintf('QR%s.%03d', $jaar, $teller++);
        $db->prepare('INSERT INTO apparaten (klant_id, qr_code, type, merk, model, serienummer, aanschafdatum, garantie_tot, locatie, status, ip_adres, mac_adres, firmware, notities) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([$a[0], $qr, $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7], $a[8], $a[9], $a[10], $a[11], $a[12]]);
        $apparaat_ids[] = (int)$db->lastInsertId();
    }
    ok(count($apparaten_data) . ' apparaten aangemaakt');
} catch (Exception $e) { err('Apparaten: ' . $e->getMessage()); }

// ─── Inloggegevens (AES-256 versleuteld) ─────────────────────────────────────
$inlog_data = [
    [$klant_ids[0], 'netwerk', 'WiFi wachtwoord intern',     'connect4it-wifi',     'Bakkerij@WiFi2024!',    '',                             'Intern WiFi netwerk 2.4/5GHz'],
    [$klant_ids[0], 'netwerk', 'WiFi gasten',                'gasten',              'Gasten#Korrel99',       '',                             'Gastennetwerk apart VLAN'],
    [$klant_ids[0], 'portaal', 'DirectAdmin hosting',        'dekorrel',            'Hosting#Secure2024',    'https://cp.mijnhost.nl',        'Webhostingbeheer'],
    [$klant_ids[0], 'server',  'NAS Synology',               'admin',               'NAS!Korrel2023',        'https://192.168.1.5:5001',      'DiskStation DS920+'],
    [$klant_ids[1], 'server',  'Windows Server beheer',      'Administrator',       'Garage$Admin2023!',     '',                             'Lokale server ThinkCentre'],
    [$klant_ids[1], 'cloud',   'Microsoft 365 admin',        'admin@garagesmit.nl', 'M365!Garage#2024',      'https://admin.microsoft.com',   'Globale M365-beheerder'],
    [$klant_ids[1], 'netwerk', 'Cisco switch beheer',        'admin',               'Cisco!Switch$22',       'https://10.0.0.1',              'SG350-28 webinterface'],
    [$klant_ids[1], 'overig',  'Boekhoudpakket Exact',       'psmit',               'Exact#Smit2024!',       'https://start.exactonline.nl',  'Exact Online licentie'],
    [$klant_ids[2], 'netwerk', 'Cisco RV340 firewall',       'admin',               'Cisco!Firewall#2022',   'https://172.16.0.1',            'Webinterface firewall'],
    [$klant_ids[2], 'portaal', 'Dental patiëntensoftware',   'jansen.praktijk',     'Dental#2024Veilig!',    'https://app.dentalsoft.nl',     'Planning + patiëntdossiers'],
    [$klant_ids[2], 'cloud',   'iCloud beheer Apple',        'praktijk@jansen.nl',  'iCloud$Apple#23',       'https://appleid.apple.com',     'Apple Business Manager'],
    [$klant_ids[3], 'server',  'Dell iDRAC primaire server', 'root',                'iDRAC!Dell$Primary1',   'https://10.10.0.2:443',         'Out-of-band beheer server 1'],
    [$klant_ids[3], 'server',  'Dell iDRAC backupserver',    'root',                'iDRAC!Dell$Backup2',    'https://10.10.0.3:443',         'Out-of-band beheer server 2'],
    [$klant_ids[3], 'netwerk', 'FortiGate firewall',         'admin',               'Forti!Gate#VanDijk24',  'https://10.10.0.1',             'FortiOS webinterface'],
    [$klant_ids[3], 'cloud',   'Azure portaal',              'rob@vandijktransport.nl','Azure$VanDijk#2024', 'https://portal.azure.com',      'Productie Azure subscription'],
    [$klant_ids[3], 'overig',  'VPN pre-shared key',         'ipsec',               'VPN!PSK#Transport99',   '',                             'Site-to-site VPN naar Venlo'],
];
try {
    foreach ($inlog_data as $il) {
        $enc = encrypt_wachtwoord($il[4]);
        $db->prepare('INSERT INTO inloggegevens (klant_id, categorie, label, gebruikersnaam, wachtwoord_enc, url, notities, aangemaakt_door) VALUES (?,?,?,?,?,?,?,1)')
           ->execute([$il[0], $il[1], $il[2], $il[3], $enc, $il[5], $il[6]]);
    }
    ok(count($inlog_data) . ' inloggegevens aangemaakt (versleuteld opgeslagen)');
} catch (Exception $e) { err('Inloggegevens: ' . $e->getMessage()); }

// ─── Notities ────────────────────────────────────────────────────────────────
$notities = [
    [$klant_ids[0], 'Netwerkschema',    "Intern netwerk: 192.168.1.0/24\nRouter/GW: 192.168.1.1 (Ubiquiti USG)\nDHCP range: .100-.200\nNAS: 192.168.1.5\nPrinter: 192.168.1.50\nBalie PC: 192.168.1.10"],
    [$klant_ids[0], 'Backup beleid',    "Dagelijks: NAS Synology (23:00 auto-snapshot)\nWekelijks: USB-schijf (maandag, handmatig)\nRetentie: 30 snapshots\nLaatste controle: 2026-03-01 OK"],
    [$klant_ids[1], 'Serverinformatie', "Windows Server 2022 Standard\nRollen: Fileserver, DHCP, DNS\nIP: 10.0.0.2 (statisch)\nDomeinnaam: SMIT.LOCAL\nAdmin wachtwoord: zie wachtwoordkluis"],
    [$klant_ids[1], 'Microsoft 365',    "Tenant: garagesmit.onmicrosoft.com\n5x Business Premium licenties\nMFA ingeschakeld voor alle gebruikers\nConditional Access policy actief"],
    [$klant_ids[2], 'AVG / NEN7510',    "Verwerkersovereenkomst getekend: 2023-05-01\nPrivacycontactpersoon: Dr. Jansen\nDatabeheerder aangesteld\nJaarlijkse audit gepland: 2026-05"],
    [$klant_ids[2], 'Netwerkinrichting',"Netwerk: 172.16.0.0/24\nFirewall: 172.16.0.1\nBehandelkamer 1: 172.16.0.10\nReceptie: 172.16.0.11\nPrinter: 172.16.0.20\nWiFi AP: 172.16.0.30"],
    [$klant_ids[3], 'VPN configuratie', "Site-to-site VPN Rotterdam - Venlo\nProtocol: IPSec IKEv2\nPre-shared key: zie wachtwoordkluis > VPN PSK\nTunnel up sinds: 2025-01-15\nMonitoring: Zabbix"],
    [$klant_ids[3], 'Azure omgeving',   "Subscription: Van Dijk Production\nResource groups: rg-infra, rg-backup\nVMs: 2x Windows Server 2022\nBackup: Azure Backup dagelijks 02:00\nKosten ca. €320/maand"],
];
try {
    foreach ($notities as $n) {
        $db->prepare('INSERT INTO klant_notities (klant_id, titel, inhoud, aangemaakt_door) VALUES (?,?,?,1)')->execute($n);
    }
    ok(count($notities) . ' notities aangemaakt');
} catch (Exception $e) { err('Notities: ' . $e->getMessage()); }

// ─── Servicehistorie ─────────────────────────────────────────────────────────
$ai = $apparaat_ids;
$service = [
    [$klant_ids[0], $ai[0]??null,  '2026-01-10', 'onderhoud', 'Windows updates uitgevoerd, schijf gecontroleerd. Geen problemen gevonden.',            'Thom'],
    [$klant_ids[0], $ai[2]??null,  '2026-02-03', 'storing',   'Router crashte na stroomstoring. Firmware herinstalleerd, config teruggezet via backup.','Thom'],
    [$klant_ids[0], null,          '2026-03-05', 'bezoek',    'Kwartaalonderhoudsbezoek. Alle systemen gecontroleerd, WiFi-kanalen geoptimaliseerd.',   'Thom'],
    [$klant_ids[1], $ai[5]??null,  '2026-01-20', 'onderhoud', 'Server preventief onderhoud: RAM getest (OK), fans gereinigd, Windows updates.',         'Thom'],
    [$klant_ids[1], null,          '2026-03-01', 'bezoek',    'Nieuwe werkplek monteur ingericht. PC geïnstalleerd met Windows 11 Pro + Office 365.',   'Thom'],
    [$klant_ids[1], $ai[4]??null,  '2026-02-15', 'storing',   'PC balie kon niet opstarten. Defecte SSD vervangen door Samsung 870 EVO 500GB.',         'Thom'],
    [$klant_ids[2], $ai[7]??null,  '2026-02-14', 'update',    'macOS Sequoia update uitgevoerd. Tandartssoftware na update getest — volledig OK.',       'Thom'],
    [$klant_ids[2], $ai[9]??null,  '2026-01-08', 'onderhoud', 'Firewall regels gecontroleerd en bijgewerkt. Firmware update uitgevoerd naar 1.0.03.22.',  'Thom'],
    [$klant_ids[2], null,          '2026-03-12', 'bezoek',    'Jaarlijkse NEN7510 controle. Alle toegangsrechten gecontroleerd, rapport opgesteld.',     'Thom'],
    [$klant_ids[3], $ai[10]??null, '2026-01-05', 'storing',   'Primaire server onbereikbaar na netwerkaanpassing. IP-conflict opgelost, VLAN gecheckt.', 'Thom'],
    [$klant_ids[3], $ai[13]??null, '2026-02-20', 'update',    'FortiGate firmware bijgewerkt naar 7.4.3. Security policies gecontroleerd na update.',    'Thom'],
    [$klant_ids[3], null,          '2026-03-10', 'onderhoud', 'Kwartaalcontrole alle servers, switches en firewall. VPN-tunnel stabiel. Alles OK.',      'Thom'],
];
try {
    foreach ($service as $s) {
        $db->prepare('INSERT INTO service_historie (klant_id, apparaat_id, datum, type, omschrijving, opgelost_door, aangemaakt_door) VALUES (?,?,?,?,?,?,1)')->execute($s);
    }
    ok(count($service) . ' servicehistorie-items aangemaakt');
} catch (Exception $e) { err('Servicehistorie: ' . $e->getMessage()); }

// ─── Contracten ──────────────────────────────────────────────────────────────
$contracten = [
    [$klant_ids[0], 'Basis beheercontract',       'basis',     '2024-01-01', '2026-12-31', "Maandelijks 2 uur ondersteuning\nReactietijd: 4 uur op werkdagen\nJaarlijks onderhoudsbezoek"],
    [$klant_ids[1], 'Standaard SLA',              'standaard', '2023-06-01', '2026-05-31', "24/7 monitoring via Zabbix\nReactietijd: 2 uur\nMaandelijkse rapportage\nKwartaalonderhoudsbezoek"],
    [$klant_ids[2], 'Premium zorgcontract',       'premium',   '2024-03-01', '2027-02-28', "NEN7510-compliancy begeleiding\nDagelijkse backup-verificatie\nReactietijd: 2 uur\nJaarlijkse audit"],
    [$klant_ids[3], 'Standaard transportbeheer',  'standaard', '2025-01-01', '2027-12-31', "Server- en firewallbeheer\nVPN-monitoring en beheer\nKwartaalonderhoudsbezoek\nAzure-kostenbewaking"],
];
try {
    foreach ($contracten as $c) {
        $db->prepare('INSERT INTO contracten (klant_id, omschrijving, sla_niveau, start_datum, eind_datum, notities) VALUES (?,?,?,?,?,?)')->execute($c);
    }
    ok(count($contracten) . ' contracten aangemaakt');
} catch (Exception $e) { err('Contracten: ' . $e->getMessage()); }

// ─── Office 365 tenants ───────────────────────────────────────────────────────
$o365_data = [
    // klant_id, tenant_naam, tenant_id, admin_email, wachtwoord, notities
    [$klant_ids[1], 'garagesmit.onmicrosoft.com',      'a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'admin@garagesmit.nl',      'M365!Admin#Smit2024',  "Intune MDM ingeschakeld\nConditional Access: blokkeer toegang buiten NL"],
    [$klant_ids[2], 'jansenpraktijk.onmicrosoft.com',  'b2c3d4e5-f6a7-8901-bcde-f12345678901', 'praktijk@jansen-tand.nl', 'O365!Tandarts#2024',   "Intune MDM voor MacBooks\nAVG: toegang beperkt tot NL"],
    [$klant_ids[3], 'vandijktransport.onmicrosoft.com','c3d4e5f6-a7b8-9012-cdef-123456789012', 'rob@vandijktransport.nl', 'Azure$M365!VanDijk',   "Azure AD Connect met lokaal AD\nConditional Access + Intune"],
];
try {
    foreach ($o365_data as $o) {
        $enc = encrypt_wachtwoord($o[4]);
        $db->prepare('INSERT INTO klant_o365 (klant_id, tenant_naam, tenant_id, admin_email, admin_wachtwoord_enc, notities) VALUES (?,?,?,?,?,?)')
           ->execute([$o[0], $o[1], $o[2], $o[3], $enc, $o[5]]);
    }
    ok(count($o365_data) . ' Office 365 tenants aangemaakt');
} catch (Exception $e) { err('Office 365 tenants: ' . $e->getMessage()); }

// ─── Office 365 gebruikers ────────────────────────────────────────────────────
$o365_gebruikers_data = [
    // klant_id, naam, email, wachtwoord, rol, licentie_type, notities
    [$klant_ids[1], 'Peter Smit',      'peter@garagesmit.nl',  'Gebruiker!Smit#24',  'Globale beheerder',   'Microsoft 365 Business Premium',  'Eigenaar, volledige beheertoegang'],
    [$klant_ids[1], 'Karin Smit',      'karin@garagesmit.nl',  'Karin!M365#2024',    'Gebruiker',           'Microsoft 365 Business Premium',  'Administratie'],
    [$klant_ids[1], 'Jan Monteur',     'jan@garagesmit.nl',    'Jan!Garage#24',      'Gebruiker',           'Microsoft 365 Business Basic',    'Monteur werkplaats'],
    [$klant_ids[1], 'Lisa Balie',      'lisa@garagesmit.nl',   'Lisa!Balie#2024',    'Gebruiker',           'Microsoft 365 Business Basic',    'Balie medewerker'],
    [$klant_ids[1], 'Tom Inkoop',      'tom@garagesmit.nl',    'Tom!Inkoop#24',      'Gebruiker',           'Exchange Online Plan 1',          'Inkoop, alleen e-mail nodig'],
    [$klant_ids[2], 'Dr. A. Jansen',   'ajansen@jansen-tand.nl',   'Jansen!Doc#2024', 'Globale beheerder', 'Microsoft 365 Business Standard', 'Eigenaar praktijk'],
    [$klant_ids[2], 'Sandra Pieterse', 'receptie@jansen-tand.nl',  'Sandra!Rec#24',  'Gebruiker',           'Microsoft 365 Business Standard', 'Receptie + planning'],
    [$klant_ids[2], 'Mark Assistent',  'mark@jansen-tand.nl',      'Mark!Tand#2024', 'Gebruiker',           'Microsoft 365 Business Basic',    'Tandartsassistent'],
    [$klant_ids[3], 'Rob van Dijk',    'rob@vandijktransport.nl',   'Rob!Dir#M365',   'Globale beheerder',   'Microsoft 365 E3',                'Directeur, volledige toegang'],
    [$klant_ids[3], 'Mike de Groot',   'mike@vandijktransport.nl',  'Mike!IT#E3#24',  'Gebruikersbeheerder', 'Microsoft 365 E3',                'IT contactpersoon'],
    [$klant_ids[3], 'Sandra Logistiek','sandra@vandijktransport.nl','Sandra!Log#24',  'Gebruiker',           'Microsoft 365 E3',                'Logistiek manager'],
    [$klant_ids[3], 'Ahmed Chauffeur', 'ahmed@vandijktransport.nl', 'Ahmed!Ch#2024',  'Gebruiker',           'Microsoft 365 Business Basic',    'Chauffeur, mobiele toegang'],
];
try {
    foreach ($o365_gebruikers_data as $gu) {
        $enc = $gu[3] !== '' ? encrypt_wachtwoord($gu[3]) : null;
        $db->prepare('INSERT INTO klant_o365_gebruikers (klant_id, naam, email, wachtwoord_enc, rol, licentie_type, notities) VALUES (?,?,?,?,?,?,?)')
           ->execute([$gu[0], $gu[1], $gu[2], $enc, $gu[4], $gu[5], $gu[6]]);
    }
    ok(count($o365_gebruikers_data) . ' Office 365 gebruikers aangemaakt');
} catch (Exception $e) { err('Office 365 gebruikers: ' . $e->getMessage()); }

// ─── Yeastar centralen ────────────────────────────────────────────────────────
$yeastar_data = [
    [$klant_ids[0], 'S-Series S100',   '192.168.1.200', '8088', 'https://192.168.1.200:8088', 'admin', 'Yeastar!Bakkerij#24', '30.13.0.18', "8 extensies actief\nVoicemailbox ingesteld voor Henk"],
    [$klant_ids[3], 'P-Series P560',   '10.10.0.50',    '8086', 'https://10.10.0.50:8086',   'admin', 'Forti!PBX#VanDijk23', '84.12.0.10', "28 extensies\nIntegratie met CRM actief\nSIP-trunk via KPN"],
    [$klant_ids[3], 'Cloud PBX',       '',              '',     'https://pbx.yeastar.cloud',  'admin', 'Cloud!PBX#Backup22',  '',           "Backup/uitwijkcentrale\nActief bij storing on-premise"],
];
try {
    foreach ($yeastar_data as $y) {
        $enc = encrypt_wachtwoord($y[6]);
        $db->prepare('INSERT INTO klant_yeastar (klant_id, model, ip_adres, poort, admin_url, admin_gebruiker, admin_wachtwoord_enc, firmware, notities) VALUES (?,?,?,?,?,?,?,?,?)')
           ->execute([$y[0], $y[1], $y[2], $y[3], $y[4], $y[5], $enc, $y[7], $y[8]]);
    }
    ok(count($yeastar_data) . ' Yeastar centralen aangemaakt');
} catch (Exception $e) { err('Yeastar: ' . $e->getMessage()); }

// ─── Simpbx ───────────────────────────────────────────────────────────────────
$simpbx_data = [
    [$klant_ids[1], 1, 8,  'smit.simpbx.nl',      'https://pbx.simpbx.nl/smit',    'smit_admin', 'Simpbx!Smit#2024',   "8 extensies\nBelgroepen: Balie, Werkplaats\nOpeningstijden ingesteld"],
    [$klant_ids[2], 1, 4,  'jansen.simpbx.nl',    'https://pbx.simpbx.nl/jansen',  'jansen_adm', 'Simpbx!Jansen#24',   "4 extensies\nWachtrij patiënten ingesteld\nHerinnering: AVG-compliance check jaarlijks"],
];
try {
    foreach ($simpbx_data as $s) {
        $enc = encrypt_wachtwoord($s[6]);
        $db->prepare('INSERT INTO klant_simpbx (klant_id, actief, aantal_extensies, sip_domein, admin_url, admin_gebruiker, admin_wachtwoord_enc, notities) VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $enc, $s[7]]);
    }
    ok(count($simpbx_data) . ' Simpbx records aangemaakt');
} catch (Exception $e) { err('Simpbx: ' . $e->getMessage()); }

// ─── Internet ─────────────────────────────────────────────────────────────────
$internet_data = [
    [$klant_ids[0], 'Routit',  '',          'Glasvezel', '500', '500', '85.144.12.34',  '2026-12-31', "Symmetrisch glasvezel 500/500\nVast IP-adres inbegrepen\nSLA: 4 uur herstel"],
    [$klant_ids[1], 'Pocos',   '',          'DSL/VDSL',  '200', '30',  '',              '2027-03-01', "VDSL2+ verbinding\nDynamisch IP via PPPoE\nBack-up via 4G router (Teltonika)"],
    [$klant_ids[2], 'KPN',     '',          'Glasvezel', '1000','1000','85.145.87.201', '2027-06-30', "Glasvezel 1Gbit zakelijk\nVast IP voor VPN-toegang\nRedundante verbinding via Ziggo"],
    [$klant_ids[3], 'Anders',  'Colt Telecom','Lease line','100','100','212.118.33.50', '2028-01-01', "Dedicated 100Mbit lease line\nSLA: 99.9% uptime garantie\nMonitoring via NOC Colt"],
];
try {
    foreach ($internet_data as $i) {
        $db->prepare('INSERT INTO klant_internet (klant_id, provider, provider_anders, type, snelheid_down, snelheid_up, ip_adres, contract_datum, notities) VALUES (?,?,?,?,?,?,?,?,?)')
           ->execute([$i[0], $i[1], $i[2], $i[3], $i[4], $i[5], $i[6], $i[7] ?: null, $i[8]]);
    }
    ok(count($internet_data) . ' internet-records aangemaakt');
} catch (Exception $e) { err('Internet: ' . $e->getMessage()); }

echo '</div><hr>';
echo '<div class="alert alert-success mt-3"><strong>Klaar!</strong> Ga naar <a href="index.php">het dashboard</a>.</div>';
echo '<div class="alert alert-warning"><strong>Geef een seintje</strong> — dan wordt testdata.php weer geblokkeerd.</div>';
echo '</body></html>';
