<?php
/**
 * KanbanDoo - Autenticação de Dois Fatores (TOTP - RFC 6238)
 *
 * Compatível com Google Authenticator, Microsoft Authenticator, Authy, 1Password, etc.
 */

class TOTP
{
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Gera um segredo aleatório em Base32 (padrão 16 caracteres = 80 bits).
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $max = strlen(self::$base32Chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, $max)];
        }
        return $secret;
    }

    /**
     * Decodifica uma string Base32 para bytes binários.
     */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
        if ($b32 === '') {
            return '';
        }

        $binary = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $val = strpos(self::$base32Chars, $b32[$i]);
            $binary .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        $chunks = str_split($binary, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }

    /**
     * Calcula o código TOTP de 6 dígitos para o segredo fornecido.
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        if ($secretKey === '') {
            return '000000';
        }

        // Empacota o contador de tempo em binário big-endian de 64 bits (8 bytes)
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        $otp = $binary % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Valida o código informado pelo usuário (permite tolerância de drift de +/- 30 segundos).
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTimeSlice = (int)floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera a URL otpauth:// para escaneamento via QR Code.
     */
    public static function getOtpAuthUrl(string $account, string $secret, string $issuer = 'KanbanDoo'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . "&digits=6&period=30";
    }

    /**
     * Gera URL de QR Code seguro para exibir ao usuário.
     */
    public static function getQrCodeUrl(string $otpauthUrl): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=4&data=' . rawurlencode($otpauthUrl);
    }
}
