<?php

namespace XnoxsProto\Crypto;

/**
 * Telegram MTProto SRP-2048 implementation for Two-Factor Authentication.
 *
 * Reference: https://core.telegram.org/api/srp
 */
class SRP
{
    /**
     * Compute SRP proof for auth.checkPassword.
     *
     * @param string $password    User's plaintext password
     * @param string $salt1       salt1 from account.Password
     * @param string $salt2       salt2 from account.Password
     * @param int    $g           g from account.Password
     * @param string $p           p (2048-bit prime) from account.Password
     * @param string $srpB        srp_B from account.Password
     * @param int    $srpId       srp_id from account.Password
     *
     * @return array ['srp_id' => int, 'A' => string, 'M1' => string]
     */
    public static function computeCheck(
        string $password,
        string $salt1,
        string $salt2,
        int    $g,
        string $p,
        string $srpB,
        int    $srpId
    ): array {
        $pLen = 256;

        // H(data) = SHA-256
        $H = fn(string $data): string => hash('sha256', $data, true);

        // SH(data) = SHA-256, result padded to 256 bytes
        $SH = fn(string $data): string => str_pad($H($data), $pLen, "\x00", STR_PAD_LEFT);

        // xH(a, b) = SHA-256(a || b) padded to 256 bytes  
        $xH = fn(string $a, string $b): string => str_pad($H($a . $b), $pLen, "\x00", STR_PAD_LEFT);

        // Convert bytes to GMP
        $fromBytes = fn(string $s): \GMP => gmp_import($s, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        // Convert GMP to padded bytes
        $toBytes   = function (\GMP $n) use ($pLen): string {
            $raw = gmp_export($n, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
            return str_pad($raw, $pLen, "\x00", STR_PAD_LEFT);
        };

        $gmpP = $fromBytes($p);
        $gmpG = gmp_init($g);

        // Step 1: Compute x using PasswordKdfAlgoSHA256SHA256PBKDF2HMACSHA512iter100000SHA256ModPow
        //
        // Per Telegram spec (https://core.telegram.org/api/srp) and verified against Telethon:
        //
        //   SH(data, salt) := SHA256(salt + data + salt)
        //
        //   PH1 = SH(SH(password, salt1), salt2)
        //       = SHA256(salt2 + SHA256(salt1 + password + salt1) + salt2)
        //
        //   PH2 = SH(PBKDF2(sha512, PH1, salt1, 100000), salt2)
        //       = SHA256(salt2 + PBKDF2_HMAC_SHA512(PH1, salt1, 100000) + salt2)
        //
        //   x = PH2
        //
        // Common mistakes:
        //   - Skipping the intermediate SHA256(salt2 + inner + salt2) step (PH1)
        //   - Using salt2 (instead of salt1) as the PBKDF2 salt
        $inner  = hash('sha256', $salt1 . $password . $salt1, true); // SH(password, salt1)
        $ph1    = hash('sha256', $salt2 . $inner . $salt2,    true); // SH(inner, salt2) = PH1
        $pbkdf2 = hash_pbkdf2('sha512', $ph1, $salt1, 100000, 0, true); // PBKDF2(PH1, salt1, 100000)
        $x      = $H($salt2 . $pbkdf2 . $salt2);                    // SH(pbkdf2, salt2) = PH2 = x
        $gmpX   = $fromBytes($x);

        // Step 2: Random a (256 bytes)
        $aBytes = random_bytes($pLen);
        $gmpA   = $fromBytes($aBytes);

        // Step 3: g_a = g^a mod p
        $gmpGa    = gmp_powm($gmpG, $gmpA, $gmpP);
        $gaBytes  = $toBytes($gmpGa);

        // Step 4: u = H(g_a || B)
        $bBytes = str_pad($srpB, $pLen, "\x00", STR_PAD_LEFT);
        $u      = $H($gaBytes . $bBytes);
        $gmpU   = $fromBytes($u);

        // Step 5: k = H(p || g_padded)
        // g must be encoded as its MINIMAL big-endian byte string padded to 256 bytes.
        // pack('N', g) gives 4 bytes which is WRONG — use gmp_export for 1 byte (g=2/3/5).
        $gRaw    = gmp_export($gmpG, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $gPadded = str_pad($gRaw, $pLen, "\x00", STR_PAD_LEFT);
        $k       = $H($p . $gPadded);
        $gmpK    = $fromBytes($k);

        // Step 6: B value as GMP
        $gmpB = $fromBytes($bBytes);

        // Step 7: g_x = g^x mod p
        $gmpGx = gmp_powm($gmpG, $gmpX, $gmpP);

        // Step 8: t = (B - k * g_x) mod p
        // Must handle negative values: ((B - k*g_x) % p + p) % p
        $kGx = gmp_mod(gmp_mul($gmpK, $gmpGx), $gmpP);
        $t   = gmp_mod(gmp_sub($gmpB, $kGx), $gmpP);
        if (gmp_sign($t) < 0) {
            $t = gmp_add($t, $gmpP);
        }

        // Step 9: s_a = t^(a + u*x) mod p
        $ux      = gmp_add(gmp_mul($gmpU, $gmpX), $gmpA);
        $gmpSa   = gmp_powm($t, $ux, $gmpP);
        $saBytes = $toBytes($gmpSa);

        // Step 10: k_a = H(s_a)
        $ka = $H($saBytes);

        // Step 11: H(p) XOR H(g_padded)
        $hP = $H($p);
        $hG = $H($gPadded);
        $xorPG = '';
        for ($i = 0; $i < 32; $i++) {
            $xorPG .= chr(ord($hP[$i]) ^ ord($hG[$i]));
        }

        // Step 12: M1 = H(H(p) XOR H(g) || H(salt1) || H(salt2) || g_a || B || k_a)
        $M1 = $H(
            $xorPG .
            $H($salt1) .
            $H($salt2) .
            $gaBytes .
            $bBytes .
            $ka
        );

        return [
            'srp_id' => $srpId,
            'A'      => $gaBytes,
            'M1'     => $M1,
        ];
    }
}
