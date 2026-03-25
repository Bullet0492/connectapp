<?php
/**
 * Testdata — vult de database met voorbeeldklanten, apparaten, contactpersonen,
 * inloggegevens, notities, servicehistorie en contracten.
 * Eenmalig uitvoeren, daarna verwijderen.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Testdata</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Testdata laden</h3><div class="mt-3">';

function ok(string $t): void { echo '<div class="text-success mb-1">&#10003; ' . htmlspecialchars($t) . '</div>'; }
function info(string $t): void { echo '<div class="text-info mb-1">&#8505; ' . htmlspecialchars($t) . '</div>'; }

// ─── Klanten ─────────────────────────────────────────────────────────────────
$klanten = [
    ['Bakkerij De Korrel',     'Bakkerij De Korrel BV',  'Molenstraat 12',   '7411AB', 'Deventer',    '0570-612345', 'info@dekorrel.nl',       'https://www.dekorrel.nl',       'BDK001'],
    ['Autogarage Smit',        'Autogarage Smit VOF',    'Industrieweg 88',  '2700AK', 'Zoetermeer',  '079-3214567', 'contact@garragesmit.nl', 'https://www.garagesmit.nl',     'AGS002'],
    ['Tandartspraktijk Jansen','Jansen Tandheelkunde',   'Kerkplein 3',      '5038AC', 'Tilburg',     '013-5432100', 'praktijk@jansen-tand.nl','https://www.jansen-tand.nl',    'JTH003'],
    ['Transport Van Dijk',     'Van Dijk Transport BV',  'Havenweg 201',     '3089JH', 'Rotterdam',   '010-4123456', 'info@vandijktransport.nl','https://www.vandijktransport.nl','VDT004'],
];

$klant_ids = [];
foreach ($klanten as $k) {
    $check = $db->prepare('SELECT id FROM klanten WHERE email = ?');
    $check->execute([$k[5]]);
    if ($check->fetchColumn()) {
        info("Klant al aanwezig: {$k[0]}");
        $klant_ids[] = (int)$db->prepare('SELECT id FROM klanten WHERE email = ?')->execute([$k[5]]) ?: 0;
        // re-fetch id
        $r = $db->prepare('SELECT id FROM klanten WHERE email = ?'); $r->execute([$k[5]]);
        $klant_ids[] = (int)$r->fetchColumn();
        continue;
    }
    $db->prepare('INSERT INTO klanten (naam, bedrijf, adres, postcode, stad, telefoon, email, website, intra_id) VALUES (?,?,?,?,?,?,?,?,?)')
       ->execute($k);
    $klant_ids[] = (int)$db->lastInsertId();
    ok("Klant aangemaakt: {$k[0]}");
}

// ─── Contactpersonen ─────────────────────────────────────────────────────────
$contacten = [
    [$klant_ids[0], 'Henk de Korrel',    'Eigenaar',   'henk@dekorrel.nl',        '06-11223344'],
    [$klant_ids[0], 'Lisa Bakker',       'Administratie','lisa@dekorrel.nl',       '06-22334455'],
    [$klant_ids[1], 'Peter Smit',        'Eigenaar',   'peter@garagesmit.nl',      '06-33445566'],
    [$klant_ids[2], 'Dr. A. Jansen',     'Tandarts',   'ajansen@jansen-tand.nl',   '06-44556677'],
    [$klant_ids[2], 'Sandra Pieterse',   'Receptie',   'receptie@jansen-tand.nl',  '06-55667788'],
    [$klant_ids[3], 'Rob van Dijk',      'Directeur',  'rob@vandijktransport.nl',  '06-66778899'],
];
foreach ($contacten as $c) {
    $db->prepare('INSERT INTO contactpersonen (klant_id, naam, functie, email, telefoon) VALUES (?,?,?,?,?)')->execute($c);
}
ok(count($contacten) . ' contactpersonen aangemaakt');

// ─── Apparaten ───────────────────────────────────────────────────────────────
$jaar = date('Y');
$qr_teller = 1;
$apparaat_ids = [];
$apparaten_data = [
    [$klant_ids[0], 'desktop', 'Dell',    'OptiPlex 7090',    'DL7090-' . rand(1000,9999), '2022-03-15', '2025-03-15', 'Kantoor',     'actief', '192.168.1.10', 'AA:BB:CC:11:22:33'],
    [$klant_ids[0], 'laptop',  'HP',      'EliteBook 840 G8', 'HP840-'  . rand(1000,9999), '2021-11-01', '2024-11-01', 'Mobiel',      'actief', '192.168.1.11', 'AA:BB:CC:11:22:44'],
    [$klant_ids[0], 'netwerk', 'Ubiquiti','UniFi USG',        'USG-'    . rand(1000,9999), '2020-05-20', '2023-05-20', 'Serverruimte','actief', '192.168.1.1',  'AA:BB:CC:11:22:55'],
    [$klant_ids[1], 'desktop', 'Lenovo',  'ThinkCentre M90q', 'LC90Q-'  . rand(1000,9999), '2023-01-10', '2026-01-10', 'Werkplaats',  'actief', '10.0.0.10',    'BB:CC:DD:22:33:44'],
    [$klant_ids[1], 'server',  'HP',      'ProLiant ML30',    'HPML30-' . rand(1000,9999), '2021-06-15', '2024-06-15', 'Serverruimte','actief', '10.0.0.2',     'BB:CC:DD:22:33:55'],
    [$klant_ids[2], 'laptop',  'Apple',   'MacBook Pro 14"',  'MBP14-'  . rand(1000,9999), '2022-09-01', '2025-09-01', 'Behandelkamer','actief','172.16.0.10', 'CC:DD:EE:33:44:55'],
    [$klant_ids[2], 'netwerk', 'Cisco',   'RV340 Router',     'CSRV-'   . rand(1000,9999), '2020-12-01', '2023-12-01', 'Serverruimte','actief', '172.16.0.1',   'CC:DD:EE:33:44:66'],
    [$klant_ids[3], 'server',  'Dell',    'PowerEdge T340',   'DPE340-' . rand(1000,9999), '2021-03-20', '2024-03-20', 'Magazijn',    'actief', '10.10.0.2',    'DD:EE:FF:44:55:66'],
    [$klant_ids[3], 'desktop', 'HP',      'ProDesk 600 G6',   'HPD600-' . rand(1000,9999), '2022-07-05', '2025-07-05', 'Kantoor',     'actief', '10.10.0.11',   'DD:EE:FF:44:55:77'],
];
foreach ($apparaten_data as $a) {
    $qr = sprintf('QR%s.%03d', $jaar, $qr_teller++);
    $db->prepare('INSERT INTO apparaten (klant_id, qr_code, type, merk, model, serienummer, aanschafdatum, garantie_tot, locatie, status, ip_adres, mac_adres) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$a[0], $qr, $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7], $a[8], $a[9], $a[10]]);
    $apparaat_ids[] = (int)$db->lastInsertId();
}
ok(count($apparaten_data) . ' apparaten aangemaakt');

// ─── Inloggegevens (versleuteld) ──────────────────────────────────────────────
$inlog_data = [
    [$klant_ids[0], 'netwerk',  'WiFi wachtwoord',       'connect4it',      'Bakkerij@WiFi2024!',  '',                           'WiFi gastnetwerk'],
    [$klant_ids[0], 'portaal',  'DirectAdmin hosting',   'dekorrel',        'Hosting#Secure99',    'https://cp.mijnhost.nl',     'Hostingbeheer'],
    [$klant_ids[1], 'server',   'Windows Server admin',  'Administrator',   'Garage$Admin2023',    '',                           'Lokale server'],
    [$klant_ids[1], 'cloud',    'Microsoft 365 beheer',  'admin@smit.nl',   'M365!Garage2024',     'https://admin.microsoft.com','Tenant-beheerder'],
    [$klant_ids[2], 'netwerk',  'Firewall beheer',       'admin',           'Cisco!Firewall22',    'https://172.16.0.1',         'Cisco RV340'],
    [$klant_ids[2], 'portaal',  'Patiëntensofteem',      'jansen.praktijk', 'Dental#2024Veilig',   'https://app.dentalsoft.nl',  'Planningsmodule'],
    [$klant_ids[3], 'server',   'ESXi beheer',           'root',            'VMware!Root2023',     'https://10.10.0.2:443',      'VMware ESXi host'],
    [$klant_ids[3], 'cloud',    'Azure portaal',         'rob@vandijk.nl',  'Azure$VanDijk2024',   'https://portal.azure.com',   'Productie-omgeving'],
];
foreach ($inlog_data as $il) {
    $enc = encrypt_wachtwoord($il[4]);
    $db->prepare('INSERT INTO inloggegevens (klant_id, categorie, label, gebruikersnaam, wachtwoord_enc, url, notities) VALUES (?,?,?,?,?,?,?)')
       ->execute([$il[0], $il[1], $il[2], $il[3], $enc, $il[5], $il[6]]);
}
ok(count($inlog_data) . ' inloggegevens aangemaakt (versleuteld)');

// ─── Notities ────────────────────────────────────────────────────────────────
$notities = [
    [$klant_ids[0], 'Netwerkschema', "Intern netwerk: 192.168.1.0/24\nRouter: 192.168.1.1 (Ubiquiti USG)\nDHCP range: 192.168.1.100-200\nPrinter: 192.168.1.50"],
    [$klant_ids[1], 'Backup beleid', "Dagelijks: lokale NAS (23:00)\nWekelijks: Acronis Cloud (zondag 02:00)\nRetentie: 30 dagen\nLaatste test: 2026-01-15 — geslaagd"],
    [$klant_ids[2], 'AVG gegevens',  "Verwerkersovereenkomst getekend: 2023-05-01\nContactpersoon privacy: Dr. Jansen\nGegevens bewaard conform NEN7510"],
    [$klant_ids[3], 'VPN toegang',   "Site-to-site VPN naar magazijn 2 (Venlo)\nProtocol: IPSec IKEv2\nPre-shared key: zie wachtwoordkluis → VPN PSK"],
];
foreach ($notities as $n) {
    $db->prepare('INSERT INTO klant_notities (klant_id, titel, inhoud, aangemaakt_door) VALUES (?,?,?,1)')->execute([$n[0], $n[1], $n[2]]);
}
ok(count($notities) . ' notities aangemaakt');

// ─── Servicehistorie ─────────────────────────────────────────────────────────
$service = [
    [$klant_ids[0], $apparaat_ids[0], '2026-01-10', 'onderhoud',  'Windows updates uitgevoerd, schijf gedefragmenteerd. Systeem loopt goed.',      'Thom'],
    [$klant_ids[0], $apparaat_ids[2], '2026-02-03', 'storing',    'Router crashte na stroomstoring. Firmware herinstalleerd, config teruggezet.',   'Thom'],
    [$klant_ids[1], $apparaat_ids[4], '2026-01-20', 'onderhoud',  'Server preventief onderhoud: RAM getest, fans gereinigd, RAID-status OK.',       'Thom'],
    [$klant_ids[1], null,             '2026-03-01', 'bezoek',     'Nieuwe werkplek ingericht voor monteur. PC geïnstalleerd met Windows 11 Pro.',   'Thom'],
    [$klant_ids[2], $apparaat_ids[5], '2026-02-14', 'update',     'macOS Sequoia update uitgevoerd. Tandartssoftware getest na update — OK.',       'Thom'],
    [$klant_ids[3], $apparaat_ids[7], '2026-01-05', 'storing',    'Server niet bereikbaar na netwerkwijziging. IP conflict opgelost, VLAN gecheckt.','Thom'],
    [$klant_ids[3], null,             '2026-03-10', 'onderhoud',  'Kwartaalcontrole: alle servers, switches en firewall gecontroleerd. Alles OK.',  'Thom'],
];
foreach ($service as $s) {
    $db->prepare('INSERT INTO service_historie (klant_id, apparaat_id, datum, type, omschrijving, opgelost_door, aangemaakt_door) VALUES (?,?,?,?,?,?,1)')
       ->execute($s);
}
ok(count($service) . ' servicehistorie-items aangemaakt');

// ─── Contracten ──────────────────────────────────────────────────────────────
$contracten = [
    [$klant_ids[0], 'Basis beheercontract',      'basis',     '2024-01-01', '2026-12-31', 'Maandelijks 2 uur ondersteuning, reactietijd 4 uur.'],
    [$klant_ids[1], 'Standaard SLA',             'standaard', '2023-06-01', '2026-05-31', '24/7 monitoring, reactietijd 2 uur, maandrapportage.'],
    [$klant_ids[2], 'Premium zorgcontract',      'premium',   '2024-03-01', '2027-02-28', 'NEN7510-compliancy, dagelijkse backup-check, 4u reactie.'],
    [$klant_ids[3], 'Standaard transportbeheer', 'standaard', '2025-01-01', '2027-12-31', 'Serverbeheer, VPN-beheer, kwartaalonderhoudsbezoek.'],
];
foreach ($contracten as $c) {
    $db->prepare('INSERT INTO contracten (klant_id, omschrijving, sla_niveau, start_datum, eind_datum, notities) VALUES (?,?,?,?,?,?)')->execute($c);
}
ok(count($contracten) . ' contracten aangemaakt');

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong> Testdata geladen. Ga naar <a href="index.php">het dashboard</a>.</div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder <code>testdata.php</code> na gebruik (of laat .htaccess het blokkeren).</div>';
echo '</body></html>';
