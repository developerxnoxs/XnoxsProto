<?php

/**
 * XnoxsProto — Basic Group Management Test
 * ==========================================
 * Menguji semua fitur manajemen basic group secara otomatis.
 *
 * Alur:
 *   1. createChat     — buat grup baru (anggota: akun sendiri + @SpamBot)
 *   2. getFullChat    — ambil info lengkap grup
 *   3. sendMessage    — kirim pesan ke grup
 *   4. getHistory     — verifikasi pesan masuk
 *   5. editChatTitle  — ubah judul grup
 *   6. exportInviteLink — buat link undangan
 *   7. setDefaultPermissions — atur izin default anggota
 *   8. pinMessage     — pin pesan
 *   9. unpinMessage   — unpin pesan
 *  10. promoteAdmin   — jadikan @SpamBot admin
 *  11. demoteAdmin    — cabut status admin
 *  12. kickUser       — keluarkan @SpamBot
 *  13. addChatUser    — tambahkan @SpamBot kembali
 *  14. banUser        — keluarkan @SpamBot permanen (basic group = kick permanen)
 *  15. deleteMessages — hapus pesan test di grup
 *  16. deleteChat     — hapus grup (cleanup)
 *
 * Jalankan:
 *   php test_group.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;
use XnoxsProto\TL\Types\InputPeer;

// ═══════════════════════════════════════════════════════════════════════════
// HELPER
// ═══════════════════════════════════════════════════════════════════════════

$pass = 0; $fail = 0;
$results = [];

function step(string $name, callable $fn): mixed
{
    global $pass, $fail, $results;
    echo "  » $name ... ";
    try {
        $ret = $fn();
        $detail = is_string($ret) ? $ret : '';
        $results[] = ['PASS', $name, $detail];
        echo "\033[32mPASS\033[0m" . ($detail ? " — $detail" : '') . "\n";
        $pass++;
        return $ret;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $results[] = ['FAIL', $name, $msg];
        echo "\033[31mFAIL\033[0m — $msg\n";
        $fail++;
        return null;
    }
}

function stepExpectFail(string $name, callable $fn, string $expectedMsg = ''): void
{
    global $pass, $fail, $results;
    echo "  » $name (expect exception) ... ";
    try {
        $fn();
        $results[] = ['FAIL', $name, 'Tidak melempar exception seperti yang diharapkan'];
        echo "\033[31mFAIL\033[0m — tidak throw exception\n";
        $fail++;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if ($expectedMsg !== '' && stripos($msg, $expectedMsg) === false) {
            $results[] = ['FAIL', $name, "Pesan salah: $msg"];
            echo "\033[31mFAIL\033[0m — pesan salah: $msg\n";
            $fail++;
        } else {
            $results[] = ['PASS', $name, "exception: $msg"];
            echo "\033[32mPASS\033[0m — exception tertangkap: " . substr($msg, 0, 80) . "\n";
            $pass++;
        }
    }
}

function section(string $t): void { echo "\n\033[1;34m── $t\033[0m\n"; }

// ═══════════════════════════════════════════════════════════════════════════
// BANNER & KONEKSI
// ═══════════════════════════════════════════════════════════════════════════

echo "\n\033[1;36m╔══════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║    Basic Group Management Test              ║\033[0m\n";
echo "\033[1;36m╚══════════════════════════════════════════════╝\033[0m\n";

$apiId   = (int)  getenv('TG_API_ID');
$apiHash = trim((string) getenv('TG_API_HASH'));

if ($apiId === 0 || $apiHash === '') {
    echo "\n\033[31m  ERROR: TG_API_ID / TG_API_HASH belum di-set.\033[0m\n";
    exit(1);
}

$sessionFiles = glob(__DIR__ . '/*.session') ?: [];
usort($sessionFiles, fn($a, $b) => filemtime($b) - filemtime($a));
$sessionFile  = $sessionFiles[0] ?? null;

$client = new TelegramClient($apiId, $apiHash, $sessionFile ? new FileSession($sessionFile) : null);

section('Koneksi');
step('connect()', function () use ($client) {
    $client->connect();
    if (!$client->isConnected()) throw new \RuntimeException('isConnected false');
    $me = $client->getMe();
    return "id={$me['id']} name={$me['first_name']}";
});

// ── Resolve @SpamBot (anggota kedua grup) ─────────────────────────────────
$botPeer   = null;
$botUserId = null;
section('Resolve bot anggota');
$resolveResult = step('resolvePeer(@SpamBot)', function () use ($client, &$botPeer, &$botUserId) {
    $botPeer   = $client->resolvePeer('@SpamBot');
    $botUserId = $botPeer->getId();
    if ($botUserId <= 0) throw new \RuntimeException('ID tidak valid');
    return "id=$botUserId type=0x" . dechex($botPeer->getType());
});

if ($botPeer === null) {
    echo "\n\033[31m  FATAL: Tidak bisa resolve @SpamBot — test dihentikan.\033[0m\n";
    $client->disconnect();
    exit(1);
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 1 — BUAT GRUP
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 1 — createChat');

$chatId   = null;
$groupMsg = [];   // ID pesan yang dikirim ke grup (untuk cleanup)

step('createChat() — buat basic group baru', function () use ($client, &$chatId, $botPeer) {
    $ts     = date('H:i:s');
    $result = $client->createChat("[XnoxsProto Test] Grup $ts", '@SpamBot');
    if ($result['created'] !== true)       throw new \RuntimeException("'created' bukan true");
    if (!isset($result['chat_id']))        throw new \RuntimeException("'chat_id' tidak ada — parse updates mungkin gagal");
    if ($result['chat_id'] <= 0)           throw new \RuntimeException('chat_id tidak valid');

    $chatId = $result['chat_id'];
    return "chat_id=$chatId title={$result['title']}";
});

if ($chatId === null) {
    echo "\n\033[31m  FATAL: createChat gagal — tidak ada chat_id, test dihentikan.\033[0m\n";
    $client->disconnect();
    exit(1);
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 2 — BACA INFO GRUP
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 2 — getFullChat');

step('getFullChat() — ambil info lengkap', function () use ($client, $chatId) {
    $info = $client->getFullChat($chatId);
    if (!array_key_exists('id', $info))                  throw new \RuntimeException("'id' tidak ada");
    if (!array_key_exists('title', $info))               throw new \RuntimeException("'title' tidak ada");
    if (!array_key_exists('participants_count', $info))  throw new \RuntimeException("'participants_count' tidak ada");
    if (!array_key_exists('about', $info))               throw new \RuntimeException("'about' tidak ada");
    if (!array_key_exists('type', $info))                throw new \RuntimeException("'type' tidak ada");
    if ($info['type'] !== 'chat')                        throw new \RuntimeException("type bukan 'chat': {$info['type']}");
    return "id={$info['id']} title={$info['title']} anggota={$info['participants_count']}";
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 3 — KIRIM & BACA PESAN
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 3 — sendMessage & getHistory');

$msgId1 = null;
step('sendMessage() → grup', function () use ($client, $chatId, &$msgId1, &$groupMsg) {
    $ts     = date('H:i:s');
    $result = $client->sendMessage($chatId, "[Test] Pesan pertama — $ts");
    if ($result['sent'] !== true)      throw new \RuntimeException("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \RuntimeException("'message_id' tidak ada");

    $msgId1    = $result['message_id'];
    $groupMsg[] = $msgId1;
    return "message_id=$msgId1";
});

step('sendMessage() kedua → grup', function () use ($client, $chatId, &$groupMsg) {
    $result = $client->sendMessage($chatId, '[Test] Pesan kedua — akan dihapus nanti');
    if ($result['sent'] !== true) throw new \RuntimeException("'sent' bukan true");
    $groupMsg[] = $result['message_id'];
    return "message_id={$result['message_id']}";
});

step('getHistory() ← grup', function () use ($client, $chatId) {
    $msgs = $client->getHistory($chatId, limit: 5);
    if (!is_array($msgs))   throw new \RuntimeException('bukan array');
    if (count($msgs) === 0) throw new \RuntimeException('history kosong');
    $m = $msgs[0];
    if (!isset($m['id']))   throw new \RuntimeException("'id' tidak ada");
    if (!isset($m['text'])) throw new \RuntimeException("'text' tidak ada");
    return count($msgs) . " pesan, text[0]=" . substr($m['text'], 0, 40);
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 4 — EDIT JUDUL
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 4 — editChatTitle');

step('editChatTitle() — ubah judul grup', function () use ($client, $chatId) {
    $newTitle = '[XnoxsProto Test] Judul BARU — ' . date('H:i:s');
    $result   = $client->editChatTitle($chatId, $newTitle);
    if ($result['updated'] !== true) throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $chatId) throw new \RuntimeException('peer_id salah');
    if ($result['title'] !== $newTitle) throw new \RuntimeException('title tidak sesuai');
    return "title baru={$result['title']}";
});

stepExpectFail(
    'editChatAbout() — basic group tidak didukung (harus throw)',
    fn () => $client->editChatAbout($chatId, 'tentang grup ini'),
    'basic group'
);

// ═══════════════════════════════════════════════════════════════════════════
// FASE 5 — INVITE LINK
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 5 — exportInviteLink');

step('exportInviteLink()', function () use ($client, $chatId) {
    $result = $client->exportInviteLink($chatId);
    if (!array_key_exists('link', $result))        throw new \RuntimeException("'link' tidak ada");
    if (!array_key_exists('revoked', $result))     throw new \RuntimeException("'revoked' tidak ada");
    if ($result['link'] !== null && !str_starts_with($result['link'], 'https://t.me/'))
        throw new \RuntimeException("link tidak valid: {$result['link']}");
    return "link={$result['link']}";
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 6 — DEFAULT PERMISSIONS
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 6 — setDefaultPermissions');

step('setDefaultPermissions() — larang kirim stiker & GIF', function () use ($client, $chatId) {
    $flags  = TelegramClient::BAN_SEND_STICKERS | TelegramClient::BAN_SEND_GIFS;
    $result = $client->setDefaultPermissions($chatId, $flags);
    if ($result['updated'] !== true)        throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $chatId)     throw new \RuntimeException('peer_id salah');
    if ($result['banned_rights'] !== $flags) throw new \RuntimeException('banned_rights tidak cocok');
    return sprintf('flags=0x%x (STICKERS|GIFS dilarang)', $flags);
});

step('setDefaultPermissions() — reset izin (semua diizinkan)', function () use ($client, $chatId) {
    $result = $client->setDefaultPermissions($chatId, 0);
    if ($result['updated'] !== true) throw new \RuntimeException("'updated' bukan true");
    if ($result['banned_rights'] !== 0) throw new \RuntimeException('banned_rights seharusnya 0');
    return 'semua izin dikembalikan (flags=0)';
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 7 — PIN / UNPIN
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 7 — pinMessage & unpinMessage');

step('pinMessage() — pin pesan pertama', function () use ($client, $chatId, $msgId1) {
    if ($msgId1 === null) throw new \RuntimeException('msgId1 null — sendMessage gagal');
    $result = $client->pinMessage($chatId, $msgId1, silent: true);
    if ($result['pinned'] !== true) throw new \RuntimeException("'pinned' bukan true");
    return "pinned message_id=$msgId1";
});

step('unpinMessage() — unpin pesan', function () use ($client, $chatId, $msgId1) {
    if ($msgId1 === null) throw new \RuntimeException('msgId1 null');
    $result = $client->unpinMessage($chatId, $msgId1);
    if ($result['unpinned'] !== true) throw new \RuntimeException("'unpinned' bukan true");
    return "unpinned message_id=$msgId1";
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 8 — ADMIN MANAGEMENT
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 8 — promoteAdmin & demoteAdmin');

step('promoteAdmin() — jadikan @SpamBot admin', function () use ($client, $chatId, $botUserId) {
    $result = $client->promoteAdmin($chatId, '@SpamBot');
    if ($result['promoted'] !== true) throw new \RuntimeException("'promoted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    // Basic group mengembalikan note
    $note = $result['note'] ?? '';
    return "user_id={$result['user_id']} note=" . substr($note, 0, 50);
});

step('demoteAdmin() — cabut admin @SpamBot', function () use ($client, $chatId, $botUserId) {
    $result = $client->demoteAdmin($chatId, '@SpamBot');
    if ($result['demoted'] !== true) throw new \RuntimeException("'demoted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "user_id={$result['user_id']}";
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 9 — KICK & ADD KEMBALI
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 9 — kickUser & addChatUser');

step('kickUser() — keluarkan @SpamBot', function () use ($client, $chatId, $botUserId) {
    $result = $client->kickUser($chatId, '@SpamBot');
    if ($result['kicked'] !== true)    throw new \RuntimeException("'kicked' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "kicked user_id=$botUserId";
});

sleep(1); // beri jeda sebelum addChatUser

step('addChatUser() — tambahkan @SpamBot kembali', function () use ($client, $chatId, $botUserId) {
    $result = $client->addChatUser($chatId, '@SpamBot', fwdLimit: 0);
    if ($result['added'] !== true)         throw new \RuntimeException("'added' bukan true");
    if ($result['chat_id'] !== $chatId)    throw new \RuntimeException('chat_id salah');
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "added user_id=$botUserId ke chat_id=$chatId";
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 10 — BAN (di basic group = kick permanen)
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 10 — banUser (basic group = kick permanen)');

step('banUser() — keluarkan @SpamBot permanen', function () use ($client, $chatId, $botUserId) {
    $result = $client->banUser($chatId, '@SpamBot');
    if ($result['banned'] !== true)        throw new \RuntimeException("'banned' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    $note = $result['note'] ?? 'tidak ada note';
    return "user_id=$botUserId note=" . substr($note, 0, 60);
});

stepExpectFail(
    'unbanUser() — basic group tidak mendukung unban (harus throw)',
    fn () => $client->unbanUser($chatId, '@SpamBot'),
    'Basic group'
);

stepExpectFail(
    'restrictUser() — basic group tidak mendukung restrict parsial (harus throw)',
    fn () => $client->restrictUser($chatId, '@SpamBot', TelegramClient::BAN_SEND_STICKERS),
    'Basic group'
);

// ═══════════════════════════════════════════════════════════════════════════
// FASE 11 — FORWARD & SEARCH
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 11 — forwardMessages & search');

step('forwardMessages() — forward dari grup ke Saved Messages', function () use ($client, $chatId, $msgId1) {
    if ($msgId1 === null) throw new \RuntimeException('msgId1 null');
    $result = $client->forwardMessages('me', [$msgId1], $chatId);
    if ($result['forwarded'] !== true) throw new \RuntimeException("'forwarded' bukan true");
    return "forwarded ids=" . implode(',', $result['ids']);
});

step('search() — cari pesan di grup', function () use ($client, $chatId) {
    $msgs = $client->search($chatId, 'Test', limit: 5);
    if (!is_array($msgs)) throw new \RuntimeException('bukan array');
    return count($msgs) . " pesan ditemukan";
});

// ═══════════════════════════════════════════════════════════════════════════
// FASE 12 — HAPUS PESAN & GRUP
// ═══════════════════════════════════════════════════════════════════════════

section('Fase 12 — Cleanup (deleteMessages & deleteChat)');

step('deleteMessages() — hapus pesan test di grup', function () use ($client, &$groupMsg, $chatId) {
    if (empty($groupMsg)) return 'tidak ada pesan test';
    $ids    = array_unique($groupMsg);
    $result = $client->deleteMessages($ids);
    if ($result['deleted'] !== true) throw new \RuntimeException("'deleted' bukan true");
    $groupMsg = [];
    return "dihapus " . count($ids) . " pesan";
});

step('deleteChat() — hapus grup', function () use ($client, $chatId) {
    $result = $client->deleteChat($chatId);
    if ($result['deleted'] !== true)    throw new \RuntimeException("'deleted' bukan true");
    if ($result['peer_id'] !== $chatId) throw new \RuntimeException('peer_id salah');
    return "chat_id=$chatId dihapus permanen";
});

// ═══════════════════════════════════════════════════════════════════════════
// DISCONNECT
// ═══════════════════════════════════════════════════════════════════════════

section('Disconnect');
step('disconnect()', function () use ($client) {
    $client->disconnect();
    if ($client->isConnected()) throw new \RuntimeException('masih terhubung');
    return 'isConnected=false';
});

// ═══════════════════════════════════════════════════════════════════════════
// RINGKASAN
// ═══════════════════════════════════════════════════════════════════════════

$total = $pass + $fail;
echo "\n\033[1;34m══════════════════════════════════════════════\033[0m\n";
echo "\033[1m RINGKASAN — Basic Group Management Test\033[0m\n";
echo "\033[1;34m══════════════════════════════════════════════\033[0m\n\n";
echo "  Total  : $total\n";
echo "  \033[32mPASS   : $pass\033[0m\n";
echo "  \033[31mFAIL   : $fail\033[0m\n\n";

if ($fail > 0) {
    echo "\033[31m  GAGAL:\033[0m\n";
    foreach ($results as [$status, $name, $detail]) {
        if ($status === 'FAIL') {
            echo "    ✗ $name\n";
            echo "      " . wordwrap($detail, 76, "\n      ", true) . "\n";
        }
    }
    echo "\n";
}

echo ($fail === 0)
    ? "  \033[1;32m✓ Semua test LULUS!\033[0m\n\n"
    : "  \033[31m✗ Ada yang GAGAL.\033[0m\n\n";

exit($fail > 0 ? 1 : 0);
