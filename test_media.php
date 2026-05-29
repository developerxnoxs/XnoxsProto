<?php

/**
 * XnoxsProto — Test Kirim & Download Media
 *
 * Semua dikirim ke Saved Messages ('me') — tidak ganggu siapapun.
 * File download disimpan ke downloads/
 *
 * Jalankan: php test_media.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

// ─── Config ──────────────────────────────────────────────────────────────────
$apiId   = (int)($_ENV['TG_API_ID']   ?? getenv('TG_API_ID')   ?: 0);
$apiHash =       $_ENV['TG_API_HASH'] ?? getenv('TG_API_HASH') ?: '';

$sessionFiles = glob(__DIR__ . '/sessions/*.session') ?: [];
if (empty($sessionFiles)) die("❌  Tidak ada session. Jalankan php interactive_login.php\n");
usort($sessionFiles, fn($a,$b) => filemtime($b)-filemtime($a));
$sessionFile = $sessionFiles[0];

if (!is_dir('downloads')) mkdir('downloads', 0755, true);

// ─── State ───────────────────────────────────────────────────────────────────
$pass = 0; $fail = 0;
$sentIds = [];
$log     = [];

function ok(string $label, string $detail = ''): void {
    global $pass, $log;
    $pass++;
    $log[] = ['status' => 'PASS', 'label' => $label, 'detail' => $detail];
    echo "  ✅  $label" . ($detail ? " — $detail" : '') . "\n";
}
function fail(string $label, string $err): void {
    global $fail, $log;
    $fail++;
    $log[] = ['status' => 'FAIL', 'label' => $label, 'detail' => $err];
    echo "  ❌  $label — $err\n";
}

// ─── Connect ─────────────────────────────────────────────────────────────────
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  XnoxsProto — Test Kirim & Download Media\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📡  Menghubungkan: " . basename($sessionFile) . "\n";
$client = TelegramClient::create($apiId, $apiHash, $sessionFile);
$client->connect();
if (!$client->getAuth()->isAuthorized()) die("❌  Session tidak valid.\n");

$me = $client->getMe();
echo "✅  Login: {$me['first_name']} (ID: {$me['id']})\n";
echo "    Tujuan: Saved Messages (me)\n\n";

$PEER = 'me';

// ═══════════════════════════════════════════════════════════════════════════
// BAGIAN 1 — KIRIM MEDIA
// ═══════════════════════════════════════════════════════════════════════════

echo "┌─────────────────────────────────────────────────\n";
echo "│  BAGIAN 1 — KIRIM MEDIA\n";
echo "└─────────────────────────────────────────────────\n";

// 1.1 sendPhoto
echo "\n[1.1] sendPhoto (JPG → tampil inline)\n";
try {
    $r = $client->sendPhoto($PEER, 'test_assets/test_photo.jpg', 'XnoxsProto sendPhoto ✅');
    if (!empty($r['message_id'])) {
        $sentIds['photo'] = $r['message_id'];
        ok('sendPhoto', "msg_id={$r['message_id']}");
    } else {
        fail('sendPhoto', json_encode($r));
    }
} catch (\Throwable $e) { fail('sendPhoto', $e->getMessage()); }
sleep(1);

// 1.2 sendDocument (TXT)
echo "\n[1.2] sendDocument (TXT)\n";
try {
    $r = $client->sendDocument($PEER, 'test_assets/test_doc.txt', 'XnoxsProto sendDocument ✅', 'xnoxsproto_test.txt');
    if (!empty($r['message_id'])) {
        $sentIds['document'] = $r['message_id'];
        ok('sendDocument', "msg_id={$r['message_id']}");
    } else {
        fail('sendDocument', json_encode($r));
    }
} catch (\Throwable $e) { fail('sendDocument', $e->getMessage()); }
sleep(1);

// 1.3 sendAudio (MP3)
echo "\n[1.3] sendAudio (MP3)\n";
try {
    $r = $client->sendAudio($PEER, 'test_assets/test_audio.mp3', 'XnoxsProto sendAudio ✅',
        duration: 1, title: 'XnoxsProto Test', performer: 'Library');
    if (!empty($r['message_id'])) {
        $sentIds['audio'] = $r['message_id'];
        ok('sendAudio', "msg_id={$r['message_id']}");
    } else {
        fail('sendAudio', json_encode($r));
    }
} catch (\Throwable $e) { fail('sendAudio', $e->getMessage()); }
sleep(1);

// 1.4 sendFile auto-detect
echo "\n[1.4] sendFile (auto-detect JPG → foto)\n";
try {
    $r = $client->sendFile($PEER, 'test_assets/test_photo.jpg', 'sendFile auto-detect ✅');
    if (!empty($r['message_id'])) {
        ok('sendFile auto-detect', "msg_id={$r['message_id']}");
    } else {
        fail('sendFile auto-detect', json_encode($r));
    }
} catch (\Throwable $e) { fail('sendFile auto-detect', $e->getMessage()); }
sleep(1);

// 1.5 sendFile forceDocument
echo "\n[1.5] sendFile (forceDocument=true)\n";
try {
    $r = $client->sendFile($PEER, 'test_assets/test_photo.jpg', 'sendFile forceDocument ✅', forceDocument: true);
    if (!empty($r['message_id'])) {
        ok('sendFile forceDocument', "msg_id={$r['message_id']}");
    } else {
        fail('sendFile forceDocument', json_encode($r));
    }
} catch (\Throwable $e) { fail('sendFile forceDocument', $e->getMessage()); }

sleep(2);

// ═══════════════════════════════════════════════════════════════════════════
// BAGIAN 2 — DOWNLOAD MEDIA (dari pesan yang baru dikirim)
// ═══════════════════════════════════════════════════════════════════════════

echo "\n┌─────────────────────────────────────────────────\n";
echo "│  BAGIAN 2 — DOWNLOAD MEDIA\n";
echo "└─────────────────────────────────────────────────\n\n";

if (empty($sentIds)) {
    echo "⚠️   Tidak ada pesan yang berhasil dikirim — skip download test.\n";
} else {
    // getHistory() mengembalikan flat array pesan langsung (bukan ['messages'=>[...]])
    echo "  Mengambil history Saved Messages...\n";
    try {
        $messages = $client->getHistory($PEER, 20);
        echo "  Pesan ditemukan: " . count($messages) . "\n\n";
    } catch (\Throwable $e) {
        echo "  ❌  Gagal ambil history: " . $e->getMessage() . "\n";
        $messages = [];
    }

    // Index by message_id
    $msgById = [];
    foreach ($messages as $m) {
        $msgById[$m['id']] = $m;
    }

    foreach ($sentIds as $tipe => $msgId) {
        echo "[2.x] Download $tipe (msg #$msgId)\n";

        if (!isset($msgById[$msgId])) {
            fail("download $tipe", "msg #$msgId tidak ada di history (ambil 20, ada: " . implode(',', array_keys($msgById)) . ")");
            continue;
        }

        $msg   = $msgById[$msgId];
        $media = $msg['media'] ?? null;

        if (empty($media)) {
            fail("download $tipe", "field media null/kosong");
            continue;
        }
        if (empty($media['id'])) {
            fail("download $tipe", "media.id kosong — media=" . json_encode($media));
            continue;
        }

        $ext     = $client->getMediaExtension($media);
        $outPath = "downloads/{$tipe}_msg{$msgId}.{$ext}";

        echo "  type={$media['type']} mime=" . ($media['mime'] ?? '-') . " dc_id=" . ($media['dc_id'] ?? '?') . " size=" . ($media['size'] ?? '?') . "\n";

        $start = microtime(true);
        try {
            $savedPath = $client->downloadMedia($msg, $outPath, function($recv, $total, $pct) {
                static $last = -1;
                if ($pct !== $last) { echo "\r  ⬇️   $pct% — " . number_format($recv) . " bytes     "; $last = $pct; }
            });
            echo "\r";
            $elapsed  = round(microtime(true) - $start, 2);
            $fileSize = file_exists($savedPath) ? filesize($savedPath) : 0;

            if ($fileSize > 0) {
                ok("download $tipe", number_format($fileSize) . " bytes, {$elapsed}s");
            } else {
                fail("download $tipe", "file kosong setelah download");
            }
        } catch (\Throwable $e) {
            echo "\r";
            fail("download $tipe", $e->getMessage());
        }
        echo "\n";
        sleep(1);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// RINGKASAN
// ═══════════════════════════════════════════════════════════════════════════

$total = $pass + $fail;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊  RINGKASAN: $pass/$total PASS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($log as $r) {
    $icon = $r['status'] === 'PASS' ? '✅' : '❌';
    echo "  $icon  [{$r['status']}] {$r['label']}" . ($r['detail'] ? " — {$r['detail']}" : '') . "\n";
}
echo "\n";

$downloaded = glob('downloads/*') ?: [];
if (!empty($downloaded)) {
    echo "📁  File download tersimpan di: downloads/\n";
    foreach ($downloaded as $f) {
        echo "    " . basename($f) . " (" . number_format(filesize($f)) . " bytes)\n";
    }
    echo "\n";
}

file_put_contents('test_media_result.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'session'   => basename($sessionFile),
    'pass'      => $pass,
    'fail'      => $fail,
    'total'     => $total,
    'results'   => $log,
    'downloads' => array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f)], $downloaded),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "📄  Log: test_media_result.json\n\n";
$client->disconnect();
echo "✅  Test selesai.\n";
