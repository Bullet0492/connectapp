<?php
/**
 * TOTP 2FA — RFC 6238 implementatie zonder externe libraries.
 * Werkt met Google Authenticator, Microsoft Authenticator, Authy, etc.
 */

define('TOTP_BASE32_ALFABET', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567');

/**
 * Genereert een nieuw willekeurig 20-byte base32-gecodeerd TOTP-geheim.
 */
function totp_genereer_secret(): string {
    $bytes = random_bytes(20); // 160 bits entropy
    $bits  = '';
    for ($i = 0; $i < strlen($bytes); $i++) {
        $bits .= sprintf('%08b', ord($bytes[$i]));
    }
    $secret = '';
    for ($i = 0; $i + 5 <= strlen($bits); $i += 5) {
        $secret .= TOTP_BASE32_ALFABET[bindec(substr($bits, $i, 5))];
    }
    return $secret;
}

/**
 * Decodeert een base32-geheim naar binaire bytes.
 */
function totp_base32_decode(string $secret): string {
    $secret = strtoupper(preg_replace('/\s/', '', $secret));
    $bits   = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $pos = strpos(TOTP_BASE32_ALFABET, $secret[$i]);
        if ($pos === false) continue;
        $bits .= sprintf('%05b', $pos);
    }
    $result = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $result .= chr(bindec(substr($bits, $i, 8)));
    }
    return $result;
}

/**
 * Berekent de 6-cijferige TOTP-code voor het gegeven tijdvenster.
 * $tijdstap = 0 (huidig), -1 (vorig), 1 (volgend)
 */
function totp_code(string $secret, int $tijdstap = 0): string {
    $key     = totp_base32_decode($secret);
    $counter = (int) floor(time() / 30) + $tijdstap;

    // 8-byte big-endian teller (hoge 4 bytes = 0 voor huidige epoch)
    $teller = pack('N', 0) . pack('N', $counter);

    $hmac   = hash_hmac('sha1', $teller, $key, true);
    $offset = ord($hmac[19]) & 0x0F;
    $code   = (
        ((ord($hmac[$offset])     & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8)  |
        ((ord($hmac[$offset + 3]) & 0xFF))
    ) % 1000000;

    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verifieert een ingevoerde 6-cijferige code.
 * Staat ±1 tijdvenster toe (30 seconden klokafwijking).
 */
function totp_verifieer(string $secret, string $invoer): bool {
    $code = preg_replace('/\D/', '', $invoer);
    if (strlen($code) !== 6) return false;
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code($secret, $i), $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Genereert de otpauth://-URL voor de QR-code in een authenticator-app.
 */
function totp_qr_url(string $secret, string $email): string {
    $issuer = 'Connect App';
    $label  = rawurlencode($issuer . ':' . $email);
    return 'otpauth://totp/' . $label . '?' . http_build_query([
        'secret'    => $secret,
        'issuer'    => $issuer,
        'algorithm' => 'SHA1',
        'digits'    => 6,
        'period'    => 30,
    ]);
}

/**
 * Versleutelt een TOTP-geheim met AES-256-CBC (zelfde methode als wachtwoordkluis).
 */
function totp_encrypt_secret(string $plaintext): string {
    $key = hex2bin(ENCRYPT_KEY);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

/**
 * Ontsleutelt een versleuteld TOTP-geheim.
 */
function totp_decrypt_secret(string $ciphertext): string {
    $key  = hex2bin(ENCRYPT_KEY);
    $data = base64_decode($ciphertext);
    $iv   = substr($data, 0, 16);
    $enc  = substr($data, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv) ?: '';
}
