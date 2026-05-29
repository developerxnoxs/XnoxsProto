<?php

/**
 * Test Download Media — XnoxsProto
 *
 * Script ini menguji kemampuan download media dari Telegram:
 *   - Foto
 *   - Video
 *   - Audio
 *   - Dokumen
 *   - Voice
 *
 * Jalankan: php test_download.php
 *
 * Pastikan sudah login dulu (php interactive_login.php) dan ada
 * session aktif di folder sessions/.
 *
 * Konfigurasi: set variabel di bawah sesuai kebutuhan.
 */

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

// ─── KONFIGURASI ─────────────────────────────────────────────────────────────
$apiId   = (int)($_ENV['TG_API_ID']   ?? getenv('TG_API_ID')   ?: 0);
$apiHash =       $_ENV['TG_API_HASH'] ?? getenv('TG_API_HASH') ?: '';

// Ubah ke username/nomor/ID chat yang punya media (foto, video, dokumen, dll.)
// Contoh: '@username', '+6281234567890', atau ID numerik
$TEST_PEER = '@telegram'; // Channel resmi Telegram (biasanya punya foto/video)

// Berapa pesan yang akan dicek untuk cari media
$SCAN_LIMIT = 20;

// Folder untuk menyimpan file download
$DOWNLOAD_DIR = __DIR__ . '/downloads';
// ─────────────────────────────────────────────────────────────────────────────

if ($apiId === 0 || $apiHash === '') {
    die("❌  Set TG_API_ID dan TG_API_HASH di environment atau .env\n");
}

// Buat folder downloads
if (!is_dir($DOWNLOAD_DIR)) {
    mkdir($DOWNLOAD_DIR, 0755, true);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  XnoxsProto — Test Download Media\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// ─── Init client ─────────────────────────────────────────────────────────────
echo "📡  Menghubungkan ke Telegram...\n";
$client = TelegramClient::create($apiId, $apiHash);

// Cari session yang sudah ada
$sessionFiles = glob(__DIR__ . '/sessions/*.session') ?: [];
if (empty($sessionFiles)) {
    die("❌  Tidak ada session aktif. Jalankan php interactive_login.php dulu.\n");
}

// Ambil session terbaru
usort($sessionFiles, fn($a, $b) => filemtime($b) - filemtime($a));
$sessionFile = $sessionFiles[0];
echo "🔑  Menggunakan session: " . basename($sessionFile) . "\n";

$client2 = new TelegramClient($apiId, $apiHash, $sessionFile);
$client2->connect();

if (!$client2->getAuth()->isAuthorized()) {
    die("❌  Session tidak terautentikasi. Login ulang dengan php interactive_login.php\n");
}

$me = $client2->getMe();
echo "✅  Login sebagai: {$me['first_name']} (@{$me['username']})\n\n";

// ─── Ambil history ────────────────────────────────────────────────────────────
echo "📬  Mengambil $SCAN_LIMIT pesan terbaru dari $TEST_PEER...\n";

try {
    $history = $client2->getHistory($TEST_PEER, $SCAN_LIMIT);
} catch (\Throwable $e) {
    die("❌  Gagal ambil history: " . $e->getMessage() . "\n");
}

$messages = $history['messages'] ?? [];
echo "📨  Ditemukan " . count($messages) . " pesan.\n\n";

// ─── Cari pesan dengan media ──────────────────────────────────────────────────
$mediaMessages = array_filter($messages, fn($m) => !empty($m['media']) && !empty($m['media']['id']));
$mediaMessages = array_values($mediaMessages);

if (empty($mediaMessages)) {
    echo "⚠️   Tidak ada pesan dengan media yang bisa diunduh di $SCAN_LIMIT pesan terakhir.\n";
    echo "     Coba ganti TEST_PEER ke channel/grup yang aktif mengirim media.\n";
    $client2->disconnect();
    exit(0);
}

echo "🎯  Ditemukan " . count($mediaMessages) . " pesan dengan media:\n";
echo str_repeat('─', 60) . "\n";

$stats = ['total' => 0, 'sukses' => 0, 'gagal' => 0, 'skip' => 0];

foreach ($mediaMessages as $idx => $msg) {
    $media    = $msg['media'];
    $type     = $media['type']     ?? 'unknown';
    $mime     = $media['mime']     ?? '';
    $filename = $media['filename'] ?? '';
    $size     = $media['size']     ?? 0;
    $dcId     = $media['dc_id']    ?? '?';
    $msgId    = $msg['id'];

    $sizeStr = $size > 0 ? sprintf('%.1f KB', $size / 1024) : '? KB';
    echo sprintf(
        "[%d] MSG #%d | type=%-10s mime=%-20s dc=%s size=%s\n",
        $idx + 1, $msgId, $type, $mime ?: '-', $dcId, $sizeStr
    );
    if ($filename) echo "     filename: $filename\n";

    // Skip tipe yang tidak perlu diunduh
    if (in_array($type, ['sticker', 'gif', 'custom_emoji'], true)) {
        echo "     ⏭️   Skip (sticker/gif/emoji tidak diunduh)\n";
        $stats['skip']++;
        continue;
    }

    // Tentukan nama file output
    $ext      = $client2->getMediaExtension($media);
    $outName  = sprintf('msg%d_%s.%s', $msgId, $type, $ext);
    $outPath  = $DOWNLOAD_DIR . '/' . $outName;
    $stats['total']++;

    // Progress callback
    $lastPct = -1;
    $progress = function(int $recv, int $total, int $pct) use (&$lastPct) {
        if ($pct !== $lastPct && ($pct % 10 === 0 || $pct === 100)) {
            echo "\r     ⬇️   Mengunduh: {$pct}% (" . number_format($recv) . " bytes)      ";
            $lastPct = $pct;
        }
    };

    echo "     ⬇️   Menyimpan ke: $outName\n";
    $start = microtime(true);

    try {
        $savedPath = $client2->downloadMedia($msg, $outPath, $progress);
        $elapsed   = round(microtime(true) - $start, 2);
        $fileSize  = file_exists($savedPath) ? filesize($savedPath) : 0;

        if ($fileSize === 0) {
            echo "\r     ⚠️   File kosong setelah download ($elapsed detik)\n";
            $stats['gagal']++;
        } else {
            echo "\r     ✅  Sukses! " . number_format($fileSize) . " bytes dalam {$elapsed}s\n";
            $stats['sukses']++;
        }
    } catch (\Throwable $e) {
        echo "\r     ❌  Gagal: " . $e->getMessage() . "\n";
        $stats['gagal']++;
        // Hapus file parsial
        if (file_exists($outPath) && filesize($outPath) === 0) {
            unlink($outPath);
        }
    }

    echo "\n";

    // Jangan download terlalu banyak sekaligus — ambil 3 file pertama saja untuk test
    if ($stats['total'] >= 3) {
        echo "ℹ️   Batas 3 file tercapai, berhenti (ubah batas di script untuk lebih banyak).\n\n";
        break;
    }
}

echo str_repeat('─', 60) . "\n";
echo "📊  Ringkasan:\n";
echo "    Total dicoba : {$stats['total']}\n";
echo "    ✅ Sukses    : {$stats['sukses']}\n";
echo "    ❌ Gagal     : {$stats['gagal']}\n";
echo "    ⏭️  Dilewati  : {$stats['skip']}\n";
echo "\n";

// ─── Tampilkan daftar file yang berhasil diunduh ──────────────────────────────
$downloaded = glob($DOWNLOAD_DIR . '/*');
if (!empty($downloaded)) {
    echo "📁  File tersimpan di: $DOWNLOAD_DIR\n";
    foreach ($downloaded as $f) {
        echo "    " . basename($f) . " (" . number_format(filesize($f)) . " bytes)\n";
    }
}

$client2->disconnect();
echo "\n✅  Test selesai.\n";
