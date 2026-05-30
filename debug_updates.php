<?php
/**
 * debug_updates.php — standalone debug penerima update
 * Jalankan: php debug_updates.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\TL\Functions\UpdatesGetStateRequest;

$API_ID   = 19001991;
$API_HASH = 'f3eb78228439ad8ac3b81729df992a9a';

// ── Cari session ───────────────────────────────────────────────────────────
$sessionsDir = __DIR__ . '/sessions';
$sessions    = glob($sessionsDir . '/*.session') ?: [];

if (empty($sessions)) {
    die("[ERROR] Tidak ada session. Jalankan xnoxs_tester.php dulu untuk login.\n");
}

$sessionFile = $sessions[0];
$phone       = pathinfo($sessionFile, PATHINFO_FILENAME);
echo "[INFO] Pakai session: $sessionFile\n";

// ── Setup client ───────────────────────────────────────────────────────────
$client = new TelegramClient($API_ID, $API_HASH);
$client->setSessionsDir($sessionsDir);
$client->start($phone);

$me = $client->getMe();
echo "[INFO] Login sebagai: {$me['first_name']} (ID: {$me['id']})\n";

$sender = $client->getSender();

// ── Panggil getState dan cetak hasilnya ────────────────────────────────────
try {
    $req  = new UpdatesGetStateRequest();
    $req  = $client->wrapFirstRequest($req);
    $resp = $sender->send($req);
    $r    = $resp['reader'];
    $pts     = $r->readInt();
    $qts     = $r->readInt();
    $date    = $r->readInt();
    $seq     = $r->readInt();
    $unread  = $r->readInt();
    echo "[STATE] pts=$pts  qts=$qts  date=$date  seq=$seq  unread=$unread\n";
} catch (\Throwable $e) {
    echo "[WARN] getState gagal: " . $e->getMessage() . "\n";
}

echo str_repeat('─', 60) . "\n";
echo "[INFO] Mendengarkan... Kirim pesan dari akun lain sekarang.\n";
echo "[INFO] Titik (.) = timeout 2 detik, tidak ada packet.\n";
echo str_repeat('─', 60) . "\n";

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () { echo "\n[INFO] Dihentikan.\n"; exit(0); });
}

$lastPing = time();
$pktNo    = 0;

while (true) {
    if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();

    // Ping tiap 15 detik
    if (time() - $lastPing >= 15) {
        try {
            $sender->ping();
            echo "\n[PING] " . date('H:i:s') . "\n";
        } catch (\Throwable $e) {
            echo "\n[PING-ERR] " . $e->getMessage() . "\n";
        }
        $lastPing = time();
    }

    $info = null;
    try {
        $info = $sender->receiveDebug(2);
    } catch (\Throwable $e) {
        echo "\n[RECV-ERR] " . $e->getMessage() . "\n";
        sleep(1);
        continue;
    }

    if ($info === null) {
        echo '.';
        flush();
        continue;
    }

    $pktNo++;
    echo "\n[PKT #$pktNo @ " . date('H:i:s') . "] " . $info['ctor'] . " = " . $info['name']
        . "  (" . $info['raw_len'] . " bytes)";

    if (isset($info['error'])) {
        echo "  ERROR: " . $info['error'];
    }

    if (!empty($info['container'])) {
        echo "\n  Container isi " . count($info['container']) . " pesan:";
        foreach ($info['container'] as $i => $inner) {
            echo "\n    [$i] " . $inner['ctor'] . " = " . $inner['name'] . "  (" . $inner['bytes'] . " bytes)";
        }
    }

    echo "\n";
    flush();
}
