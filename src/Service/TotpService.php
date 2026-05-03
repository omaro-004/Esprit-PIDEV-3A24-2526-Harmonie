<?php

namespace App\Service;

/**
 * Service TOTP natif — aucune dépendance externe.
 * Implémente RFC 6238 (TOTP) + RFC 4226 (HOTP).
 * Compatible avec Google Authenticator, Authy, Microsoft Authenticator.
 */
class TotpService
{
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ISSUER = 'Harmony';

    // ── Génération du secret ──────────────────────────────────────────────────

    /**
     * Génère un secret aléatoire encodé en Base32 (16 caractères = 80 bits)
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(10); // 80 bits
        return $this->base32Encode($bytes);
    }

    // ── URI otpauth:// pour le QR Code ───────────────────────────────────────

    public function getProvisioningUri(string $secret, string $email): string
    {
        $label  = rawurlencode(self::ISSUER . ':' . $email);
        $issuer = rawurlencode(self::ISSUER);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label, $secret, $issuer, self::DIGITS, self::PERIOD
        );
    }

    // ── Validation du code TOTP ───────────────────────────────────────────────

    /**
     * Vérifie un code OTP. Tolère ±1 période (30s) pour les décalages d'horloge.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $secretDecoded = $this->base32Decode($secret);
        $timestamp     = (int) floor(time() / self::PERIOD);

        foreach ([-1, 0, 1] as $offset) {
            $expected = $this->hotp($secretDecoded, $timestamp + $offset);
            $expected = str_pad((string) $expected, self::DIGITS, '0', STR_PAD_LEFT);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    // ── Secondes restantes avant expiration ───────────────────────────────────

    public function getSecondsRemaining(): int
    {
        return self::PERIOD - (time() % self::PERIOD);
    }

    // ── Algorithme HOTP (RFC 4226) ────────────────────────────────────────────

    private function hotp(string $keyBinary, int $counter): int
    {
        // Counter → big-endian 64 bits
        $counterBin = pack('N*', 0) . pack('N*', $counter);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBin, $keyBinary, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $code   = (
            (ord($hash[$offset])     & 0x7F) << 24 |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8  |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return $code % (10 ** self::DIGITS);
    }

    // ── Base32 (RFC 4648) ─────────────────────────────────────────────────────

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private function base32Encode(string $data): string
    {
        $output  = '';
        $n       = 0;
        $buffer  = 0;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $n     += 8;
            while ($n >= 5) {
                $n      -= 5;
                $output .= self::BASE32_CHARS[($buffer >> $n) & 0x1F];
            }
        }
        if ($n > 0) {
            $output .= self::BASE32_CHARS[($buffer << (5 - $n)) & 0x1F];
        }

        return $output;
    }

    private function base32Decode(string $data): string
    {
        $data    = strtoupper(str_replace('=', '', trim($data)));
        $charMap = array_flip(str_split(self::BASE32_CHARS));
        $output  = '';
        $buffer  = 0;
        $n       = 0;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            if (!isset($charMap[$data[$i]])) {
                continue;
            }
            $buffer = ($buffer << 5) | $charMap[$data[$i]];
            $n     += 5;
            if ($n >= 8) {
                $n      -= 8;
                $output .= chr(($buffer >> $n) & 0xFF);
            }
        }

        return $output;
    }
}
