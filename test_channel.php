<?php

/**
 * XnoxsProto — Channel & Supergroup Management Test
 * ===================================================
 * Menguji semua fitur manajemen channel (broadcast) dan supergroup secara otomatis.
 *
 * SECTION A — Channel Broadcast
 *   1.  createChannel (broadcast)         — buat channel broadcast baru
 *   2.  getFullChannel                    — ambil info lengkap
 *   3.  sendMessage                       — kirim pesan ke channel
 *   4.  getHistory                        — verifikasi pesan masuk
 *   5.  editChatTitle                     — ubah judul
 *   6.  editChatAbout                     — ubah deskripsi
 *   7.  exportInviteLink                  — buat link undangan
 *   8.  toggleSignatures (aktif)          — nyalakan tanda tangan admin
 *   9.  toggleSignatures (nonaktif)       — matikan tanda tangan admin
 *  10.  toggleJoinRequest (private chan)  — SKIP: butuh channel publik
 *  11.  inviteToChannel (bot)             — SKIP: Telegram melarang bot di-invite ke channel
 *  12.  promoteAdmin                      — jadikan @SpamBot admin
 *  13.  demoteAdmin                       — cabut admin @SpamBot
 *  14.  banUser                           — ban @SpamBot
 *  15.  unbanUser                         — unban @SpamBot
 *  16.  getChannelMembers                 — ambil daftar anggota
 *  17.  pinMessage                        — pin pesan
 *  18.  unpinMessage                      — unpin pesan
 *  19.  deleteMessages                    — hapus pesan test
 *  20.  deleteChat (channel)              — hapus channel
 *
 * SECTION B — Supergroup
 *  21.  createChannel (megagroup=true)    — buat supergroup baru
 *  22.  getFullChannel (supergroup)       — ambil info lengkap
 *  23.  sendMessage                       — kirim pesan ke supergroup
 *  24.  getHistory                        — verifikasi pesan masuk
 *  25.  editChatTitle                     — ubah judul
 *  26.  editChatAbout                     — ubah deskripsi
 *  27.  exportInviteLink                  — buat link undangan
 *  28.  setDefaultPermissions (larang)    — larang kirim stiker & GIF
 *  29.  setDefaultPermissions (reset)     — kembalikan semua izin
 *  30.  toggleSlowMode (aktif 10s)        — aktifkan slow mode
 *  31.  toggleSlowMode (nonaktif)         — matikan slow mode
 *  32.  toggleJoinToSend (private sg)     — SKIP: butuh linked discussion channel
 *  33.  toggleJoinRequest (private sg)    — SKIP: butuh supergroup publik (punya username)
 *  34.  inviteToChannel (@SpamBot)        — tambah anggota
 *  35.  promoteAdmin (dengan rank)        — jadikan admin dengan gelar custom
 *  36.  demoteAdmin                       — cabut admin
 *  37.  restrictUser (mute)               — batasi @SpamBot (tidak bisa kirim pesan)
 *  38.  unbanUser (hapus restriksi)       — cabut semua restriksi
 *  39.  kickUser                          — kick @SpamBot
 *  40.  getChannelMembers                 — ambil daftar anggota
 *  41.  forwardMessages                   — forward ke Saved Messages
 *  42.  search                            — cari pesan di supergroup
 *  43.  pinMessage                        — pin pesan
 *  44.  unpinMessage                      — unpin pesan
 *  45.  deleteMessages (supergroup)       — hapus pesan test
 *  46.  deleteChat (supergroup)           — hapus supergroup
 *
 * Catatan Telegram API Constraints (SKIP):
 *   - toggleJoinRequest pada channel/supergroup PRIVATE → butuh username publik dulu
 *   - inviteToChannel bot ke broadcast channel → Telegram blokir (USER_BOT)
 *   - toggleJoinToSend pada supergroup → butuh linked discussion channel (DISCUSSION_CHAT_REQUIRED)
 *
 * Jalankan:
 *   TG_API_ID=123456 TG_API_HASH=abc123 php test_channel.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;
use XnoxsProto\TL\Types\InputPeer;

// ═══════════════════════════════════════════════════════════════════════════
// HELPER
// ═══════════════════════════════════════════════════════════════════════════

$pass    = 0;
$fail    = 0;
$skip    = 0;
$results = [];

function step(string $name, callable $fn): mixed
{
    global $pass, $fail, $results;
    echo "  » $name ... ";
    try {
        $ret    = $fn();
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

function skipStep(string $name, string $reason): void
{
    global $skip, $results;
    $results[] = ['SKIP', $name, $reason];
    echo "  » $name ... \033[33mSKIP\033[0m — $reason\n";
    $skip++;
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

function section(string $t): void
{
    echo "\n\033[1;34m── $t\033[0m\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// BANNER & KONEKSI
// ═══════════════════════════════════════════════════════════════════════════

echo "\n\033[1;36m╔══════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║  Channel & Supergroup Management Test            ║\033[0m\n";
echo "\033[1;36m║  XnoxsProto — PHP MTProto Layer 214              ║\033[0m\n";
echo "\033[1;36m╚══════════════════════════════════════════════════╝\033[0m\n";

$apiId   = (int)  getenv('TG_API_ID');
$apiHash = trim((string) getenv('TG_API_HASH'));

if ($apiId === 0 || $apiHash === '') {
    echo "\n\033[31m  ERROR: TG_API_ID / TG_API_HASH belum di-set.\033[0m\n";
    echo "  Jalankan: TG_API_ID=123456 TG_API_HASH=abc123 php test_channel.php\n\n";
    exit(1);
}

// Auto-detect session file — pakai *.session terbaru
$sessionFiles = glob(__DIR__ . '/*.session') ?: [];
usort($sessionFiles, fn($a, $b) => filemtime($b) - filemtime($a));
$sessionFile = $sessionFiles[0] ?? null;

if ($sessionFile === null) {
    echo "\n\033[31m  ERROR: Tidak ada file .session ditemukan.\033[0m\n";
    echo "  Pastikan sudah login dan file .session ada di direktori ini.\n\n";
    exit(1);
}

echo "  API ID   : $apiId\n";
echo "  API Hash : " . substr($apiHash, 0, 6) . str_repeat('*', max(0, strlen($apiHash) - 6)) . "\n";
echo "  Session  : " . basename($sessionFile) . " (" . number_format(filesize($sessionFile)) . " bytes)\n";

$client = new TelegramClient($apiId, $apiHash, new FileSession($sessionFile));

section('Koneksi');
step('connect()', function () use ($client) {
    $client->connect();
    if (!$client->isConnected()) throw new \RuntimeException('isConnected false');
    $me = $client->getMe();
    return "id={$me['id']} name={$me['first_name']}";
});

// ── Resolve @SpamBot ────────────────────────────────────────────────────────
$botPeer   = null;
$botUserId = null;

section('Resolve bot anggota (@SpamBot)');
step('resolvePeer(@SpamBot)', function () use ($client, &$botPeer, &$botUserId) {
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
//  ██████  SECTION A — CHANNEL BROADCAST
// ═══════════════════════════════════════════════════════════════════════════

echo "\n\033[1;35m══════════════════════════════════════════════════\033[0m\n";
echo "\033[1;35m  SECTION A — CHANNEL BROADCAST\033[0m\n";
echo "\033[1;35m══════════════════════════════════════════════════\033[0m\n";

$chanId    = null;
$chanMsgs  = [];
$chanMsgId = null;

// ── A1: Buat channel ────────────────────────────────────────────────────────

section('A1 — createChannel (broadcast)');
step('createChannel() — buat channel broadcast baru', function () use ($client, &$chanId) {
    $ts     = date('H:i:s');
    $result = $client->createChannel(
        "[XnoxsProto Test] Channel $ts",
        'Test channel otomatis — akan dihapus',
        megagroup: false
    );
    if ($result['created'] !== true)       throw new \RuntimeException("'created' bukan true");
    if (!isset($result['channel_id']))     throw new \RuntimeException("'channel_id' tidak ada");
    if ($result['channel_id'] <= 0)        throw new \RuntimeException('channel_id tidak valid');
    if ($result['megagroup'] !== false)    throw new \RuntimeException("'megagroup' bukan false");

    $chanId = $result['channel_id'];
    return "channel_id=$chanId title={$result['title']}";
});

if ($chanId === null) {
    echo "\n\033[31m  FATAL: createChannel (broadcast) gagal — Section A dilewati.\033[0m\n";
    goto sectionB;
}

// ── A2: Info channel ────────────────────────────────────────────────────────

section('A2 — getFullChannel');
step('getFullChannel() — ambil info lengkap', function () use ($client, $chanId) {
    $info = $client->getFullChannel($chanId);
    if (!array_key_exists('id', $info))     throw new \RuntimeException("'id' tidak ada");
    if (!array_key_exists('about', $info))  throw new \RuntimeException("'about' tidak ada");
    if (!array_key_exists('type', $info))   throw new \RuntimeException("'type' tidak ada");
    if ($info['type'] !== 'channel')        throw new \RuntimeException("type bukan 'channel': {$info['type']}");
    return "id={$info['id']} about=" . substr((string)$info['about'], 0, 30)
         . " anggota={$info['participants_count']}";
});

// ── A3: Kirim & baca pesan ─────────────────────────────────────────────────

section('A3 — sendMessage & getHistory');
step('sendMessage() → channel', function () use ($client, $chanId, &$chanMsgId, &$chanMsgs) {
    $ts     = date('H:i:s');
    $result = $client->sendMessage($chanId, "[Test Channel] Pesan pertama — $ts");
    if ($result['sent'] !== true)      throw new \RuntimeException("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \RuntimeException("'message_id' tidak ada");
    $chanMsgId    = $result['message_id'];
    $chanMsgs[]   = $chanMsgId;
    return "message_id=$chanMsgId";
});

step('sendMessage() kedua → channel', function () use ($client, $chanId, &$chanMsgs) {
    $result = $client->sendMessage($chanId, '[Test Channel] Pesan kedua — cleanup nanti');
    if ($result['sent'] !== true) throw new \RuntimeException("'sent' bukan true");
    $chanMsgs[] = $result['message_id'];
    return "message_id={$result['message_id']}";
});

step('getHistory() ← channel', function () use ($client, $chanId) {
    $msgs = $client->getHistory($chanId, limit: 5);
    if (!is_array($msgs))   throw new \RuntimeException('bukan array');
    if (count($msgs) === 0) throw new \RuntimeException('history kosong');
    $m = $msgs[0];
    if (!isset($m['id']))   throw new \RuntimeException("'id' tidak ada");
    return count($msgs) . " pesan, text[0]=" . substr($m['text'] ?? '', 0, 40);
});

// ── A4: Edit judul & deskripsi ─────────────────────────────────────────────

section('A4 — editChatTitle & editChatAbout');
step('editChatTitle() — ubah judul channel', function () use ($client, $chanId) {
    $newTitle = '[XnoxsProto Test] Channel RENAMED — ' . date('H:i:s');
    $result   = $client->editChatTitle($chanId, $newTitle);
    if ($result['updated'] !== true)    throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $chanId) throw new \RuntimeException('peer_id salah');
    if ($result['title'] !== $newTitle) throw new \RuntimeException('title tidak sesuai');
    return "title baru=" . substr($result['title'], 0, 50);
});

step('editChatAbout() — ubah deskripsi channel', function () use ($client, $chanId) {
    $about  = 'Deskripsi test ' . date('H:i:s');
    $result = $client->editChatAbout($chanId, $about);
    if ($result['updated'] !== true)    throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $chanId) throw new \RuntimeException('peer_id salah');
    if ($result['about'] !== $about)    throw new \RuntimeException('about tidak sesuai');
    return "about=" . substr($result['about'], 0, 40);
});

// ── A5: Invite link ────────────────────────────────────────────────────────

section('A5 — exportInviteLink');
step('exportInviteLink() — channel', function () use ($client, $chanId) {
    $result = $client->exportInviteLink($chanId);
    if (!array_key_exists('link', $result))    throw new \RuntimeException("'link' tidak ada");
    if (!array_key_exists('revoked', $result)) throw new \RuntimeException("'revoked' tidak ada");
    if ($result['link'] !== null && !str_starts_with($result['link'], 'https://t.me/'))
        throw new \RuntimeException("link tidak valid: {$result['link']}");
    return "link={$result['link']}";
});

// ── A6: Signatures ─────────────────────────────────────────────────────────

section('A6 — toggleSignatures (khusus channel broadcast)');
step('toggleSignatures(true) — aktifkan tanda tangan admin', function () use ($client, $chanId) {
    $result = $client->toggleSignatures($chanId, true);
    if ($result['updated'] !== true)              throw new \RuntimeException("'updated' bukan true");
    if ($result['channel_id'] !== $chanId)        throw new \RuntimeException('channel_id salah');
    if ($result['signatures_enabled'] !== true)   throw new \RuntimeException("'signatures_enabled' bukan true");
    return "channel_id=$chanId signatures=ON";
});

step('toggleSignatures(false) — matikan tanda tangan admin', function () use ($client, $chanId) {
    $result = $client->toggleSignatures($chanId, false);
    if ($result['updated'] !== true)              throw new \RuntimeException("'updated' bukan true");
    if ($result['signatures_enabled'] !== false)  throw new \RuntimeException("'signatures_enabled' bukan false");
    return "signatures=OFF";
});

// ── A7: Join request ───────────────────────────────────────────────────────

section('A7 — toggleJoinRequest');
// TELEGRAM API CONSTRAINT: toggleJoinRequest hanya bisa dipakai pada channel yang
// sudah berstatus PUBLIK (punya username). Channel private baru yang belum punya
// username akan menghasilkan error CHAT_ID_INVALID dari server Telegram.
skipStep(
    'toggleJoinRequest — channel privat (SKIP: butuh username publik)',
    'Telegram API constraint: CHAT_ID_INVALID — toggleJoinRequest hanya berlaku pada channel/supergroup publik (sudah punya username)'
);

// ── A8: Member management ──────────────────────────────────────────────────

section('A8 — inviteToChannel');
// TELEGRAM API CONSTRAINT: Bot tidak bisa di-invite ke broadcast channel via
// channels.inviteToChannel — server menolak dengan USER_BOT. Bot hanya bisa
// menjadi admin channel jika ditambahkan lewat menu Telegram atau via promoteAdmin
// (setelah bot sudah ada di channel). Test admin & ban tetap berjalan karena
// @SpamBot bisa langsung di-promote/ban tanpa harus jadi anggota terlebih dulu.
skipStep(
    'inviteToChannel(@SpamBot) ke channel broadcast (SKIP: Telegram blokir USER_BOT)',
    'Telegram API constraint: USER_BOT — bot tidak bisa di-invite ke broadcast channel via inviteToChannel'
);

// ── A9: Admin management ───────────────────────────────────────────────────

section('A9 — promoteAdmin & demoteAdmin (channel)');
step('promoteAdmin(@SpamBot) — jadikan admin channel', function () use ($client, $chanId, $botUserId) {
    $result = $client->promoteAdmin($chanId, '@SpamBot');
    if ($result['promoted'] !== true)      throw new \RuntimeException("'promoted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    $rights = $result['rights'] ?? 0;
    return sprintf("user_id=$botUserId rights=0x%x", $rights);
});

step('demoteAdmin(@SpamBot) — cabut admin channel', function () use ($client, $chanId, $botUserId) {
    $result = $client->demoteAdmin($chanId, '@SpamBot');
    if ($result['demoted'] !== true)       throw new \RuntimeException("'demoted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "user_id=$botUserId dicabut admin";
});

// ── A10: Ban / Unban ───────────────────────────────────────────────────────

section('A10 — banUser & unbanUser (channel)');
step('banUser(@SpamBot) — ban dari channel', function () use ($client, $chanId, $botUserId) {
    $result = $client->banUser($chanId, '@SpamBot');
    if ($result['banned'] !== true)        throw new \RuntimeException("'banned' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "user_id=$botUserId banned until={$result['until']}";
});

step('unbanUser(@SpamBot) — hapus ban dari channel', function () use ($client, $chanId, $botUserId) {
    $result = $client->unbanUser($chanId, '@SpamBot');
    if ($result['unbanned'] !== true)      throw new \RuntimeException("'unbanned' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "user_id=$botUserId unbanned";
});

// ── A11: Daftar anggota ────────────────────────────────────────────────────

section('A11 — getChannelMembers');
step('getChannelMembers() — channel', function () use ($client, $chanId) {
    $members = $client->getChannelMembers($chanId, limit: 10);
    if (!is_array($members))   throw new \RuntimeException('bukan array');
    if (count($members) === 0) throw new \RuntimeException('anggota kosong');
    $m = $members[0];
    if (!isset($m['user_id'])) throw new \RuntimeException("'user_id' tidak ada di member[0]");
    return count($members) . " anggota, user_id[0]={$m['user_id']}";
});

// ── A12: Pin / Unpin ───────────────────────────────────────────────────────

section('A12 — pinMessage & unpinMessage (channel)');
step('pinMessage() — pin pesan pertama', function () use ($client, $chanId, $chanMsgId) {
    if ($chanMsgId === null) throw new \RuntimeException('chanMsgId null — sendMessage gagal');
    $result = $client->pinMessage($chanId, $chanMsgId, silent: true);
    if ($result['pinned'] !== true) throw new \RuntimeException("'pinned' bukan true");
    return "pinned message_id=$chanMsgId";
});

step('unpinMessage() — unpin pesan', function () use ($client, $chanId, $chanMsgId) {
    if ($chanMsgId === null) throw new \RuntimeException('chanMsgId null');
    $result = $client->unpinMessage($chanId, $chanMsgId);
    if ($result['unpinned'] !== true) throw new \RuntimeException("'unpinned' bukan true");
    return "unpinned message_id=$chanMsgId";
});

// ── A13: Hapus pesan & channel ─────────────────────────────────────────────

section('A13 — Cleanup channel (deleteMessages & deleteChat)');
step('deleteMessages() — hapus pesan test di channel', function () use ($client, &$chanMsgs, $chanId) {
    if (empty($chanMsgs)) return 'tidak ada pesan test';
    $ids    = array_unique($chanMsgs);
    $result = $client->deleteMessages($ids, $chanId);
    if ($result['deleted'] !== true) throw new \RuntimeException("'deleted' bukan true");
    $chanMsgs = [];
    return "dihapus " . count($ids) . " pesan";
});

step('deleteChat() — hapus channel', function () use ($client, $chanId) {
    $result = $client->deleteChat($chanId);
    if ($result['deleted'] !== true)    throw new \RuntimeException("'deleted' bukan true");
    if ($result['peer_id'] !== $chanId) throw new \RuntimeException('peer_id salah');
    return "channel_id=$chanId dihapus permanen";
});

// ═══════════════════════════════════════════════════════════════════════════
//  ██████  SECTION B — SUPERGROUP
// ═══════════════════════════════════════════════════════════════════════════

sectionB:

echo "\n\033[1;35m══════════════════════════════════════════════════\033[0m\n";
echo "\033[1;35m  SECTION B — SUPERGROUP\033[0m\n";
echo "\033[1;35m══════════════════════════════════════════════════\033[0m\n";

$sgId    = null;
$sgMsgs  = [];
$sgMsgId = null;

// ── B1: Buat supergroup ────────────────────────────────────────────────────

section('B1 — createChannel (megagroup=true)');
step('createChannel() — buat supergroup baru', function () use ($client, &$sgId) {
    $ts     = date('H:i:s');
    $result = $client->createChannel(
        "[XnoxsProto Test] Supergroup $ts",
        'Test supergroup otomatis — akan dihapus',
        megagroup: true
    );
    if ($result['created'] !== true)      throw new \RuntimeException("'created' bukan true");
    if (!isset($result['channel_id']))    throw new \RuntimeException("'channel_id' tidak ada");
    if ($result['channel_id'] <= 0)       throw new \RuntimeException('channel_id tidak valid');
    if ($result['megagroup'] !== true)    throw new \RuntimeException("'megagroup' bukan true");

    $sgId = $result['channel_id'];
    return "channel_id=$sgId title={$result['title']}";
});

if ($sgId === null) {
    echo "\n\033[31m  FATAL: createChannel (supergroup) gagal — Section B dilewati.\033[0m\n";
    goto cleanup;
}

// ── B2: Info supergroup ────────────────────────────────────────────────────

section('B2 — getFullChannel (supergroup)');
step('getFullChannel() — supergroup', function () use ($client, $sgId) {
    $info = $client->getFullChannel($sgId);
    if (!array_key_exists('id', $info))    throw new \RuntimeException("'id' tidak ada");
    if (!array_key_exists('about', $info)) throw new \RuntimeException("'about' tidak ada");
    if (!array_key_exists('type', $info))  throw new \RuntimeException("'type' tidak ada");
    if ($info['type'] !== 'channel')       throw new \RuntimeException("type bukan 'channel': {$info['type']}");
    return "id={$info['id']} anggota={$info['participants_count']} about=" . substr((string)$info['about'], 0, 30);
});

// ── B3: Kirim & baca pesan ─────────────────────────────────────────────────

section('B3 — sendMessage & getHistory (supergroup)');
step('sendMessage() → supergroup', function () use ($client, $sgId, &$sgMsgId, &$sgMsgs) {
    $ts     = date('H:i:s');
    $result = $client->sendMessage($sgId, "[Test Supergroup] Pesan pertama — $ts");
    if ($result['sent'] !== true)      throw new \RuntimeException("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \RuntimeException("'message_id' tidak ada");
    $sgMsgId  = $result['message_id'];
    $sgMsgs[] = $sgMsgId;
    return "message_id=$sgMsgId";
});

step('sendMessage() kedua → supergroup', function () use ($client, $sgId, &$sgMsgs) {
    $result = $client->sendMessage($sgId, '[Test Supergroup] Pesan kedua — cleanup nanti');
    if ($result['sent'] !== true) throw new \RuntimeException("'sent' bukan true");
    $sgMsgs[] = $result['message_id'];
    return "message_id={$result['message_id']}";
});

step('getHistory() ← supergroup', function () use ($client, $sgId) {
    $msgs = $client->getHistory($sgId, limit: 5);
    if (!is_array($msgs))   throw new \RuntimeException('bukan array');
    if (count($msgs) === 0) throw new \RuntimeException('history kosong');
    $m = $msgs[0];
    if (!isset($m['id']))   throw new \RuntimeException("'id' tidak ada");
    return count($msgs) . " pesan, text[0]=" . substr($m['text'] ?? '', 0, 40);
});

// ── B4: Edit judul & deskripsi ─────────────────────────────────────────────

section('B4 — editChatTitle & editChatAbout (supergroup)');
step('editChatTitle() — ubah judul supergroup', function () use ($client, $sgId) {
    $newTitle = '[XnoxsProto Test] Supergroup RENAMED — ' . date('H:i:s');
    $result   = $client->editChatTitle($sgId, $newTitle);
    if ($result['updated'] !== true)   throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $sgId)  throw new \RuntimeException('peer_id salah');
    if ($result['title'] !== $newTitle) throw new \RuntimeException('title tidak sesuai');
    return "title baru=" . substr($result['title'], 0, 50);
});

step('editChatAbout() — ubah deskripsi supergroup', function () use ($client, $sgId) {
    $about  = 'Deskripsi supergroup test ' . date('H:i:s');
    $result = $client->editChatAbout($sgId, $about);
    if ($result['updated'] !== true)   throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $sgId)  throw new \RuntimeException('peer_id salah');
    if ($result['about'] !== $about)   throw new \RuntimeException('about tidak sesuai');
    return "about=" . substr($result['about'], 0, 40);
});

// ── B5: Invite link ────────────────────────────────────────────────────────

section('B5 — exportInviteLink (supergroup)');
step('exportInviteLink() — supergroup', function () use ($client, $sgId) {
    $result = $client->exportInviteLink($sgId);
    if (!array_key_exists('link', $result))    throw new \RuntimeException("'link' tidak ada");
    if (!array_key_exists('revoked', $result)) throw new \RuntimeException("'revoked' tidak ada");
    if ($result['link'] !== null && !str_starts_with($result['link'], 'https://t.me/'))
        throw new \RuntimeException("link tidak valid: {$result['link']}");
    return "link={$result['link']}";
});

// ── B6: Default permissions ────────────────────────────────────────────────

section('B6 — setDefaultPermissions (supergroup)');
step('setDefaultPermissions() — larang stiker & GIF', function () use ($client, $sgId) {
    $flags  = TelegramClient::BAN_SEND_STICKERS | TelegramClient::BAN_SEND_GIFS;
    $result = $client->setDefaultPermissions($sgId, $flags);
    if ($result['updated'] !== true)         throw new \RuntimeException("'updated' bukan true");
    if ($result['peer_id'] !== $sgId)        throw new \RuntimeException('peer_id salah');
    if ($result['banned_rights'] !== $flags) throw new \RuntimeException('banned_rights tidak cocok');
    return sprintf("flags=0x%x (STICKERS|GIFS dilarang)", $flags);
});

step('setDefaultPermissions() — reset semua izin', function () use ($client, $sgId) {
    $result = $client->setDefaultPermissions($sgId, 0);
    if ($result['updated'] !== true)       throw new \RuntimeException("'updated' bukan true");
    if ($result['banned_rights'] !== 0)    throw new \RuntimeException('banned_rights seharusnya 0');
    return 'semua izin dikembalikan (flags=0)';
});

// ── B7: Slow mode ──────────────────────────────────────────────────────────

section('B7 — toggleSlowMode (khusus supergroup)');
step('toggleSlowMode(10) — aktifkan slow mode 10 detik', function () use ($client, $sgId) {
    $result = $client->toggleSlowMode($sgId, 10);
    if ($result['updated'] !== true)           throw new \RuntimeException("'updated' bukan true");
    if ($result['channel_id'] !== $sgId)       throw new \RuntimeException('channel_id salah');
    if ($result['slow_mode_seconds'] !== 10)   throw new \RuntimeException('slow_mode_seconds bukan 10');
    if ($result['slow_mode_enabled'] !== true) throw new \RuntimeException("'slow_mode_enabled' bukan true");
    return "slow_mode=10s ON";
});

step('toggleSlowMode(0) — matikan slow mode', function () use ($client, $sgId) {
    $result = $client->toggleSlowMode($sgId, 0);
    if ($result['updated'] !== true)            throw new \RuntimeException("'updated' bukan true");
    if ($result['slow_mode_seconds'] !== 0)     throw new \RuntimeException('slow_mode_seconds bukan 0');
    if ($result['slow_mode_enabled'] !== false) throw new \RuntimeException("'slow_mode_enabled' bukan false");
    return "slow_mode=OFF";
});

// ── B8: Join to send ───────────────────────────────────────────────────────

section('B8 — toggleJoinToSend (supergroup)');
// TELEGRAM API CONSTRAINT: toggleJoinToSend hanya bisa diaktifkan pada supergroup
// yang sudah di-link ke sebuah broadcast channel sebagai discussion group.
// Supergroup standalone (belum punya linked channel) akan ditolak dengan
// DISCUSSION_CHAT_REQUIRED oleh server Telegram.
skipStep(
    'toggleJoinToSend — supergroup standalone (SKIP: butuh linked discussion channel)',
    'Telegram API constraint: DISCUSSION_CHAT_REQUIRED — toggleJoinToSend hanya berlaku pada supergroup yang sudah di-link ke channel broadcast'
);

// ── B9: Join request ───────────────────────────────────────────────────────

section('B9 — toggleJoinRequest (supergroup)');
// TELEGRAM API CONSTRAINT: toggleJoinRequest hanya bisa dipakai pada supergroup
// yang sudah berstatus PUBLIK (punya username/link publik). Supergroup private
// yang belum punya username ditolak dengan CHAT_PUBLIC_REQUIRED.
skipStep(
    'toggleJoinRequest — supergroup privat (SKIP: butuh username publik)',
    'Telegram API constraint: CHAT_PUBLIC_REQUIRED — toggleJoinRequest hanya berlaku pada supergroup publik (sudah punya username)'
);

// ── B10: Tambah anggota ────────────────────────────────────────────────────

section('B10 — inviteToChannel (supergroup)');
step('inviteToChannel(@SpamBot) — tambah ke supergroup', function () use ($client, $sgId, $botUserId) {
    $result = $client->inviteToChannel($sgId, '@SpamBot');
    if ($result['invited'] !== true)       throw new \RuntimeException("'invited' bukan true");
    if ($result['channel_id'] !== $sgId)   throw new \RuntimeException('channel_id salah');
    if (!in_array($botUserId, $result['user_ids'], true))
        throw new \RuntimeException('user_ids tidak mengandung botUserId');
    return "invited user_id=$botUserId ke supergroup_id=$sgId";
});

// ── B11: Admin (dengan rank) ───────────────────────────────────────────────

section('B11 — promoteAdmin (dengan rank) & demoteAdmin (supergroup)');
step('promoteAdmin(@SpamBot, rank="Moderator") — jadikan admin dengan gelar', function () use ($client, $sgId, $botUserId) {
    $result = $client->promoteAdmin($sgId, '@SpamBot', rank: 'Moderator');
    if ($result['promoted'] !== true)      throw new \RuntimeException("'promoted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    if ($result['rank'] !== 'Moderator')   throw new \RuntimeException("rank bukan 'Moderator': {$result['rank']}");
    return sprintf("user_id=$botUserId rank={$result['rank']} rights=0x%x", $result['rights']);
});

step('demoteAdmin(@SpamBot) — cabut admin supergroup', function () use ($client, $sgId, $botUserId) {
    $result = $client->demoteAdmin($sgId, '@SpamBot');
    if ($result['demoted'] !== true)       throw new \RuntimeException("'demoted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "user_id=$botUserId admin dicabut";
});

// ── B12: Restrict / Unban ──────────────────────────────────────────────────

section('B12 — restrictUser & unbanUser (supergroup)');
step('restrictUser(@SpamBot) — mute (larang kirim pesan)', function () use ($client, $sgId, $botUserId) {
    $flags  = TelegramClient::BAN_SEND_MESSAGES;
    $result = $client->restrictUser($sgId, '@SpamBot', $flags);
    if ($result['restricted'] !== true)    throw new \RuntimeException("'restricted' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    if ($result['flags'] !== $flags)       throw new \RuntimeException(sprintf("flags salah: expect 0x%x got 0x%x", $flags, $result['flags']));
    return sprintf("user_id=$botUserId flags=0x%x (muted) until={$result['until']}", $result['flags']);
});

step('unbanUser(@SpamBot) — hapus semua restriksi', function () use ($client, $sgId, $botUserId) {
    $result = $client->unbanUser($sgId, '@SpamBot');
    if ($result['unbanned'] !== true)      throw new \RuntimeException("'unbanned' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "user_id=$botUserId restriksi dihapus";
});

// ── B13: Kick ──────────────────────────────────────────────────────────────

section('B13 — kickUser (supergroup)');
step('kickUser(@SpamBot) — kick dari supergroup', function () use ($client, $sgId, $botUserId) {
    $result = $client->kickUser($sgId, '@SpamBot');
    if ($result['kicked'] !== true)        throw new \RuntimeException("'kicked' bukan true");
    if ($result['user_id'] !== $botUserId) throw new \RuntimeException('user_id salah');
    return "kicked user_id=$botUserId";
});

// ── B14: Daftar anggota ────────────────────────────────────────────────────

section('B14 — getChannelMembers (supergroup)');
step('getChannelMembers() — supergroup', function () use ($client, $sgId) {
    $members = $client->getChannelMembers($sgId, limit: 10);
    if (!is_array($members))   throw new \RuntimeException('bukan array');
    if (count($members) === 0) throw new \RuntimeException('anggota kosong');
    $m = $members[0];
    if (!isset($m['user_id'])) throw new \RuntimeException("'user_id' tidak ada di member[0]");
    return count($members) . " anggota, user_id[0]={$m['user_id']}";
});

// ── B15: Forward & Search ──────────────────────────────────────────────────

section('B15 — forwardMessages & search (supergroup)');
step('forwardMessages() — forward dari supergroup ke Saved Messages', function () use ($client, $sgId, $sgMsgId) {
    if ($sgMsgId === null) throw new \RuntimeException('sgMsgId null — sendMessage gagal');
    $result = $client->forwardMessages('me', [$sgMsgId], $sgId);
    if ($result['forwarded'] !== true) throw new \RuntimeException("'forwarded' bukan true");
    return "forwarded ids=" . implode(',', $result['ids']);
});

step('search() — cari pesan di supergroup', function () use ($client, $sgId) {
    $msgs = $client->search($sgId, 'Test', limit: 5);
    if (!is_array($msgs)) throw new \RuntimeException('bukan array');
    return count($msgs) . " pesan ditemukan";
});

// ── B16: Pin / Unpin ───────────────────────────────────────────────────────

section('B16 — pinMessage & unpinMessage (supergroup)');
step('pinMessage() — pin pesan pertama', function () use ($client, $sgId, $sgMsgId) {
    if ($sgMsgId === null) throw new \RuntimeException('sgMsgId null — sendMessage gagal');
    $result = $client->pinMessage($sgId, $sgMsgId, silent: true);
    if ($result['pinned'] !== true) throw new \RuntimeException("'pinned' bukan true");
    return "pinned message_id=$sgMsgId";
});

step('unpinMessage() — unpin pesan', function () use ($client, $sgId, $sgMsgId) {
    if ($sgMsgId === null) throw new \RuntimeException('sgMsgId null');
    $result = $client->unpinMessage($sgId, $sgMsgId);
    if ($result['unpinned'] !== true) throw new \RuntimeException("'unpinned' bukan true");
    return "unpinned message_id=$sgMsgId";
});

// ── B17: Hapus pesan & supergroup ─────────────────────────────────────────

section('B17 — Cleanup supergroup (deleteMessages & deleteChat)');
step('deleteMessages() — hapus pesan test di supergroup', function () use ($client, &$sgMsgs, $sgId) {
    if (empty($sgMsgs)) return 'tidak ada pesan test';
    $ids    = array_unique($sgMsgs);
    $result = $client->deleteMessages($ids, $sgId);
    if ($result['deleted'] !== true) throw new \RuntimeException("'deleted' bukan true");
    $sgMsgs = [];
    return "dihapus " . count($ids) . " pesan";
});

step('deleteChat() — hapus supergroup', function () use ($client, $sgId) {
    $result = $client->deleteChat($sgId);
    if ($result['deleted'] !== true)   throw new \RuntimeException("'deleted' bukan true");
    if ($result['peer_id'] !== $sgId)  throw new \RuntimeException('peer_id salah');
    return "supergroup_id=$sgId dihapus permanen";
});

// ═══════════════════════════════════════════════════════════════════════════
// DISCONNECT
// ═══════════════════════════════════════════════════════════════════════════

cleanup:

section('Disconnect');
step('disconnect()', function () use ($client) {
    $client->disconnect();
    if ($client->isConnected()) throw new \RuntimeException('masih terhubung');
    return 'isConnected=false';
});

// ═══════════════════════════════════════════════════════════════════════════
// RINGKASAN
// ═══════════════════════════════════════════════════════════════════════════

$total = $pass + $fail + $skip;
echo "\n\033[1;34m══════════════════════════════════════════════════\033[0m\n";
echo "\033[1m RINGKASAN — Channel & Supergroup Management Test\033[0m\n";
echo "\033[1;34m══════════════════════════════════════════════════\033[0m\n\n";
echo "  Total  : $total test\n";
echo "  \033[32mPASS   : $pass\033[0m\n";
echo "  \033[31mFAIL   : $fail\033[0m\n";
echo "  \033[33mSKIP   : $skip\033[0m\n\n";

if ($skip > 0) {
    echo "\033[33m  SKIP (Telegram API Constraints):\033[0m\n";
    foreach ($results as [$status, $name, $detail]) {
        if ($status === 'SKIP') {
            echo "    ⊘ $name\n";
            echo "      " . wordwrap($detail, 76, "\n      ", true) . "\n";
        }
    }
    echo "\n";
}

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

if ($fail === 0 && $skip === 0) {
    echo "  \033[1;32m✓ Semua test LULUS!\033[0m\n\n";
} elseif ($fail === 0) {
    echo "  \033[32m✓ Semua test yang dijalankan LULUS! (skip karena Telegram API constraints)\033[0m\n\n";
} else {
    echo "  \033[31m✗ Ada yang GAGAL — periksa output di atas.\033[0m\n\n";
}

exit($fail > 0 ? 1 : 0);
