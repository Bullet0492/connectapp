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
    fwrite($fp, $query . "\r\n");
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

// ─── Aanbevelingen / analyse ─────────────────────────────────────────────────

/**
 * Analyseert de verzamelde data en geeft concrete aanbevelingen terug.
 * Elke bevinding: ['status' => 'ok'|'warn'|'fout', 'titel' => ..., 'tekst' => ..., 'hoe' => ...]
 *   - status: severity
 *   - titel : korte samenvatting (wat is er aan de hand)
 *   - tekst : toelichting
 *   - hoe   : concrete actie om het te verbeteren (optioneel)
 */
function domein_aanbevelingen(
    string $domein,
    array $dns,
    ?array $ssl,
    array $spf,
    ?array $dmarc,
    array $dkim,
    array $blacklist,
    array $mail_banners
): array {
    $b = [];

    // ─── SPF ────────────────────────────────────────
    if (!$spf['aanwezig']) {
        $b[] = ['fout', 'Geen SPF record',
            'Zonder SPF kan iedereen zogenaamd namens dit domein mailen. Ontvangende mailservers weten niet welke IPs geautoriseerd zijn.',
            'Voeg een TXT-record toe op ' . $domein . ' met: v=spf1 include:_spf.jouwprovider.nl -all'];
    } else {
        $raw = $spf['raw'];
        if (preg_match('/(^|\s)\+all(\s|$)/i', $raw)) {
            $b[] = ['fout', 'SPF staat +all toe', 'Dit laat iedereen namens het domein mailen — dat maakt SPF nutteloos.',
                'Vervang +all door -all (strikt) of ~all (soft fail).'];
        } elseif (preg_match('/(^|\s)~all(\s|$)/i', $raw)) {
            $b[] = ['warn', 'SPF eindigt op ~all (soft fail)',
                'Mail die niet matcht wordt door ontvangers doorgaans alsnog geaccepteerd (markering als spam).',
                'Overweeg -all zodra je zeker weet dat alle legitieme verzenders in de SPF staan.'];
        } elseif (preg_match('/(^|\s)-all(\s|$)/i', $raw)) {
            $b[] = ['ok', 'SPF strikt ingesteld (-all)', '', ''];
        } else {
            $b[] = ['warn', 'SPF heeft geen eind-mechanisme',
                'Zonder ±all / -all weet de ontvanger niet wat te doen met niet-matchende mail.',
                'Voeg -all (of tijdelijk ~all) toe aan het einde van de SPF.'];
        }
        $lookups = preg_match_all('/\b(include|a|mx|exists|redirect|ptr)[:=]/i', $raw);
        if ($lookups > 10) {
            $b[] = ['fout', 'SPF overschrijdt limiet van 10 DNS-lookups',
                'RFC 7208 staat maximaal 10 include/a/mx/exists/redirect/ptr toe. Ontvangers mogen de SPF dan negeren.',
                'Consolideer includes of gebruik een flattening-tool.'];
        }
    }

    // ─── DMARC ──────────────────────────────────────
    if (!$dmarc) {
        $b[] = ['fout', 'Geen DMARC record',
            'Zonder DMARC is er geen afwijs-beleid voor mail die SPF/DKIM faalt, en geen rapportage over misbruik.',
            'Voeg TXT-record toe op _dmarc.' . $domein . ': v=DMARC1; p=quarantine; rua=mailto:postmaster@' . $domein . '; adkim=s; aspf=s'];
    } else {
        $p = strtolower($dmarc['pairs']['p'] ?? 'none');
        if ($p === 'none') {
            $b[] = ['warn', 'DMARC policy is p=none (monitoring-only)',
                'Mail die faalt wordt niet tegengehouden — alleen gerapporteerd.',
                'Zodra je rapporten (rua) hebt geanalyseerd: zet naar p=quarantine, en daarna p=reject.'];
        } elseif ($p === 'quarantine') {
            $b[] = ['ok', 'DMARC p=quarantine', 'Sterke instelling: falende mail gaat naar spam.', 'Overweeg upgrade naar p=reject voor maximale bescherming.'];
        } elseif ($p === 'reject') {
            $b[] = ['ok', 'DMARC p=reject', 'Strengste DMARC-instelling.', ''];
        }
        if (empty($dmarc['pairs']['rua'])) {
            $b[] = ['warn', 'DMARC zonder rua (rapportage-adres)',
                'Je krijgt geen dagelijkse aggregate rapporten van ontvangers over wie er namens jou mailt.',
                'Voeg rua=mailto:postmaster@' . $domein . ' toe aan het DMARC record.'];
        }
        $pct = (int)($dmarc['pairs']['pct'] ?? 100);
        if ($pct < 100 && $p !== 'none') {
            $b[] = ['warn', 'DMARC pct=' . $pct . ' — slechts deel van mail wordt gefilterd',
                'Alleen ' . $pct . '% van falende mail valt onder de policy.',
                'Zet naar pct=100 zodra de rapporten schoon zijn.'];
        }
    }

    // ─── DKIM ───────────────────────────────────────
    if (!$dkim) {
        $b[] = ['warn', 'Geen DKIM selector gevonden',
            'We probeerden ' . count(DOMEIN_DKIM_SELECTOREN) . ' veelvoorkomende selector-namen. DKIM kan alsnog ingesteld zijn met een custom selector.',
            'Vraag de mailprovider naar de gebruikte selector en verifieer via <selector>._domainkey.' . $domein . '.'];
    } else {
        $b[] = ['ok', count($dkim) . ' DKIM selector(s) gevonden', '', ''];
    }

    // ─── MX ─────────────────────────────────────────
    $mx = $dns['MX'] ?? [];
    if (!$mx) {
        $b[] = ['warn', 'Geen MX records',
            'Zonder MX kan dit domein geen mail ontvangen. Als het domein puur voor websites is, is dit prima.',
            'Voeg MX records toe als e-mail gewenst is.'];
    } elseif (count($mx) === 1) {
        $b[] = ['warn', 'Slechts 1 MX record',
            'Eén MX host betekent geen redundantie: bij uitval gaat mail kwijt of bounced.',
            'Voeg een tweede MX met hogere priority toe (backup MX).'];
    } else {
        $b[] = ['ok', count($mx) . ' MX records (redundant)', '', ''];
    }

    // MX PTR mismatch
    foreach ($mail_banners as $mb) {
        $host = strtolower(rtrim($mb['host'] ?? '', '.'));
        if (!$host) continue;
        $a = @dns_get_record($host, DNS_A);
        if (!$a) continue;
        foreach ($a as $rec) {
            $ip = $rec['ip'] ?? null;
            if (!$ip) continue;
            $ptr = domein_reverse_dns($ip);
            if ($ptr && strtolower(rtrim($ptr, '.')) !== $host) {
                $b[] = ['warn', 'MX PTR wijkt af van hostname',
                    $host . ' ' . $ip . ' → PTR wijst naar ' . $ptr . '. Sommige ontvangers (zoals Microsoft) eisen matchend PTR.',
                    'Laat de hosting-provider de reverse-DNS gelijk zetten aan ' . $host . '.'];
                break 2;
            }
        }
    }

    // ─── SSL ────────────────────────────────────────
    if (!$ssl) {
        $b[] = ['warn', 'Geen SSL op poort 443',
            'Dit domein is niet bereikbaar via HTTPS.',
            'Installeer een (Let\'s Encrypt) certificaat via DirectAdmin of de hosting-provider.'];
    } else {
        if ($ssl['geldig_tot']) {
            $dagen = (int) floor(($ssl['geldig_tot'] - time()) / 86400);
            if ($dagen < 0) {
                $b[] = ['fout', 'SSL certificaat verlopen (' . abs($dagen) . ' dagen geleden)',
                    'Bezoekers krijgen waarschuwingen in de browser.',
                    'Vernieuw direct via DirectAdmin → SSL Certificates → Let\'s Encrypt.'];
            } elseif ($dagen < 14) {
                $b[] = ['fout', 'SSL verloopt binnen ' . $dagen . ' dagen',
                    'Auto-renew heeft niet gewerkt.',
                    'Controleer in DirectAdmin of de Let\'s Encrypt cron nog draait en hernieuw handmatig.'];
            } elseif ($dagen < 30) {
                $b[] = ['warn', 'SSL verloopt binnen ' . $dagen . ' dagen', '',
                    'Controleer of auto-renewal ingeschakeld staat.'];
            } else {
                $b[] = ['ok', 'SSL geldig (' . $dagen . ' dagen)', '', ''];
            }
        }
        // Subject/SAN match?
        $cn = strtolower($ssl['subject_cn'] ?? '');
        $sans = array_map('strtolower', $ssl['sans']);
        $match = ($cn === $domein) || in_array($domein, $sans, true) || in_array('*.' . implode('.', array_slice(explode('.', $domein), 1)), $sans, true);
        if (!$match && $sans) {
            $b[] = ['warn', 'SSL certificaat dekt dit domein niet',
                'CN/SAN matcht niet met ' . $domein . '. Browsers tonen een foutmelding.',
                'Vernieuw het certificaat met correcte SAN (bijv. ' . $domein . ' en www.' . $domein . ').'];
        }
    }

    // ─── CAA ────────────────────────────────────────
    if (empty($dns['CAA'])) {
        $b[] = ['warn', 'Geen CAA records',
            'CAA beperkt welke certificaat-autoriteiten certificaten voor dit domein mogen uitgeven. Extra beveiliging tegen mis-issuance.',
            'Voeg TXT/CAA-record toe: 0 issue "letsencrypt.org" (en eventueel 0 issuewild "letsencrypt.org").'];
    } else {
        $b[] = ['ok', 'CAA records aanwezig', '', ''];
    }

    // ─── DNSSEC ─────────────────────────────────────
    // dns_get_record geeft geen DS/RRSIG. We kunnen alleen via whois zien of DNSSEC actief is — dat wordt elders getoond.

    // ─── NS redundantie ─────────────────────────────
    $ns = $dns['NS'] ?? [];
    if (count($ns) < 2) {
        $b[] = ['warn', 'Minder dan 2 nameservers',
            'Best practice (en vereist bij .nl) is minimaal 2 nameservers voor redundantie.',
            'Voeg een tweede NS toe bij de registrar.'];
    } else {
        $b[] = ['ok', count($ns) . ' nameservers (redundant)', '', ''];
    }

    // ─── Blacklist ──────────────────────────────────
    $gelist = [];
    foreach ($blacklist as $ip => $checks) {
        foreach ($checks as $c) {
            if ($c['gelist']) $gelist[] = $ip . ' staat op ' . $c['rbl'] . ($c['reden'] ? ' (' . $c['reden'] . ')' : '');
        }
    }
    if ($gelist) {
        $b[] = ['fout', count($gelist) . ' blacklist-vermelding(en)',
            implode("\n", $gelist),
            'Controleer op uitgaande spam/compromitteringen en vraag delisting aan via de website van de betreffende RBL.'];
    } elseif ($blacklist) {
        $b[] = ['ok', 'Niet op onderzochte blacklists', '', ''];
    }

    return $b;
}

// ─── TXT helper ──────────────────────────────────────────────────────────────

/** Pakt txt uit record (soms in 'txt', soms in 'entries'). */
function domein_txt_waarde(array $record): string {
    if (isset($record['txt'])) return $record['txt'];
    if (isset($record['entries'])) return implode('', $record['entries']);
    return '';
}
