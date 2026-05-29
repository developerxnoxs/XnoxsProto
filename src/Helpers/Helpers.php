<?php

namespace XnoxsProto\Helpers;

class Helpers
{
    public static function generateRandomBytes(int $length): string
    {
        return random_bytes($length);
    }

    public static function generateRandomLong(bool $signed = true): int
    {
        $bytes = random_bytes(8);
        $value = unpack('P', $bytes)[1];
        return $value;
    }

    public static function generateMessageId(): int
    {
        $time = microtime(true);
        $seconds = (int) $time;
        $microseconds = (int) (($time - $seconds) * 1000000);
        
        return ($seconds << 32) | ($microseconds << 2);
    }

    public static function packInt128LE($value): string
    {
        if (is_int($value)) {
            $low = $value & 0xFFFFFFFFFFFFFFFF;
            $high = ($value >> 64) & 0xFFFFFFFFFFFFFFFF;
            return pack('P2', $low, $high);
        }
        
        if (is_string($value) && strlen($value) === 16) {
            return $value;
        }
        
        throw new \InvalidArgumentException('Invalid 128-bit value');
    }

    public static function packInt256LE($value): string
    {
        if (is_string($value) && strlen($value) === 32) {
            return $value;
        }
        
        throw new \InvalidArgumentException('Invalid 256-bit value');
    }

    public static function unpackInt128LE(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new \InvalidArgumentException('Must be 16 bytes');
        }
        return $bytes;
    }

    public static function generateKeyDataFromNonce(string $serverNonce, string $newNonce): array
    {
        if (strlen($serverNonce) !== 16) {
            throw new \InvalidArgumentException('server_nonce must be 16 bytes');
        }
        if (strlen($newNonce) !== 32) {
            throw new \InvalidArgumentException('new_nonce must be 32 bytes');
        }
        
        $hash1 = sha1($newNonce . $serverNonce, true);
        $hash2 = sha1($serverNonce . $newNonce, true);
        $hash3 = sha1($newNonce . $newNonce, true);
        
        $key = $hash1 . substr($hash2, 0, 12);
        $iv = substr($hash2, 12, 8) . $hash3 . substr($newNonce, 0, 4);
        
        return [$key, $iv];
    }

    public static function factorize(\GMP|int $pq): array
    {
        if (!($pq instanceof \GMP)) {
            $pq = gmp_init($pq);
        }

        if (gmp_cmp($pq, 1) <= 0) {
            throw new \InvalidArgumentException('Number must be greater than 1');
        }

        if (gmp_prob_prime($pq)) {
            return [gmp_intval($pq), 1];
        }

        $two = gmp_init(2);
        if (gmp_cmp(gmp_mod($pq, $two), 0) === 0) {
            return [2, gmp_intval(gmp_div($pq, $two))];
        }

        $pqInt = gmp_intval($pq);
        $y = gmp_init(rand(1, min($pqInt - 1, PHP_INT_MAX)));
        $c = gmp_init(rand(1, min($pqInt - 1, PHP_INT_MAX)));
        $m = gmp_init(rand(1, min($pqInt - 1, PHP_INT_MAX)));
        
        $g = gmp_init(1);
        $r = gmp_init(1);
        $q = gmp_init(1);
        $ys = gmp_init(0);
        
        while (gmp_cmp($g, 1) === 0) {
            $x = $y;
            
            for ($i = 0; $i < gmp_intval($r); $i++) {
                $y = gmp_mod(gmp_add(gmp_pow($y, 2), $c), $pq);
            }
            
            $k = gmp_init(0);
            while (gmp_cmp($k, $r) < 0 && gmp_cmp($g, 1) === 0) {
                $ys = $y;
                
                $iterations = min(gmp_intval($m), gmp_intval(gmp_sub($r, $k)));
                for ($i = 0; $i < $iterations; $i++) {
                    $y = gmp_mod(gmp_add(gmp_pow($y, 2), $c), $pq);
                    $q = gmp_mod(gmp_mul($q, gmp_abs(gmp_sub($x, $y))), $pq);
                }
                
                $g = gmp_gcd($q, $pq);
                $k = gmp_add($k, $m);
            }
            
            $r = gmp_mul($r, $two);
        }
        
        if (gmp_cmp($g, $pq) === 0) {
            while (true) {
                $ys = gmp_mod(gmp_add(gmp_pow($ys, 2), $c), $pq);
                $g = gmp_gcd(gmp_abs(gmp_sub($x, $ys)), $pq);
                
                if (gmp_cmp($g, 1) > 0) {
                    break;
                }
            }
        }
        
        $p = gmp_intval($g);
        $q_val = gmp_intval(gmp_div($pq, $g));
        
        return $p < $q_val ? [$p, $q_val] : [$q_val, $p];
    }

    public static function gcd(int $a, int $b): int
    {
        while ($b) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }
        return $a;
    }

    public static function getByteArray(\GMP $integer): string
    {
        $hex = gmp_strval($integer, 16);
        
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        
        return hex2bin($hex);
    }

    public static function getInt(string $byteArray, bool $signed = true): \GMP
    {
        return gmp_init(bin2hex($byteArray), 16);
    }
}
