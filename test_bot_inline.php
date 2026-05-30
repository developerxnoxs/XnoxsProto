<?php
/**
 * test_bot_inline.php
 * Standalone test: chat ke @xnoxsguard_bot + klik tombol inline
 *
 * Alur:
 *   1. Login dengan session tersimpan
 *   2. Kirim /start ke bot
 *   3. Tunggu & tampilkan balasan bot (real-time via onUpdate)
 *   4. Jika balasan punya tombol inline → tampilkan & tanya tombol mana yang diklik
 *   5. Klik tombol → tampilkan respons callback
 *   6. Lanjut listen untuk balasan berikutnya
 */

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\RawUpdateEvent;

// ─── Konfigurasi ────────────────────────────────────────────────────────────
const BOT      = '@xnoxsguard_bot';
const API_ID   = 19001991;
const API_HASH = 'f3eb78228439ad8ac3b81729df992a9a';

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
    printf("  [%s] %s %s: %s\n", ts(), $arah, $label, $text);
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
hr('═');

// ─── Kirim /start ke bot ────────────────────────────────────────────────────
echo "\n[1] Mengirim /start ke " . BOT . " ...\n";
try {
    $client->startBot(BOT, BOT, '');
    printMsg('Kita → bot', '/start', true);
} catch (\Throwable $e) {
    // Kalau bot sudah pernah di-start, coba kirim pesan biasa
    echo "  (startBot gagal: {$e->getMessage()}) — kirim pesan teks biasa...\n";
    try {
        $client->sendMessage(BOT, '/start');
        printMsg('Kita → bot', '/start (sendMessage)', true);
    } catch (\Throwable $e2) {
        echo "  [ERROR] Kirim pesan juga gagal: {$e2->getMessage()}\n";
    }
}

// ─── State tracking pesan yang punya tombol inline ──────────────────────────
$pendingClick = null;   // menyimpan FullMessage terakhir dari bot yang punya tombol

// ─── Daftarkan update handler ────────────────────────────────────────────────
echo "\n[2] Mendengarkan balasan bot...\n\n";
hr();

$client->onUpdate(function (RawUpdateEvent $event) use ($client, &$pendingClick): void {

    // ── Pesan baru ──────────────────────────────────────────────────────────
    if ($event->type === 'new_message') {
        $msg  = $event->message;
        $from = $msg->fromUserId ?? 0;
        $out  = $msg->out;
        $text = $msg->text ?? '[non-teks]';

        if ($out) {
            // Pesan dari kita sendiri (konfirmasi dikirim)
            printMsg('Kita', $text, true);
            return;
        }

        // Pesan masuk dari bot
        printMsg('Bot', $text, false);

        // Tampilkan tombol inline jika ada
        if (!empty($msg->replyMarkup['rows'])) {
            $rows = $msg->replyMarkup['rows'];
            printButtons($rows);
            $pendingClick = $msg;

            // Otomatis klik tombol pertama jika berupa callback
            // Menggunakan label teks — tidak perlu tahu posisi array
            $firstBtn = $rows[0][0] ?? null;
            if ($firstBtn !== null && ($firstBtn['type'] ?? '') === 'callback') {
                $label = $firstBtn['text'];
                echo "  [AUTO] Mengklik tombol: \"{$label}\"\n";
                try {
                    $resp = $msg->click($label);
                    if (!empty($resp)) {
                        echo "  [CALLBACK] Respons: " . json_encode($resp, JSON_UNESCAPED_UNICODE) . "\n";
                    } else {
                        echo "  [CALLBACK] Tidak ada respons (server ack saja)\n";
                    }
                } catch (\Throwable $e) {
                    echo "  [ERROR] Click gagal: {$e->getMessage()}\n";
                }
            } elseif ($firstBtn !== null && !empty($firstBtn['url'])) {
                echo "  [URL BTN] Tombol [0,0] adalah URL — tidak diklik otomatis.\n";
                echo "  URL: {$firstBtn['url']}\n";
            }
            echo "\n";
        }

        flush();
        return;
    }

    // ── Edit pesan dari bot ─────────────────────────────────────────────────
    if ($event->type === 'edit_message') {
        $msg = $event->message;
        if (!$msg->out) {
            printf("  [%s] ✏️  Bot edit pesan #%d: %s\n", ts(), $msg->id, $msg->text ?? '[non-teks]');
            if (!empty($msg->replyMarkup['rows'])) {
                printButtons($msg->replyMarkup['rows']);
                $pendingClick = $msg;
            }
            flush();
        }
    }
});

// ─── Jalankan event loop ─────────────────────────────────────────────────────
echo "  (Ctrl+C untuk berhenti)\n\n";
try {
    $client->runUntilDisconnected();
} catch (\Throwable $e) {
    echo "\n[STOP] {$e->getMessage()}\n";
}
