<?php

namespace XnoxsProto\Helpers;

/**
 * Pure-PHP terminal QR code generator (ISO 18004 subset).
 *
 * Supports:
 *   - Byte encoding mode
 *   - Error Correction Level M
 *   - Versions 1–7 (covers URLs up to ~122 bytes)
 *
 * No external dependencies required.
 */
class QRCodeHelper
{
    // =========================================================================
    // GF(256) tables — initialised lazily
    // =========================================================================

    private static array $LOG = [];
    private static array $EXP = [];

    // =========================================================================
    // EC parameters for Level M, Versions 1–7 (single-group blocks)
    // Format: [ec_codewords_per_block, num_blocks, data_codewords_per_block]
    // =========================================================================

    private static array $EC_M = [
        1 => [10, 1, 16],
        2 => [16, 1, 28],
        3 => [13, 2, 22],
        4 => [18, 2, 32],
        5 => [24, 2, 43],
        6 => [16, 4, 27],
        7 => [18, 4, 31],
    ];

    // Byte-mode capacity (characters) for Level M, Versions 1–7
    private static array $CAP_M = [
        1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84, 6 => 106, 7 => 122,
    ];

    // Alignment pattern row/col centres per version (v1 has none)
    private static array $ALIGN = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Build the tg://login?token=<base64url> URL from raw token bytes.
     */
    public static function buildTgUrl(string $tokenBytes): string
    {
        $b64 = rtrim(strtr(base64_encode($tokenBytes), '+/', '-_'), '=');
        return 'tg://login?token=' . $b64;
    }

    /**
     * Render the QR code as compact unicode-block art for terminal display.
     * Uses ▀ ▄ █ and space — each printed row represents 2 QR module rows.
     *
     * Returns an empty string if the text is too long to encode (>122 bytes).
     */
    public static function terminalQR(string $text): string
    {
        self::initGF();
        $version = self::selectVersion($text);
        if ($version === 0) {
            return '';
        }
        $matrix = self::buildQR($text, $version);
        return self::renderHalfBlock($matrix);
    }

    /**
     * Render the QR code as plain ASCII art (# = dark, space = light).
     * Useful for environments where unicode block chars are unavailable.
     */
    public static function asciiQR(string $text): string
    {
        self::initGF();
        $version = self::selectVersion($text);
        if ($version === 0) {
            return '';
        }
        $matrix = self::buildQR($text, $version);
        return self::renderAscii($matrix);
    }

    // =========================================================================
    // GF(256) arithmetic
    // =========================================================================

    private static function initGF(): void
    {
        if (!empty(self::$LOG)) {
            return;
        }
        $prim = 0x11d; // primitive polynomial x^8+x^4+x^3+x^2+1
        $x    = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$EXP[$i]  = $x;
            self::$LOG[$x]  = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= $prim;
            }
        }
        self::$EXP[255] = self::$EXP[0]; // wrap-around for mod 255
    }

    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return self::$EXP[(self::$LOG[$x] + self::$LOG[$y]) % 255];
    }

    // =========================================================================
    // Reed-Solomon encoder
    // =========================================================================

    /**
     * Build RS generator polynomial of given degree.
     * g(x) = (x + α^0)(x + α^1)…(x + α^(deg-1))
     */
    private static function rsGen(int $deg): array
    {
        $g = [1];
        for ($i = 0; $i < $deg; $i++) {
            $a  = self::$EXP[$i];
            $ng = array_fill(0, count($g) + 1, 0);
            foreach ($g as $p => $gv) {
                $ng[$p]     ^= $gv;
                $ng[$p + 1] ^= self::gfMul($gv, $a);
            }
            $g = $ng;
        }
        return $g;
    }

    /**
     * Compute EC codewords = remainder of (msg · x^ec_count) ÷ gen.
     */
    private static function rsEncode(array $msg, int $ecCount): array
    {
        $gen = self::rsGen($ecCount);
        $buf = array_merge($msg, array_fill(0, $ecCount, 0));
        $n   = count($msg);
        for ($i = 0; $i < $n; $i++) {
            $c = $buf[$i];
            if ($c !== 0) {
                for ($j = 1; $j <= $ecCount; $j++) {
                    $buf[$i + $j] ^= self::gfMul($gen[$j], $c);
                }
            }
        }
        return array_slice($buf, $n);
    }

    // =========================================================================
    // Version selection
    // =========================================================================

    private static function selectVersion(string $data): int
    {
        $len = strlen($data);
        foreach (self::$CAP_M as $v => $cap) {
            if ($len <= $cap) {
                return $v;
            }
        }
        return 0; // data too long
    }

    // =========================================================================
    // Data encoding → codeword byte array
    // =========================================================================

    private static function encodeData(string $data, int $version): array
    {
        [$ecPerBlk, $numBlocks, $dwPerBlk] = self::$EC_M[$version];
        $totalData = $numBlocks * $dwPerBlk;

        // Bit stream assembly
        $bits  = '';
        $bits .= '0100';                                       // mode indicator: byte
        $bits .= sprintf('%08b', strlen($data));               // char count (8 bits, v1-9)
        foreach (str_split($data) as $ch) {
            $bits .= sprintf('%08b', ord($ch));
        }

        // Terminator (up to 4 zeros)
        $remaining = $totalData * 8 - strlen($bits);
        $bits .= str_repeat('0', min(4, max(0, $remaining)));

        // Pad to byte boundary
        $mod8  = strlen($bits) % 8;
        if ($mod8 !== 0) {
            $bits .= str_repeat('0', 8 - $mod8);
        }

        // Pad codewords (alternating 0xEC / 0x11)
        $padSeq = ['11101100', '00010001'];
        $pi     = 0;
        while (strlen($bits) < $totalData * 8) {
            $bits .= $padSeq[$pi++ % 2];
        }

        // Convert bit string to byte array
        $cws = [];
        for ($i = 0; $i < $totalData; $i++) {
            $cws[] = bindec(substr($bits, $i * 8, 8));
        }
        return $cws;
    }

    private static function buildFinalMessage(string $data, int $version): array
    {
        [$ecPerBlk, $numBlocks, $dwPerBlk] = self::$EC_M[$version];
        $cws = self::encodeData($data, $version);

        // Split into blocks and compute EC codewords
        $blocks   = [];
        $ecBlocks = [];
        for ($b = 0; $b < $numBlocks; $b++) {
            $blockData  = array_slice($cws, $b * $dwPerBlk, $dwPerBlk);
            $blocks[]   = $blockData;
            $ecBlocks[] = self::rsEncode($blockData, $ecPerBlk);
        }

        // Interleave data codewords column-by-column
        $result = [];
        for ($i = 0; $i < $dwPerBlk; $i++) {
            foreach ($blocks as $blk) {
                $result[] = $blk[$i];
            }
        }
        // Interleave EC codewords
        for ($i = 0; $i < $ecPerBlk; $i++) {
            foreach ($ecBlocks as $blk) {
                $result[] = $blk[$i];
            }
        }
        return $result;
    }

    // =========================================================================
    // Matrix construction
    // =========================================================================

    private static function buildQR(string $data, int $version): array
    {
        $size   = 17 + 4 * $version;
        // -1 = unset, 0 = light, 1 = dark
        $matrix = array_fill(0, $size, array_fill(0, $size, -1));
        $func   = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns (top-left, top-right, bottom-left)
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
            self::placeFinderPattern($matrix, $func, $r, $c, $size);
        }

        // Timing patterns (row 6 and col 6)
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            if ($matrix[6][$i] === -1) {
                $matrix[6][$i] = $v;
                $func[6][$i]   = true;
            }
            if ($matrix[$i][6] === -1) {
                $matrix[$i][6] = $v;
                $func[$i][6]   = true;
            }
        }

        // Alignment patterns (v ≥ 2)
        if ($version >= 2) {
            $centres = self::$ALIGN[$version];
            foreach ($centres as $ar) {
                foreach ($centres as $ac) {
                    // Skip if centre is already occupied (finder/timing overlap)
                    if ($matrix[$ar][$ac] !== -1) {
                        continue;
                    }
                    self::placeAlignmentPattern($matrix, $func, $ar, $ac);
                }
            }
        }

        // Dark module (always dark, always at fixed position)
        $matrix[$size - 8][8] = 1;
        $func[$size - 8][8]   = true;

        // Reserve format info areas (will be overwritten per mask)
        self::reserveFormatInfo($matrix, $func, $size);

        // Place data bits using the standard zigzag pattern
        $finalMsg = self::buildFinalMessage($data, $version);
        $bitStr   = '';
        foreach ($finalMsg as $byte) {
            $bitStr .= sprintf('%08b', $byte);
        }

        $bi  = 0;
        $up  = true;
        $col = $size - 1;
        while ($col >= 0) {
            if ($col === 6) {
                $col--;
                continue; // skip timing column
            }
            for ($rowOff = 0; $rowOff < $size; $rowOff++) {
                $row = $up ? ($size - 1 - $rowOff) : $rowOff;
                for ($dc = 0; $dc <= 1; $dc++) {
                    $c = $col - $dc;
                    if (!$func[$row][$c] && $matrix[$row][$c] === -1) {
                        $matrix[$row][$c] = ($bi < strlen($bitStr))
                            ? (int)$bitStr[$bi++]
                            : 0;
                    }
                }
            }
            $up  = !$up;
            $col -= 2;
        }

        // Evaluate all 8 mask patterns and pick the one with lowest penalty
        return self::applyBestMask($matrix, $func, $size);
    }

    // =========================================================================
    // Pattern placement helpers
    // =========================================================================

    private static function placeFinderPattern(
        array &$m, array &$f, int $row, int $col, int $size
    ): void {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                // Dark if on the outer ring, inner ring, or centre
                $dark = (
                    ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) &&
                    (
                        $r === 0 || $r === 6 ||
                        $c === 0 || $c === 6 ||
                        ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)
                    )
                );
                $m[$rr][$cc] = $dark ? 1 : 0;
                $f[$rr][$cc] = true;
            }
        }
    }

    private static function placeAlignmentPattern(
        array &$m, array &$f, int $row, int $col
    ): void {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $dark = (
                    $r === -2 || $r === 2 ||
                    $c === -2 || $c === 2 ||
                    ($r === 0 && $c === 0)
                );
                $m[$row + $r][$col + $c] = $dark ? 1 : 0;
                $f[$row + $r][$col + $c] = true;
            }
        }
    }

    private static function reserveFormatInfo(
        array &$m, array &$f, int $size
    ): void {
        // Top-left region: row 8 (cols 0–8) and col 8 (rows 0–8)
        for ($i = 0; $i <= 8; $i++) {
            if (!$f[8][$i]) {
                $m[8][$i] = 0;
                $f[8][$i] = true;
            }
            if (!$f[$i][8]) {
                $m[$i][8] = 0;
                $f[$i][8] = true;
            }
        }
        // Bottom-left (col 8, rows size-7 to size-1)
        for ($i = 0; $i < 7; $i++) {
            if (!$f[$size - 7 + $i][8]) {
                $m[$size - 7 + $i][8] = 0;
                $f[$size - 7 + $i][8] = true;
            }
        }
        // Top-right (row 8, cols size-8 to size-1)
        for ($i = 0; $i < 8; $i++) {
            if (!$f[8][$size - 8 + $i]) {
                $m[8][$size - 8 + $i] = 0;
                $f[8][$size - 8 + $i] = true;
            }
        }
    }

    // =========================================================================
    // Masking
    // =========================================================================

    private static function applyBestMask(
        array $matrix, array $func, int $size
    ): array {
        $best  = null;
        $bestP = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $m = self::applyMask($matrix, $func, $mask, $size);
            self::writeFormatInfo($m, $mask, $size);
            $p = self::penaltyScore($m, $size);
            if ($p < $bestP) {
                $bestP = $p;
                $best  = $m;
            }
        }
        return $best;
    }

    private static function applyMask(
        array $matrix, array $func, int $mask, int $size
    ): array {
        $m = $matrix;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($func[$r][$c]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($r + $c) % 2 === 0,
                    1 => $r % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($r + $c) % 3 === 0,
                    4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                    5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
                    6 => ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0,
                    7 => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
                };
                if ($flip) {
                    $m[$r][$c] ^= 1;
                }
            }
        }
        return $m;
    }

    /**
     * Write 15-bit format information string into the matrix.
     * EC Level M indicator = 00b.
     */
    private static function writeFormatInfo(array &$m, int $mask, int $size): void
    {
        // data = ecLevel(2 bits) | maskPattern(3 bits); level M = 00
        $data = (0b00 << 3) | $mask;

        // BCH(15,5) error correction with generator 10100110111 (0x537)
        $bc  = $data << 10;
        $gen = 0b10100110111;
        for ($i = 14; $i >= 10; $i--) {
            if ($bc & (1 << $i)) {
                $bc ^= ($gen << ($i - 10));
            }
        }
        // XOR with mask pattern 101010000010010 (0x5412)
        $fmt = (($data << 10) | $bc) ^ 0b101010000010010;

        // Primary copy: top-left finder region
        // Bit positions 0–14 mapped to specific (row, col) pairs
        static $primary = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        for ($i = 0; $i < 15; $i++) {
            [$r, $c]   = $primary[$i];
            $m[$r][$c] = ($fmt >> $i) & 1;
        }

        // Secondary copy: bottom-left (bits 0–6) + top-right (bits 7–14)
        for ($i = 0; $i < 7; $i++) {
            $m[$size - 7 + $i][8] = ($fmt >> $i) & 1;
        }
        for ($i = 0; $i < 8; $i++) {
            $m[8][$size - 8 + $i] = ($fmt >> (7 + $i)) & 1;
        }
    }

    // =========================================================================
    // Penalty scoring (ISO 18004)
    // =========================================================================

    private static function penaltyScore(array $m, int $size): int
    {
        $penalty = 0;

        // Rule 1: 5+ consecutive modules of same colour in a row/col
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) {
                    $run++;
                    if ($run === 5)   $penalty += 3;
                    elseif ($run > 5) $penalty++;
                } else {
                    $run = 1;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) {
                    $run++;
                    if ($run === 5)   $penalty += 3;
                    elseif ($run > 5) $penalty++;
                } else {
                    $run = 1;
                }
            }
        }

        // Rule 2: 2×2 blocks of same colour
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($m[$r][$c + 1] === $v &&
                    $m[$r + 1][$c] === $v &&
                    $m[$r + 1][$c + 1] === $v) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: finder-like patterns (1:1:3:1:1 ratio)
        $pat1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $pat2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $h1 = $h2 = true;
                for ($i = 0; $i < 11; $i++) {
                    if ($m[$r][$c + $i] !== $pat1[$i]) $h1 = false;
                    if ($m[$r][$c + $i] !== $pat2[$i]) $h2 = false;
                }
                if ($h1 || $h2) $penalty += 40;
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 11; $r++) {
                $v1 = $v2 = true;
                for ($i = 0; $i < 11; $i++) {
                    if ($m[$r + $i][$c] !== $pat1[$i]) $v1 = false;
                    if ($m[$r + $i][$c] !== $pat2[$i]) $v2 = false;
                }
                if ($v1 || $v2) $penalty += 40;
            }
        }

        // Rule 4: proportion of dark modules
        $dark = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($m[$r][$c] === 1) {
                    $dark++;
                }
            }
        }
        $ratio  = $dark / ($size * $size) * 100;
        $prev5  = (int)(abs($ratio - 50) / 5);
        $next5  = $prev5 + 1;
        $penalty += min($prev5 * 10, $next5 * 10);

        return $penalty;
    }

    // =========================================================================
    // Terminal rendering
    // =========================================================================

    /**
     * Render matrix using unicode half-block characters.
     * Each pair of QR module rows becomes one line of output text.
     * Includes a 4-module quiet zone on all sides.
     */
    private static function renderHalfBlock(array $matrix): string
    {
        $size  = count($matrix);
        $quiet = 4;
        $total = $size + 2 * $quiet;

        // Build padded matrix
        $ext = array_fill(0, $total, array_fill(0, $total, 0));
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $ext[$r + $quiet][$c + $quiet] = $matrix[$r][$c];
            }
        }

        $out = '';
        for ($r = 0; $r < $total; $r += 2) {
            for ($c = 0; $c < $total; $c++) {
                $top = $ext[$r][$c] ?? 0;
                $bot = ($r + 1 < $total) ? ($ext[$r + 1][$c] ?? 0) : 0;
                $out .= match (($top << 1) | $bot) {
                    0b11 => '█',  // top dark, bottom dark
                    0b10 => '▀',  // top dark, bottom light
                    0b01 => '▄',  // top light, bottom dark
                    0b00 => ' ',  // both light
                };
            }
            $out .= "\n";
        }
        return $out;
    }

    /**
     * Render matrix as plain ASCII art (# = dark, space = light).
     */
    private static function renderAscii(array $matrix): string
    {
        $size  = count($matrix);
        $quiet = 4;
        $total = $size + 2 * $quiet;

        $ext = array_fill(0, $total, array_fill(0, $total, 0));
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $ext[$r + $quiet][$c + $quiet] = $matrix[$r][$c];
            }
        }

        $border = str_repeat('#', $total + 2) . "\n";
        $out    = $border;
        foreach ($ext as $row) {
            $line = '#';
            foreach ($row as $v) {
                $line .= $v ? '#' : ' ';
            }
            $out .= $line . "#\n";
        }
        $out .= $border;
        return $out;
    }
}
