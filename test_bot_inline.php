<?php
/**
 * test_bot_inline.php
 * Tes klik tombol inline — urutan aksi berganda dengan label teks
 *
 * Skenario:
 *   1. Kirim /start ke bot
 *   2. Klik "⭐ Fitur"
 *   3. Setelah bot edit → klik "⬅️ Kembali"
 *   4. Setelah bot edit kembali → klik "ℹ️ Tentang"
 *   5. Setelah bot edit → klik "⬅️ Kembali" lagi
 *   6. Selesai
 */

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\RawUpdateEvent;

// ─── Konfigurasi ────────────────────────────────────────────────────────────
const BOT      = '@xnoxsguard_bot';
const API_ID   = 19001991;
const API_HASH = 'f3eb78228439ad8ac3b81729df992a9a';

// ─── Urutan tombol yang akan diklik ─────────────────────────────────────────
// Setiap elemen adalah label teks yang dicari di pesan bot berikutnya
$clickQueue = [
    '⭐ Fitur',        // klik pertama setelah /start
    'Kembali',         // partial match "⬅️ Kembali"
    'Tentang',         // partial match "ℹ️ Tentang"
    'Kembali',         // kembali ke menu utama
];
$clickStep  = 0;       // langkah sekarang di $clickQueue

// ─── Helper output ──────────────────────────────────────────────────────────
function ts(): string { return date('H:i:s'); }

function hr(string $char = '─', int $n = 60): void
{
    echo str_repeat($char, $n) . "\n";
}

function printMsg(string $from, string $text, bool $out = false): void
{
    $arah  = $out ? '→' : '←';
    $label = $out ? "\e[36m{$from}\e[0m" : "\e[32m{$from}\e[0m";
    // Batasi teks panjang agar tidak banjir layar
    $preview = mb_strlen($text) > 120 ? mb_substr($text, 0, 117) . '...' : $text;
    printf("  [%s] %s %s: %s\n", ts(), $arah, $label, $preview);
}

function printButtons(array $rows): void
{
    echo "\n  \e[33m[Tombol inline]\e[0m\n";
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $btn) {
            $type = $btn['type'] ?? 'callback';
            $url  = !empty($btn['url']) ? " (url: {$btn['url']})" : '';
            printf("    [%d,%d] %-30s  type=%s%s\n",
                $r, $c,
                $btn['text'] ?? '?',
                $type,
                $url
            );
        }
    }
    echo "\n";
}

function tryClick(mixed $msg, string $label): void
{
    echo "  \e[35m[KLIK]\e[0m Mencoba klik tombol: \"\e[1m{$label}\e[0m\"\n";
    try {
        $resp = $msg->click($label);
        if (!empty($resp)) {
            echo "  \e[32m[OK]\e[0m Respons: " . json_encode($resp, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "  \e[32m[OK]\e[0m Server ACK (tidak ada isi respons)\n";
        }
    } catch (\Throwable $e) {
        echo "  \e[31m[ERROR]\e[0m " . $e->getMessage() . "\n";
    }
    echo "\n";
    flush();
}

// ─── Login ──────────────────────────────────────────────────────────────────
$sessions = glob(__DIR__ . '/sessions/*.session') ?: [];
if (empty($sessions)) {
    die("[FATAL] Tidak ada session. Jalankan xnoxs_tester.php untuk login dulu.\n");
}

$phone  = pathinfo($sessions[0], PATHINFO_FILENAME);
$client = new TelegramClient(API_ID, API_HASH);
$client->setSessionsDir(__DIR__ . '/sessions');
$client->start($phone);

$me = $client->getMe();
hr('═');
printf("  Login sebagai : %s %s (ID: %d)\n",
    $me['first_name'],
    $me['last_name'] ?? '',
    $me['id']
);
printf("  Target bot    : %s\n", BOT);
printf("  Rencana klik  : %s\n", implode(' → ', $clickQueue));
hr('═');

// ─── Kirim /start ke bot ────────────────────────────────────────────────────
echo "\n[1] Mengirim /start ke " . BOT . " ...\n";
try {
    $client->startBot(BOT, BOT, '');
    printMsg('Kita → bot', '/start', true);
} catch (\Throwable $e) {
    echo "  (startBot gagal: {$e->getMessage()}) — kirim pesan teks biasa...\n";
    try {
        $client->sendMessage(BOT, '/start');
        printMsg('Kita → bot', '/start (sendMessage)', true);
    } catch (\Throwable $e2) {
        echo "  [ERROR] Kirim pesan juga gagal: {$e2->getMessage()}\n";
    }
}

// ─── Daftarkan update handler ────────────────────────────────────────────────
echo "\n[2] Mendengarkan balasan bot & menjalankan urutan klik...\n\n";
hr();

$client->onUpdate(function (RawUpdateEvent $event) use ($client, $clickQueue, &$clickStep): void {

    // ── Pesan baru ──────────────────────────────────────────────────────────
    if ($event->type === 'new_message') {
        $msg  = $event->message;
        $out  = $msg->out;
        $text = $msg->text ?? '[non-teks]';

        if ($out) {
            printMsg('Kita', $text, true);
            return;
        }

        printMsg('Bot', $text, false);

        if (!empty($msg->replyMarkup['rows'])) {
            printButtons($msg->replyMarkup['rows']);

            if (isset($clickQueue[$clickStep])) {
                $label = $clickQueue[$clickStep];
                $clickStep++;
                tryClick($msg, $label);
            } else {
                echo "  \e[90m[INFO] Semua langkah klik selesai — hanya mendengarkan.\e[0m\n\n";
            }
        }
        return;
    }

    // ── Edit pesan dari bot ─────────────────────────────────────────────────
    if ($event->type === 'edit_message') {
        $msg = $event->message;
        if ($msg->out) return;

        $text = $msg->text ?? '[non-teks]';
        $preview = mb_strlen($text) > 120 ? mb_substr($text, 0, 117) . '...' : $text;
        printf("  [%s] \e[33m✏️  Bot edit pesan #%d\e[0m: %s\n", ts(), $msg->id, $preview);

        if (!empty($msg->replyMarkup['rows'])) {
            printButtons($msg->replyMarkup['rows']);

            if (isset($clickQueue[$clickStep])) {
                $label = $clickQueue[$clickStep];
                $clickStep++;
                tryClick($msg, $label);
            } else {
                echo "  \e[90m[INFO] Semua langkah klik selesai.\e[0m\n\n";
            }
        }
        flush();
    }
});

// ─── Jalankan event loop ─────────────────────────────────────────────────────
echo "  (Ctrl+C untuk berhenti)\n\n";
try {
    $client->runUntilDisconnected();
} catch (\Throwable $e) {
    echo "\n[STOP] {$e->getMessage()}\n";
}
