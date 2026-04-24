<?php
/**
 * Helper-functies voor de domein-check module.
 *
 * Alle functies zijn defensief geschreven: wanneer iets faalt (timeout,
 * geen DNS, geen TCP op 25, geen WHOIS server, etc.) retourneren ze lege
 * arrays of null — de pagina toont dat dan netjes als "geen data".
 */

// ─── Constanten ──────────────────────────────────────────────────────────────

const DOMEIN_VPS_TEMPLATE    = 'vps%d.connect4it.hix.nl';
const DOMEIN_VPS_RANGE       = [1, 2, 3, 4, 5, 6];
const DOMEIN_VPS_PANEL_POORT = 2222;

const DOMEIN_DKIM_SELECTOREN = [
    'default', 'google', 'k1', 'k2', 'selector1', 'selector2',
    'dkim', 'mail', 's1', 's2', 'smtp', 'mandrill', 'mxvault',
    'everlytickey1', 'everlytickey2', 'key1', 'key2',
    'zoho', 'protonmail', 'protonmail2', 'protonmail3',
    'mailjet', 'mailo', 'pm', 'dkim1024', 'dkim2048',
];

const DOMEIN_BLACKLISTS = [
    'zen.spamhaus.org',
    'bl.spamcop.net',
    'b.barracudacentral.org',
    'dnsbl.sorbs.net',
    'psbl.surriel.com',
    'cbl.abuseat.org',
];

// TLD → WHOIS server (aanvullende lijst; rest wordt via IANA opgelost).
const DOMEIN_WHOIS_TLD = [
    'nl'    => 'whois.sidn.nl',
    'com'   => 'whois.verisign-grs.com',
    'net'   => 'whois.verisign-grs.com',
    'org'   => 'whois.pir.org',
    'info'  => 'whois.afilias.net',
    'biz'   => 'whois.nic.biz',
    'eu'    => 'whois.eu',
    'be'    => 'whois.dns.be',
    'de'    => 'whois.denic.de',
    'fr'    => 'whois.nic.fr',
    'uk'    => 'whois.nic.uk',
    'io'    => 'whois.nic.io',
    'app'   => 'whois.nic.google',
    'dev'   => 'whois.nic.google',
    'shop'  => 'whois.nic.shop',
    'cloud' => 'whois.nic.cloud',
    'xyz'   => 'whois.nic.xyz',
    'me'    => 'whois.nic.me',
    'co'    => 'whois.nic.co',
];

// ─── Basis: normaliseren en valideren ───────────────────────────────────────

function domein_normaliseer(string $input): ?string {
    $d = trim($input);
    if ($d === '') return null;
    $d = preg_replace('#^https?://#i', '', $d);
    $d = preg_replace('#/.*$#', '', $d);
    $d = preg_replace('#:\d+$#', '', $d);
    $d = strtolower($d);

    // Heel losse validatie — we willen punycode en hyphens accepteren.
    if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]{0,252}[a-z0-9])?$/', $d)) {
        return null;
    }
    if (strpos($d, '.') === false) return null;
    return $d;
}

function domein_tld(string $domein): string {
    $deel = explode('.', $domein);
    return end($deel);
}

// ─── DNS ─────────────────────────────────────────────────────────────────────

/**
 * Haalt alle interessante DNS records op.
 * Retourneert associative array met sleutels A, AAAA, MX, NS, TXT, CNAME, SOA, CAA, SRV.
 */
function domein_dns_alle(string $domein): array {
    $typen = [
        'A'     => DNS_A,
        'AAAA'  => DNS_AAAA,
        'MX'    => DNS_MX,
        'NS'    => DNS_NS,
        'TXT'   => DNS_TXT,
        'CNAME' => DNS_CNAME,
        'SOA'   => DNS_SOA,
        'CAA'   => defined('DNS_CAA') ? DNS_CAA : 0,
    ];
    $result = [];
    foreach ($typen as $naam => $flag) {
        if (!$flag) { $result[$naam] = []; continue; }
        $records = @dns_get_record($domein, $flag);
        $result[$naam] = is_array($records) ? $records : [];
    }
    // SRV voor veelvoorkomende services
    $srv_services = ['_autodiscover._tcp', '_sip._tcp', '_sip._tls', '_sipfederationtls._tcp'];
    $srv = [];
    foreach ($srv_services as $svc) {
        $r = @dns_get_record("$svc.$domein", DNS_SRV);
        if (is_array($r) && $r) $srv = array_merge($srv, $r);
    }
    $result['SRV'] = $srv;

    return $result;
}

function domein_reverse_dns(string $ip): ?string {
    $host = @gethostbyaddr($ip);
    return ($host && $host !== $ip) ? $host : null;
}

// ─── VPS detectie ────────────────────────────────────────────────────────────

/**
 * Haalt IPs op voor vps1..vps6.connect4it.hix.nl. Resultaat wordt gecached
 * per request.
 * Retourneert: ['1.2.3.4' => 'vps3.connect4it.hix.nl', ...]
 */
function domein_vps_ip_map(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (DOMEIN_VPS_RANGE as $n) {
        $host = sprintf(DOMEIN_VPS_TEMPLATE, $n);
        $records = @dns_get_record($host, DNS_A);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (!empty($r['ip'])) {
                    $cache[$r['ip']] = $host;
                }
            }
        }
    }
    return $cache;
}

function domein_vps_panel_url(string $host): string {
    return 'https://' . $host . ':' . DOMEIN_VPS_PANEL_POORT . '/';
}

/**
 * Kijkt of een gegeven IP-lijst overeenkomt met een van onze VPS-servers.
 *
 * Match op twee manieren, omdat een VPS vaak meerdere IPs heeft waarvan
 * alleen de hoofd-IP forward-DNS krijgt:
 *   1. Directe forward-match (vpsN.connect4it.hix.nl → IP == onze IP)
 *   2. Reverse-DNS-match (PTR van onze IP == vpsN.connect4it.hix.nl)
 *
 * Retourneert: [ ['vps_host' => ..., 'panel_url' => ..., 'ip' => ..., 'via' => ...], ... ]
 */
function domein_vps_matches_voor_ips(array $ips): array {
    $map = domein_vps_ip_map();
    $matches = [];
    $gezien  = [];

    foreach (array_unique($ips) as $ip) {
        $host = null;
        $via  = null;

        if (isset($map[$ip])) {
            $host = $map[$ip];
            $via  = 'A';
        } else {
            $ptr = domein_reverse_dns($ip);
            if ($ptr && preg_match('/^vps[1-9]\d?\.connect4it\.hix\.nl\.?$/i', $ptr)) {
                $host = rtrim(strtolower($ptr), '.');
                $via  = 'PTR';
            }
        }

        if (!$host) continue;
        $key = $host . '|' . $ip;
        if (isset($gezien[$key])) continue;
        $gezien[$key] = true;

        $matches[] = [
            'ip'        => $ip,
            'vps_host'  => $host,
            'panel_url' => domein_vps_panel_url($host),
            'via'       => $via,
        ];
    }
    return $matches;
}

/**
 * Maakt vpsN.connect4it.hix.nl in een stuk tekst klikbaar.
 * Werkt op htmlspecialchars-safe tekst (run h() eerst op de input).
 */
function domein_linkify_vps(string $html_safe_tekst): string {
    return preg_replace_callback(
        '/\b(vps[1-9]\d?\.connect4it\.hix\.nl)\b/i',
        function ($m) {
            $host = strtolower($m[1]);
            $url  = domein_vps_panel_url($host);
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener">'
                 . htmlspecialchars($host, ENT_QUOTES)
                 . ' <i class="ri-external-link-line" style="font-size:12px;"></i></a>';
        },
        $html_safe_tekst
    );
}

// ─── WHOIS ───────────────────────────────────────────────────────────────────

function domein_whois_server_voor(string $domein): ?string {
    $tld = domein_tld($domein);
    if (isset(DOMEIN_WHOIS_TLD[$tld])) return DOMEIN_WHOIS_TLD[$tld];

    // Fallback: vraag IANA welke server
    $iana = domein_whois_query('whois.iana.org', $tld);
    if ($iana && preg_match('/^whois:\s*(\S+)/mi', $iana, $m)) {
        return $m[1];
    }
    return null;
}

function domein_whois_query(string $server, string $query, int $timeout = 6): ?string {
    $fp = @fsockopen($server, 43, $errno, $errstr, $timeout);
    if (!$fp) return null;
    stream_set_timeout($fp, $timeout);
    // SIDN wil "domein ascii" om utf-8 output te krijgen
    $suffix = (stripos($server, 'sidn.nl') !== false) ? " -C US-ASCII\r\n" : "\r\n";
    fwrite($fp, $query . $suffix);
    $data = '';
    while (!feof($fp)) {
        $data .= fread($fp, 8192);
        $info = stream_get_meta_data($fp);
        if ($info['timed_out']) break;
    }
    fclose($fp);
    return $data ?: null;
}

function domein_whois(string $domein): ?string {
    $server = domein_whois_server_voor($domein);
    if (!$server) return null;
    $raw = domein_whois_query($server, $domein);
    if (!$raw) return null;

    // Sommige TLDs (com/net) verwijzen door naar een registrar-whois
    if (preg_match('/Registrar WHOIS Server:\s*(\S+)/i', $raw, $m)) {
        $tweede = domein_whois_query($m[1], $domein);
        if ($tweede && strlen($tweede) > 200) {
            $raw = $tweede;
        }
    }
    return $raw;
}

// ─── SSL certificaat ─────────────────────────────────────────────────────────

function domein_ssl_info(string $domein): ?array {
    $ctx = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'SNI_enabled'       => true,
            'peer_name'         => $domein,
        ],
    ]);
    $sock = @stream_socket_client(
        'ssl://' . $domein . ':443',
        $errno, $errstr, 5,
        STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$sock) return null;

    $params = stream_context_get_params($sock);
    fclose($sock);
    if (empty($params['options']['ssl']['peer_certificate'])) return null;

    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    if (!$cert) return null;

    $sans = [];
    if (!empty($cert['extensions']['subjectAltName'])) {
        foreach (explode(',', $cert['extensions']['subjectAltName']) as $part) {
            $sans[] = trim(preg_replace('/^DNS:/i', '', trim($part)));
        }
    }

    return [
        'subject_cn' => $cert['subject']['CN'] ?? '',
        'issuer_cn'  => $cert['issuer']['CN']  ?? '',
        'issuer_o'   => $cert['issuer']['O']   ?? '',
        'geldig_van' => $cert['validFrom_time_t'] ?? null,
        'geldig_tot' => $cert['validTo_time_t']  ?? null,
        'sans'       => array_values(array_filter($sans)),
        'serial'     => $cert['serialNumberHex'] ?? ($cert['serialNumber'] ?? ''),
    ];
}

// ─── SPF / DMARC / DKIM ──────────────────────────────────────────────────────

function domein_spf_uit_txt(array $txt_records): ?string {
    foreach ($txt_records as $r) {
        $txt = $r['txt'] ?? ($r['entries'][0] ?? '');
        if ($txt && stripos($txt, 'v=spf1') === 0) return $txt;
    }
    return null;
}

function domein_spf_analyse(?string $spf): array {
    if (!$spf) return ['aanwezig' => false];
    $mechanismen = preg_split('/\s+/', trim($spf));
    array_shift($mechanismen); // haal "v=spf1" eraf
    $eind = 'neutraal';
    foreach (array_reverse($mechanismen) as $m) {
        if (preg_match('/^[-~+?]all$/i', $m)) {
            $eind = match($m[0]) {
                '-' => 'hard fail (-all)',
                '~' => 'soft fail (~all)',
                '+' => 'toestaan (+all)',
                '?' => 'neutraal (?all)',
                default => 'neutraal',
            };
            break;
        }
    }
    return [
        'aanwezig'    => true,
        'raw'         => $spf,
        'mechanismen' => $mechanismen,
        'eind'        => $eind,
    ];
}

function domein_dmarc(string $domein): ?array {
    $records = @dns_get_record('_dmarc.' . $domein, DNS_TXT);
    if (!is_array($records)) return null;
    foreach ($records as $r) {
        $txt = $r['txt'] ?? ($r['entries'][0] ?? '');
        if (stripos($txt, 'v=DMARC1') === 0) {
            $pairs = [];
            foreach (explode(';', $txt) as $p) {
                $p = trim($p);
                if ($p === '' || strpos($p, '=') === false) continue;
                [$k, $v] = array_map('trim', explode('=', $p, 2));
                $pairs[$k] = $v;
            }
            return ['raw' => $txt, 'pairs' => $pairs];
        }
    }
    return null;
}

function domein_dkim_scan(string $domein): array {
    $gevonden = [];
    foreach (DOMEIN_DKIM_SELECTOREN as $sel) {
        $host = $sel . '._domainkey.' . $domein;
        $r = @dns_get_record($host, DNS_TXT);
        if (is_array($r) && $r) {
            foreach ($r as $rec) {
                $txt = $rec['txt'] ?? ($rec['entries'][0] ?? '');
                if ($txt) {
                    $gevonden[] = ['selector' => $sel, 'txt' => $txt];
                }
            }
        }
    }
    return $gevonden;
}

// ─── Blacklist check ─────────────────────────────────────────────────────────

function domein_blacklist_voor_ip(string $ip): array {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return [];
    $omgedraaid = implode('.', array_reverse(explode('.', $ip)));
    $result = [];
    foreach (DOMEIN_BLACKLISTS as $rbl) {
        $query = $omgedraaid . '.' . $rbl;
        $records = @dns_get_record($query, DNS_A);
        $gelist = is_array($records) && count($records) > 0;
        // Haal eventuele TXT reden op
        $reden = null;
        if ($gelist) {
            $txt = @dns_get_record($query, DNS_TXT);
            if (is_array($txt) && isset($txt[0]['txt'])) $reden = $txt[0]['txt'];
        }
        $result[] = ['rbl' => $rbl, 'gelist' => $gelist, 'reden' => $reden];
    }
    return $result;
}

// ─── Mail banner test ────────────────────────────────────────────────────────

function domein_mail_banner(string $host, int $timeout = 4): ?string {
    $fp = @fsockopen($host, 25, $errno, $errstr, $timeout);
    if (!$fp) return null;
    stream_set_timeout($fp, $timeout);
    $banner = fgets($fp, 1024);
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $banner ? trim($banner) : null;
}

// ─── TXT helper ──────────────────────────────────────────────────────────────

/** Pakt txt uit record (soms in 'txt', soms in 'entries'). */
function domein_txt_waarde(array $record): string {
    if (isset($record['txt'])) return $record['txt'];
    if (isset($record['entries'])) return implode('', $record['entries']);
    return '';
}
