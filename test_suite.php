<?php

/**
 * XnoxsProto — Automated Test Suite
 * ===================================
 * Menguji semua fitur yang terdokumentasi tanpa input manual.
 *
 * Kredensial dibaca dari environment variable:
 *   TG_API_ID    — API ID dari my.telegram.org/apps
 *   TG_API_HASH  — API Hash dari my.telegram.org/apps
 *
 * Session file dideteksi otomatis dari direktori aktif (*.session).
 * Jika ada lebih dari satu, file yang paling baru dipakai.
 *
 * Semua pesan test dikirim ke Saved Messages ('me') — aman, tidak ganggu siapapun.
 * Pesan test dibersihkan otomatis di akhir sesi.
 *
 * Jalankan:
 *   TG_API_ID=123456 TG_API_HASH=abc123 php test_suite.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Client\FileDownloader;
use XnoxsProto\Sessions\FileSession;
use XnoxsProto\Sessions\MemorySession;
use XnoxsProto\Sessions\AbstractSession;
use XnoxsProto\TL\Types\InputPeer;
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;
use XnoxsProto\Events\NewMessage as NewMessageFilter;
use XnoxsProto\Events\RawUpdateEvent;

// ═══════════════════════════════════════════════════════════════════════════
// STATE GLOBAL
// ═══════════════════════════════════════════════════════════════════════════

$results    = [];
$pass       = 0;
$fail       = 0;
$skip       = 0;
$sentMsgIds = [];

// ═══════════════════════════════════════════════════════════════════════════
// HELPER FUNGSI
// ═══════════════════════════════════════════════════════════════════════════

function test(string $name, callable $fn): void
{
    global $results, $pass, $fail;
    echo "  » $name ... ";
    try {
        $detail    = $fn();
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => $detail ?? ''];
        echo "\033[32mPASS\033[0m" . ($detail ? " — $detail" : '') . "\n";
        $pass++;
    } catch (\Throwable $e) {
        $msg       = $e->getMessage();
        $results[] = ['name' => $name, 'status' => 'FAIL', 'detail' => $msg];
        echo "\033[31mFAIL\033[0m — $msg\n";
        $fail++;
    }
}

function skipTest(string $name, string $reason): void
{
    global $results, $skip;
    $results[] = ['name' => $name, 'status' => 'SKIP', 'detail' => $reason];
    echo "  » $name ... \033[33mSKIP\033[0m — $reason\n";
    $skip++;
}

function section(string $title): void
{
    echo "\n\033[1;34m── $title\033[0m\n";
}

function printSummary(): void
{
    global $results, $pass, $fail, $skip;
    $total = $pass + $fail + $skip;

    echo "\n";
    echo "\033[1;34m══════════════════════════════════════════════\033[0m\n";
    echo "\033[1m RINGKASAN HASIL TEST\033[0m\n";
    echo "\033[1;34m══════════════════════════════════════════════\033[0m\n\n";
    echo "  Total  : $total test\n";
    echo "  \033[32mPASS   : $pass\033[0m\n";
    echo "  \033[31mFAIL   : $fail\033[0m\n";
    echo "  \033[33mSKIP   : $skip\033[0m\n\n";

    if ($fail > 0) {
        echo "\033[31m  GAGAL:\033[0m\n";
        foreach ($results as $r) {
            if ($r['status'] === 'FAIL') {
                echo "    ✗ {$r['name']}\n";
                $wrapped = wordwrap($r['detail'], 76, "\n      ", true);
                echo "      $wrapped\n";
            }
        }
        echo "\n";
    }

    if ($fail === 0 && $skip === 0) {
        echo "  \033[1;32m✓ Semua test LULUS!\033[0m\n";
    } elseif ($fail === 0) {
        echo "  \033[32m✓ Semua test yang dijalankan LULUS!\033[0m\n";
    } else {
        echo "  \033[31m✗ Ada test yang GAGAL — periksa output di atas.\033[0m\n";
    }
    echo "\n";
}

/** Buat file PNG 50×50 pixel menggunakan GD (wajib valid untuk upload foto ke Telegram). */
function createTestPng(): string
{
    $path = sys_get_temp_dir() . '/xnoxs_test_' . uniqid() . '.png';

    if (extension_loaded('gd')) {
        $img = imagecreatetruecolor(50, 50);
        $bg  = imagecolorallocate($img, 66, 133, 244);   // biru Google
        $fg  = imagecolorallocate($img, 255, 255, 255);  // putih
        imagefill($img, 0, 0, $bg);
        imagestring($img, 3, 8, 18, 'XNXS', $fg);
        imagepng($img, $path);
        imagedestroy($img);
    } else {
        // Fallback: raw PNG 8×8 biru (valid untuk upload sebagai dokumen)
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAFElEQVQI12Nk' .
            'YGBg+M9ABQAAAP//AwAI/AL+hc2rNAAAAABJRU5ErkJggg=='
        );
        file_put_contents($path, $pngData);
    }
    return $path;
}

/** Buat file teks kecil untuk test dokumen. */
function createTestTxt(): string
{
    $content = "XnoxsProto Automated Test\n"
             . "Generated : " . date('Y-m-d H:i:s') . "\n"
             . "Library   : PHP MTProto Layer 214\n";
    $path = sys_get_temp_dir() . '/xnoxs_test_' . uniqid() . '.txt';
    file_put_contents($path, $content);
    return $path;
}

/**
 * Buat file OGG Opus minimal yang valid (satu frame hening ~120 bytes).
 * Cukup untuk upload sebagai audio/voice di Telegram.
 */
function createTestOgg(): string
{
    // OGG page: OpusHead + OpusTags + single silent audio frame
    $oggData = base64_decode(
        'T2dnUwACAAAAAAAAAAAAU5wTHwAAAABqr3UKHgpPcHVzSGVhZAECOAKA3gAAAA' .
        'AAAFQAAAAnT2dnUwAAAAAAAAAAAAAU5wTHAQAAAC9h0ggTDk9wdXNUYWdzDAAAA' .
        'AJYbm94c1Byb3RvAAAAAE9nZ1MABAAAAAAAAABQ5wTHAgAAAOT9DQQBP4C/'
    );
    $path = sys_get_temp_dir() . '/xnoxs_test_' . uniqid() . '.ogg';
    file_put_contents($path, $oggData);
    return $path;
}

// ═══════════════════════════════════════════════════════════════════════════
// BANNER
// ═══════════════════════════════════════════════════════════════════════════

echo "\n\033[1;36m╔══════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║    XnoxsProto  Automated Test Suite         ║\033[0m\n";
echo "\033[1;36m║    PHP " . PHP_VERSION . " — MTProto Layer 214        ║\033[0m\n";
echo "\033[1;36m╚══════════════════════════════════════════════╝\033[0m\n";

// ═══════════════════════════════════════════════════════════════════════════
// SETUP KREDENSIAL
// ═══════════════════════════════════════════════════════════════════════════

section('Setup Kredensial');

$apiId   = (int)  getenv('TG_API_ID');
$apiHash = trim((string) getenv('TG_API_HASH'));

if ($apiId === 0 || $apiHash === '') {
    echo "  \033[33m⚠  TG_API_ID / TG_API_HASH tidak ditemukan di environment.\033[0m\n";
    echo "  \033[33m   Jalankan: TG_API_ID=123456 TG_API_HASH=abc123 php test_suite.php\033[0m\n";
    echo "  \033[33m   Online tests akan di-skip.\033[0m\n";
    $onlineEnabled = false;
} else {
    echo "  API ID   : $apiId\n";
    echo "  API Hash : " . substr($apiHash, 0, 6) . str_repeat('*', max(0, strlen($apiHash) - 6)) . "\n";
    $onlineEnabled = true;
}

// Auto-detect session file — pakai *.session terbaru di direktori aktif
$sessionFile  = null;
$sessionFiles = glob(__DIR__ . '/*.session') ?: [];
if ($sessionFiles) {
    usort($sessionFiles, fn($a, $b) => filemtime($b) - filemtime($a));
    $sessionFile = $sessionFiles[0];
    echo "  Session  : " . basename($sessionFile) . " (" . number_format(filesize($sessionFile)) . " bytes)\n";
} else {
    echo "  \033[33m  Tidak ada file .session ditemukan di direktori ini.\033[0m\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// PHASE 1 — UNIT TESTS (OFFLINE)
// ═══════════════════════════════════════════════════════════════════════════

section('Phase 1: Unit Tests (Offline — tanpa koneksi internet)');

// ── T01 ───────────────────────────────────────────────────────────────────
test('FileSession — save / load / delete', function () {
    $tmp = sys_get_temp_dir() . '/xnoxs_t01_' . uniqid() . '.session';

    $s = new FileSession($tmp);
    $s->setDC(2, '149.154.167.51', 443);
    $s->setAuthKey(str_repeat("\xAB", 256));
    $s->setAuthorized(true, 99887766);
    $s->setLayer(214);
    $s->save();

    // Reload dari disk
    $s2 = new FileSession($tmp);
    $dc = $s2->getDC();
    if ($dc === null)                              throw new \AssertionError('getDC() null');
    if ($dc['dc_id'] !== 2)                        throw new \AssertionError("dc_id={$dc['dc_id']}, expect 2");
    if ($dc['server_address'] !== '149.154.167.51') throw new \AssertionError('server_address salah');
    if ($dc['port'] !== 443)                       throw new \AssertionError('port salah');
    if ($s2->getAuthKey() !== str_repeat("\xAB", 256)) throw new \AssertionError('authKey mismatch');
    if (!$s2->isUserAuthorized())                  throw new \AssertionError('isUserAuthorized false');
    if ($s2->getUserId() !== 99887766)             throw new \AssertionError('userId salah');
    if ($s2->getLayer() !== 214)                   throw new \AssertionError('layer salah');

    $s2->delete();
    if (file_exists($tmp)) throw new \AssertionError('file masih ada setelah delete');

    return 'DC, authKey, userId, layer — OK';
});

// ── T02 ───────────────────────────────────────────────────────────────────
test('FileSession — magic header binary (XNXS)', function () {
    $tmp = sys_get_temp_dir() . '/xnoxs_t02_' . uniqid() . '.session';

    $s = new FileSession($tmp);
    $s->setDC(1, '149.154.175.53', 443);
    $s->setAuthKey(str_repeat("\xCC", 256));
    $s->save();

    $raw = file_get_contents($tmp);
    if (substr($raw, 0, 4) !== 'XNXS')  throw new \AssertionError('magic header bukan XNXS');
    if (ord($raw[4]) !== 0x01)           throw new \AssertionError('version bukan 0x01');
    if (ord($raw[5]) !== 0x01)           throw new \AssertionError('flags bukan 0x01 (encrypted)');
    if (strlen($raw) < 58)               throw new \AssertionError('file terlalu kecil (<58 bytes)');

    unlink($tmp);
    return sprintf('magic=XNXS, version=0x01, flags=0x01, size=%d bytes', strlen($raw));
});

// ── T03 ───────────────────────────────────────────────────────────────────
test('FileSession — auto-migrasi format JSON lama → binary baru', function () {
    $tmp = sys_get_temp_dir() . '/xnoxs_t03_' . uniqid() . '.session';

    // Tulis session format JSON lama (sebelum versi binary)
    file_put_contents($tmp, json_encode([
        'dc_id'          => 5,
        'server_address' => '91.108.56.130',
        'port'           => 443,
        'auth_key'       => base64_encode(str_repeat("\xDD", 256)),
        'authorized'     => true,
        'user_id'        => 12345678,
        'layer'          => 148,
    ]));

    $s = new FileSession($tmp);   // load() harus auto-migrate
    $dc = $s->getDC();
    if ($dc === null || $dc['dc_id'] !== 5)    throw new \AssertionError('dc_id salah setelah migrasi');
    if (!$s->isUserAuthorized())               throw new \AssertionError('authorized salah');
    if ($s->getUserId() !== 12345678)          throw new \AssertionError('userId salah');

    // File sekarang harus sudah dikonversi ke binary
    $raw = file_get_contents($tmp);
    if (substr($raw, 0, 4) !== 'XNXS') throw new \AssertionError('file tidak termigrasi ke binary');

    unlink($tmp);
    return 'JSON → binary migrasi sukses';
});

// ── T04 ───────────────────────────────────────────────────────────────────
test('MemorySession — operasi dasar', function () {
    $s = new MemorySession();
    if ($s->getDC() !== null)          throw new \AssertionError('getDC() harus null di awal');
    if ($s->getAuthKey() !== null)     throw new \AssertionError('getAuthKey() harus null');
    if ($s->isUserAuthorized())        throw new \AssertionError('isUserAuthorized() harus false');
    if ($s->getUserId() !== null)      throw new \AssertionError('getUserId() harus null');
    if ($s->getLayer() !== null)       throw new \AssertionError('getLayer() harus null');

    $s->setDC(3, '149.154.175.100', 443);
    $s->setAuthKey(str_repeat("\xEE", 256));
    $s->setAuthorized(true, 55443322);
    $s->setLayer(214);

    $dc = $s->getDC();
    if ($dc['dc_id'] !== 3)                            throw new \AssertionError('dc_id mismatch');
    if (strlen((string) $s->getAuthKey()) !== 256)     throw new \AssertionError('authKey length mismatch');
    if (!$s->isUserAuthorized())                       throw new \AssertionError('authorized mismatch');
    if ($s->getUserId() !== 55443322)                  throw new \AssertionError('userId mismatch');
    if ($s->getLayer() !== 214)                        throw new \AssertionError('layer mismatch');

    $s->delete();
    if ($s->getAuthKey() !== null)  throw new \AssertionError('authKey harus null setelah delete');
    if ($s->getUserId() !== null)   throw new \AssertionError('userId harus null setelah delete');
    if ($s->getLayer() !== null)    throw new \AssertionError('layer harus null setelah delete');

    return 'set/get/delete semua field OK';
});

// ── T05 ───────────────────────────────────────────────────────────────────
test('FileSession — entity cache (processEntities & lookup)', function () {
    $tmp = sys_get_temp_dir() . '/xnoxs_t05_' . uniqid() . '.session';
    $s   = new FileSession($tmp);

    $s->processEntities([
        ['id' => 123, 'type' => 'user',    'username' => 'testuser', 'phone' => '6281234567890'],
        ['id' => 456, 'type' => 'channel', 'username' => 'testchan', 'access_hash' => 999],
    ]);

    // Lookup by username
    $r = $s->getEntityRowsByUsername('testuser');
    if ($r === null)         throw new \AssertionError('tidak ditemukan by username');
    if ($r['id'] !== 123)    throw new \AssertionError('id salah by username');

    // Lookup by phone
    $r = $s->getEntityRowsByPhone('6281234567890');
    if ($r === null)         throw new \AssertionError('tidak ditemukan by phone');

    // Lookup by ID
    $r = $s->getEntityRowsById(456);
    if ($r === null)         throw new \AssertionError('tidak ditemukan by id');
    if ($r['type'] !== 'channel') throw new \AssertionError("tipe salah: {$r['type']}");

    // Non-existent
    if ($s->getEntityRowsByUsername('nonexistent') !== null) throw new \AssertionError('harusnya null');
    if ($s->getEntityRowsById(999) !== null)                 throw new \AssertionError('harusnya null');

    unlink($tmp);
    return 'lookup by username / phone / id OK';
});

// ── T06 ───────────────────────────────────────────────────────────────────
test('InputPeer — semua factory method', function () {
    $user    = InputPeer::user(111, 999);
    $chat    = InputPeer::chat(222);
    $channel = InputPeer::channel(333, 888);
    $self    = InputPeer::self();
    $empty   = InputPeer::empty();

    // getType() returns int constant
    if ($user->getType()    !== InputPeer::USER)    throw new \AssertionError('user type salah');
    if ($user->getId()      !== 111)                throw new \AssertionError('user id salah');
    if ($user->getAccessHash() !== 999)             throw new \AssertionError('user accessHash salah');

    if ($chat->getType()    !== InputPeer::CHAT)    throw new \AssertionError('chat type salah');
    if ($chat->getId()      !== 222)                throw new \AssertionError('chat id salah');

    if ($channel->getType() !== InputPeer::CHANNEL) throw new \AssertionError('channel type salah');
    if ($channel->getId()   !== 333)                throw new \AssertionError('channel id salah');
    if ($channel->getAccessHash() !== 888)          throw new \AssertionError('channel accessHash salah');

    if ($self->getType()    !== InputPeer::SELF)    throw new \AssertionError('self type salah');
    if ($empty->getType()   !== InputPeer::EMPTY_)  throw new \AssertionError('empty type salah');

    return 'user / chat / channel / self / empty OK';
});

// ── T07 ───────────────────────────────────────────────────────────────────
test('Konstanta ADMIN_* — nilai & kelengkapan', function () {
    $expected = [
        'ADMIN_CHANGE_INFO'     => 0x00001,
        'ADMIN_POST_MESSAGES'   => 0x00002,
        'ADMIN_EDIT_MESSAGES'   => 0x00004,
        'ADMIN_DELETE_MESSAGES' => 0x00008,
        'ADMIN_BAN_USERS'       => 0x00010,
        'ADMIN_INVITE_USERS'    => 0x00020,
        'ADMIN_PIN_MESSAGES'    => 0x00080,
        'ADMIN_ADD_ADMINS'      => 0x00200,
        'ADMIN_ANONYMOUS'       => 0x00400,
        'ADMIN_MANAGE_CALL'     => 0x00800,
        'ADMIN_OTHER'           => 0x01000,
        'ADMIN_MANAGE_TOPICS'   => 0x02000,
    ];
    $rc = new ReflectionClass(TelegramClient::class);
    foreach ($expected as $name => $val) {
        $actual = $rc->getConstant($name);
        if ($actual === false)    throw new \AssertionError("Konstanta $name tidak ada");
        if ($actual !== $val)     throw new \AssertionError("$name: expect 0x" . dechex($val) . " got 0x" . dechex($actual));
    }
    return count($expected) . ' konstanta ADMIN_* valid';
});

// ── T08 ───────────────────────────────────────────────────────────────────
test('Konstanta BAN_* — nilai & kelengkapan', function () {
    $expected = [
        'BAN_VIEW_MESSAGES' => 0x000001,
        'BAN_SEND_MESSAGES' => 0x000002,
        'BAN_SEND_MEDIA'    => 0x000004,
        'BAN_SEND_STICKERS' => 0x000008,
        'BAN_SEND_GIFS'     => 0x000010,
        'BAN_SEND_GAMES'    => 0x000020,
        'BAN_SEND_INLINE'   => 0x000040,
        'BAN_EMBED_LINKS'   => 0x000080,
        'BAN_SEND_POLLS'    => 0x000100,
        'BAN_CHANGE_INFO'   => 0x000400,
        'BAN_INVITE_USERS'  => 0x008000,
        'BAN_PIN_MESSAGES'  => 0x020000,
        'BAN_SEND_PHOTOS'   => 0x080000,
        'BAN_SEND_VIDEOS'   => 0x100000,
        'BAN_SEND_AUDIOS'   => 0x400000,
        'BAN_SEND_DOCS'     => 0x800000,
    ];
    $rc = new ReflectionClass(TelegramClient::class);
    foreach ($expected as $name => $val) {
        $actual = $rc->getConstant($name);
        if ($actual === false)  throw new \AssertionError("Konstanta $name tidak ada");
        if ($actual !== $val)   throw new \AssertionError("$name: expect 0x" . dechex($val) . " got 0x" . dechex($actual));
    }
    return count($expected) . ' konstanta BAN_* valid';
});

// ── T09 ───────────────────────────────────────────────────────────────────
test('TelegramClient — mode konstruktor (string / null / AbstractSession)', function () {
    // Mode null → MemorySession
    $c1 = new TelegramClient(1, 'hash', null);
    if (!($c1->getSession() instanceof MemorySession)) throw new \AssertionError('null → bukan MemorySession');

    // Mode string → FileSession (dengan ekstensi .session)
    $tmp = sys_get_temp_dir() . '/xnoxs_ctor_' . uniqid();
    $c2  = new TelegramClient(1, 'hash', $tmp);
    if (!($c2->getSession() instanceof FileSession)) throw new \AssertionError('string → bukan FileSession');
    $sessFile = $tmp . '.session';
    // Cleanup
    if (file_exists($sessFile)) unlink($sessFile);

    // Mode AbstractSession eksplisit
    $mem = new MemorySession();
    $c3  = new TelegramClient(1, 'hash', $mem);
    if ($c3->getSession() !== $mem) throw new \AssertionError('AbstractSession eksplisit tidak cocok');

    return 'null/string/AbstractSession OK';
});

// ── T10 ───────────────────────────────────────────────────────────────────
test('TelegramClient — getAuth / getMessages / getAccount / getDownloader', function () {
    $c = new TelegramClient(1, 'hash', null);
    if ($c->getAuth()       === null) throw new \AssertionError('getAuth() null');
    if ($c->getMessages()   === null) throw new \AssertionError('getMessages() null');
    if ($c->getAccount()    === null) throw new \AssertionError('getAccount() null');
    if ($c->getDownloader() === null) throw new \AssertionError('getDownloader() null');
    if (!($c->getDownloader() instanceof FileDownloader)) throw new \AssertionError('getDownloader() bukan FileDownloader');
    return 'semua sub-modul terinisialisasi';
});

// ── T11 ───────────────────────────────────────────────────────────────────
test('TelegramClient — setProxy / clearProxy', function () {
    $c = new TelegramClient(1, 'hash', null);
    $c->setProxy('127.0.0.1', 9050);
    $c->setProxy('proxy.example.com', 1080, 'user', 'pass');
    $c->clearProxy();
    return 'setProxy / clearProxy tanpa exception';
});

// ── T12 ───────────────────────────────────────────────────────────────────
test('AccountGetPrivacyRequest — konstanta KEY_*', function () {
    $expected = [
        'KEY_STATUS_TIMESTAMP' => 0x4f96cb18,
        'KEY_CHAT_INVITE'      => 0xbdfb0426,
        'KEY_PHONE_CALL'       => 0xfabadc5f,
        'KEY_PHONE_P2P'        => 0xdb9e70d2,
        'KEY_FORWARDS'         => 0xa4dd4c08,
        'KEY_PROFILE_PHOTO'    => 0x5719bacc,
        'KEY_PHONE_NUMBER'     => 0x0352dafa,
        'KEY_ADDED_BY_PHONE'   => 0xd1219bdd,
        'KEY_VOICE_MESSAGES'   => 0xaee69d68,
        'KEY_ABOUT'            => 0x3823cc40,
        'KEY_BIRTHDAY'         => 0xd65a11cc,
    ];
    $rc = new ReflectionClass(AccountGetPrivacyRequest::class);
    foreach ($expected as $name => $val) {
        $actual = $rc->getConstant($name);
        if ($actual === false) throw new \AssertionError("$name tidak ada");
        if ($actual !== $val)  throw new \AssertionError("$name: expect 0x" . dechex($val) . " got 0x" . dechex((int)$actual));
    }
    return count($expected) . ' konstanta KEY_* valid';
});

// ═══════════════════════════════════════════════════════════════════════════
// PHASE 2 — INTEGRATION TESTS (ONLINE)
// ═══════════════════════════════════════════════════════════════════════════

section('Phase 2: Integration Tests (Online — koneksi ke Telegram)');

$onlineList = [
    'Koneksi — connect() / isConnected()',
    'getLayer() ≥ 214',
    'getMe()',
    'getAuth().isAuthorized()',
    'sendMessage() → Saved Messages',
    'getHistory() ← Saved Messages',
    'getDialogs()',
    'getContacts()',
    'editMessage()',
    'sendFile() — auto-detect PNG sebagai foto',
    'sendFile() — forceDocument=true',
    'sendPhoto()',
    'sendDocument()',
    'sendAudio()',
    'sendVoice()',
    'sendPoll() — mode reguler',
    'sendPoll() — quiz mode',
    'forwardMessages()',
    'pinMessage() & unpinMessage()',
    "resolvePeer() — 'me'",
    'resolvePeer() — @telegram (username publik)',
    'getFullUser() — akun sendiri',
    'getMessages().resolveUsername()',
    'search() — di Saved Messages',
    'getAccount().getPrivacy()',
    'getSession().isUserAuthorized()',
    'on(NewMessage) — daftar handler',
    'onUpdate() — daftar raw handler',
    'removeHandlers()',
    'pollOnce() — non-blocking',
    'deleteMessages() — bersihkan pesan test',
    'disconnect()',
];

if (!$onlineEnabled) {
    foreach ($onlineList as $name) {
        skipTest($name, 'TG_API_ID / TG_API_HASH tidak di-set');
    }
    printSummary();
    exit(0);
}

// Inisialisasi client dengan session yang ada (jika ditemukan)
$session = $sessionFile !== null ? new FileSession($sessionFile) : null;
$client  = new TelegramClient($apiId, $apiHash, $session);

// ── T13 ───────────────────────────────────────────────────────────────────
test('Koneksi — connect() / isConnected()', function () use ($client) {
    if ($client->isConnected()) throw new \AssertionError('harusnya belum terhubung sebelum connect()');
    $client->connect();
    if (!$client->isConnected()) throw new \AssertionError('isConnected() false setelah connect()');
    return 'isConnected=true';
});

// ── T14 ───────────────────────────────────────────────────────────────────
test('getLayer() ≥ 214', function () use ($client) {
    $layer = $client->getLayer();
    if ($layer < 214) throw new \AssertionError("Layer $layer < 214");
    return "layer=$layer";
});

// ── T15 ───────────────────────────────────────────────────────────────────
$me = null;
test('getMe()', function () use ($client, &$me) {
    $me = $client->getMe();
    if (!isset($me['id']))         throw new \AssertionError("'id' tidak ada");
    if (!isset($me['first_name'])) throw new \AssertionError("'first_name' tidak ada");
    if (!is_int($me['id']))        throw new \AssertionError("'id' bukan integer");
    if (!isset($me['bot']))        throw new \AssertionError("'bot' flag tidak ada");
    if (!isset($me['premium']))    throw new \AssertionError("'premium' flag tidak ada");
    if (!isset($me['verified']))   throw new \AssertionError("'verified' flag tidak ada");
    return "id={$me['id']} name={$me['first_name']}";
});

// ── T16 ───────────────────────────────────────────────────────────────────
test('getAuth().isAuthorized()', function () use ($client) {
    $ok = $client->getAuth()->isAuthorized();
    if (!$ok) throw new \AssertionError('Session belum authorized — perlu login dulu');
    return 'authorized=true';
});

// ── T17 ───────────────────────────────────────────────────────────────────
$testMsgId = null;
test('sendMessage() → Saved Messages', function () use ($client, &$testMsgId, &$sentMsgIds) {
    $ts     = date('H:i:s');
    $result = $client->sendMessage('me', "[XnoxsProto Test] sendMessage — $ts");
    if ($result['sent'] !== true)         throw new \AssertionError("'sent' bukan true");
    if (!isset($result['message_id']))    throw new \AssertionError("'message_id' tidak ada");
    if (!is_int($result['message_id']))   throw new \AssertionError("'message_id' bukan int");
    if (!isset($result['date']))          throw new \AssertionError("'date' tidak ada");
    if (!isset($result['text']))          throw new \AssertionError("'text' tidak ada");

    $testMsgId    = $result['message_id'];
    $sentMsgIds[] = $testMsgId;
    return "message_id=$testMsgId";
});

// ── T18 ───────────────────────────────────────────────────────────────────
test('getHistory() ← Saved Messages', function () use ($client) {
    $msgs = $client->getHistory('me', limit: 5);
    if (!is_array($msgs))    throw new \AssertionError('Hasil bukan array');
    if (count($msgs) === 0)  throw new \AssertionError('Riwayat kosong');
    $m = $msgs[0];
    if (!isset($m['id']))    throw new \AssertionError("'id' tidak ada");
    if (!isset($m['date']))  throw new \AssertionError("'date' tidak ada");
    if (!isset($m['type']))  throw new \AssertionError("'type' tidak ada");
    return count($msgs) . " pesan — id[0]={$m['id']}";
});

// ── T19 ───────────────────────────────────────────────────────────────────
test('getDialogs()', function () use ($client) {
    $dialogs = $client->getDialogs(limit: 10);
    if (!is_array($dialogs))    throw new \AssertionError('Hasil bukan array');
    if (count($dialogs) === 0)  throw new \AssertionError('Tidak ada dialog');
    $d = $dialogs[0];
    if (!isset($d['type']))  throw new \AssertionError("'type' tidak ada");
    if (!isset($d['id']))    throw new \AssertionError("'id' tidak ada");
    if (!isset($d['title'])) throw new \AssertionError("'title' tidak ada");
    if (!in_array($d['type'], ['user','chat','channel']))
        throw new \AssertionError("type tidak valid: {$d['type']}");
    return count($dialogs) . " dialog — type[0]={$d['type']}";
});

// ── T20 ───────────────────────────────────────────────────────────────────
test('getContacts()', function () use ($client) {
    $contacts = $client->getContacts();
    if (!is_array($contacts)) throw new \AssertionError('Hasil bukan array');
    if (count($contacts) > 0) {
        $c = $contacts[0];
        if (!isset($c['id']))      throw new \AssertionError("'id' tidak ada");
        if (!isset($c['display'])) throw new \AssertionError("'display' tidak ada");
        if (!isset($c['bot']))     throw new \AssertionError("'bot' flag tidak ada");
    }
    return count($contacts) . ' kontak';
});

// ── T21 ───────────────────────────────────────────────────────────────────
test('editMessage()', function () use ($client, &$testMsgId) {
    if ($testMsgId === null) throw new \RuntimeException('Butuh testMsgId dari T17');
    $ts     = date('H:i:s');
    $result = $client->editMessage('me', $testMsgId, "[XnoxsProto Test] EDITED — $ts");
    if ($result['edited'] !== true)              throw new \AssertionError("'edited' bukan true");
    if ($result['message_id'] !== $testMsgId)    throw new \AssertionError('message_id berbeda');
    return "edited message_id=$testMsgId";
});

// ── T22 ───────────────────────────────────────────────────────────────────
test('sendFile() — auto-detect PNG sebagai foto', function () use ($client, &$sentMsgIds) {
    $png    = createTestPng();
    $result = $client->sendFile('me', $png, caption: '[Test] sendFile auto-detect');
    if ($result['sent'] !== true)       throw new \AssertionError("'sent' bukan true");
    if (!isset($result['message_id']))  throw new \AssertionError("'message_id' tidak ada");
    if ($result['type'] !== 'photo')    throw new \AssertionError("type bukan photo: {$result['type']}");

    $sentMsgIds[] = $result['message_id'];
    unlink($png);
    return "message_id={$result['message_id']} type={$result['type']}";
});

// ── T23 ───────────────────────────────────────────────────────────────────
test('sendFile() — forceDocument=true', function () use ($client, &$sentMsgIds) {
    $png    = createTestPng();
    $result = $client->sendFile('me', $png, caption: '[Test] forceDocument', forceDocument: true);
    if ($result['sent'] !== true)          throw new \AssertionError("'sent' bukan true");
    if ($result['type'] !== 'document')    throw new \AssertionError("type bukan document: {$result['type']}");

    $sentMsgIds[] = $result['message_id'];
    unlink($png);
    return "message_id={$result['message_id']} type={$result['type']}";
});

// ── T24 ───────────────────────────────────────────────────────────────────
test('sendPhoto()', function () use ($client, &$sentMsgIds) {
    $png    = createTestPng();
    $result = $client->sendPhoto('me', $png, caption: '[Test] sendPhoto');
    if ($result['sent'] !== true)      throw new \AssertionError("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \AssertionError("'message_id' tidak ada");

    $sentMsgIds[] = $result['message_id'];
    unlink($png);
    return "message_id={$result['message_id']}";
});

// ── T25 ───────────────────────────────────────────────────────────────────
test('sendDocument()', function () use ($client, &$sentMsgIds) {
    $txt    = createTestTxt();
    $result = $client->sendDocument('me', $txt, caption: '[Test] sendDocument', filename: 'xnoxs_test.txt');
    if ($result['sent'] !== true)      throw new \AssertionError("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \AssertionError("'message_id' tidak ada");

    $sentMsgIds[] = $result['message_id'];
    unlink($txt);
    return "message_id={$result['message_id']}";
});

// ── T26 ───────────────────────────────────────────────────────────────────
test('sendAudio()', function () use ($client, &$sentMsgIds) {
    $ogg    = createTestOgg();
    $result = $client->sendAudio('me', $ogg, caption: '[Test] sendAudio',
                                  title: 'Test Track', performer: 'XnoxsProto');
    if ($result['sent'] !== true)      throw new \AssertionError("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \AssertionError("'message_id' tidak ada");

    $sentMsgIds[] = $result['message_id'];
    unlink($ogg);
    return "message_id={$result['message_id']}";
});

// ── T27 ───────────────────────────────────────────────────────────────────
test('sendVoice()', function () use ($client, &$sentMsgIds) {
    $ogg    = createTestOgg();
    $result = $client->sendVoice('me', $ogg, duration: 1);
    if ($result['sent'] !== true)      throw new \AssertionError("'sent' bukan true");
    if (!isset($result['message_id'])) throw new \AssertionError("'message_id' tidak ada");

    $sentMsgIds[] = $result['message_id'];
    unlink($ogg);
    return "message_id={$result['message_id']}";
});

// ── T28 ───────────────────────────────────────────────────────────────────
$pollMsgId = null;
test('sendPoll() — mode reguler', function () use ($client, &$sentMsgIds, &$pollMsgId) {
    $result = $client->sendPoll(
        'me',
        'Bahasa PHP favoritmu?',
        ['PHP 8.2', 'PHP 8.3', 'PHP 8.4']
    );
    if ($result['sent'] !== true)     throw new \AssertionError("'sent' bukan true");
    if ($result['type'] !== 'poll')   throw new \AssertionError("type bukan poll: {$result['type']}");
    if (!isset($result['message_id'])) throw new \AssertionError("'message_id' tidak ada");

    $pollMsgId    = $result['message_id'];
    $sentMsgIds[] = $pollMsgId;
    return "message_id=$pollMsgId mode=regular";
});

// ── T29 ───────────────────────────────────────────────────────────────────
test('sendPoll() — quiz mode', function () use ($client, &$sentMsgIds) {
    $result = $client->sendPoll(
        'me',
        'Layer Telegram yang dipakai XnoxsProto?',
        ['148', '165', '200', '214'],
        isQuiz:       true,
        correctIndex: 3,
        solution:     'XnoxsProto memakai Layer 214.'
    );
    if ($result['sent'] !== true)     throw new \AssertionError("'sent' bukan true");
    if ($result['type'] !== 'poll')   throw new \AssertionError("type bukan poll: {$result['type']}");

    $sentMsgIds[] = $result['message_id'];
    return "message_id={$result['message_id']} mode=quiz";
});

// ── T30 ───────────────────────────────────────────────────────────────────
test('forwardMessages()', function () use ($client, &$testMsgId, &$sentMsgIds) {
    if ($testMsgId === null) throw new \RuntimeException('Butuh testMsgId dari T17');
    $result = $client->forwardMessages('me', [$testMsgId], 'me');
    if ($result['forwarded'] !== true)            throw new \AssertionError("'forwarded' bukan true");
    if (!in_array($testMsgId, $result['ids']))    throw new \AssertionError('ID tidak ada di result');
    return "forwarded ids=" . implode(',', $result['ids']);
});

// ── T31 ───────────────────────────────────────────────────────────────────
test('pinMessage() & unpinMessage()', function () use ($client, &$testMsgId) {
    if ($testMsgId === null) throw new \RuntimeException('Butuh testMsgId dari T17');
    $pin = $client->pinMessage('me', $testMsgId, silent: true);
    if ($pin['pinned'] !== true)     throw new \AssertionError("'pinned' bukan true");

    $unpin = $client->unpinMessage('me', $testMsgId);
    if ($unpin['unpinned'] !== true) throw new \AssertionError("'unpinned' bukan true");
    return "pin+unpin message_id=$testMsgId OK";
});

// ── T32 ───────────────────────────────────────────────────────────────────
test("resolvePeer() — 'me'", function () use ($client) {
    $peer = $client->resolvePeer('me');
    if (!($peer instanceof InputPeer))       throw new \AssertionError('Hasil bukan InputPeer');
    if ($peer->getType() !== InputPeer::SELF) throw new \AssertionError("type bukan SELF: " . dechex($peer->getType()));
    return 'type=SELF OK';
});

// ── T33 ───────────────────────────────────────────────────────────────────
test('resolvePeer() — @telegram (username publik)', function () use ($client) {
    $peer = $client->resolvePeer('@telegram');
    if (!($peer instanceof InputPeer))          throw new \AssertionError('Hasil bukan InputPeer');
    if ($peer->getType() !== InputPeer::CHANNEL) throw new \AssertionError("type bukan CHANNEL: " . dechex($peer->getType()));
    if ($peer->getId() <= 0)                     throw new \AssertionError('ID tidak valid');
    return "type=CHANNEL id={$peer->getId()}";
});

// ── T34 ───────────────────────────────────────────────────────────────────
test('getFullUser() — akun sendiri', function () use ($client, &$me) {
    if ($me === null) throw new \RuntimeException('Butuh data getMe() dari T15');
    $info = $client->getFullUser($me['id']);
    if (!array_key_exists('id', $info))                  throw new \AssertionError("'id' tidak ada");
    if (!array_key_exists('first_name', $info))          throw new \AssertionError("'first_name' tidak ada");
    if (!array_key_exists('is_blocked', $info))          throw new \AssertionError("'is_blocked' tidak ada");
    if (!array_key_exists('common_chats_count', $info))  throw new \AssertionError("'common_chats_count' tidak ada");
    if ($info['id'] !== $me['id'])                       throw new \AssertionError('id tidak cocok dengan getMe()');
    return "id={$info['id']} blocked={$info['is_blocked']}";
});

// ── T35 ───────────────────────────────────────────────────────────────────
test('getMessages().resolveUsername() — @telegram', function () use ($client) {
    $info = $client->getMessages()->resolveUsername('telegram');
    if (!isset($info['id']))       throw new \AssertionError("'id' tidak ada");
    if (!isset($info['type']))     throw new \AssertionError("'type' tidak ada");
    if (!isset($info['username'])) throw new \AssertionError("'username' tidak ada");
    if ($info['type'] !== 'channel') throw new \AssertionError("type bukan channel: {$info['type']}");
    return "type={$info['type']} id={$info['id']}";
});

// ── T36 ───────────────────────────────────────────────────────────────────
test('search() — di Saved Messages', function () use ($client) {
    $msgs = $client->search('me', 'XnoxsProto', limit: 5);
    if (!is_array($msgs)) throw new \AssertionError('Hasil bukan array');
    return count($msgs) . " pesan ditemukan";
});

// ── T37 ───────────────────────────────────────────────────────────────────
test('getAccount().getPrivacy() — status online', function () use ($client) {
    $privasi = $client->getAccount()->getPrivacy(
        AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP
    );
    if (!isset($privasi['rules']))         throw new \AssertionError("'rules' tidak ada");
    if (!is_array($privasi['rules']))      throw new \AssertionError("'rules' bukan array");
    if (count($privasi['rules']) === 0)    throw new \AssertionError("'rules' kosong");
    return "rule[0]={$privasi['rules'][0]}";
});

// ── T38 ───────────────────────────────────────────────────────────────────
test('getSession().isUserAuthorized()', function () use ($client) {
    $sess = $client->getSession();
    if (!$sess->isUserAuthorized())    throw new \AssertionError('Session tidak authorized');
    if ($sess->getUserId() === null)   throw new \AssertionError('userId null');
    if ($sess->getDC() === null)       throw new \AssertionError('getDC() null');
    $dc = $sess->getDC();
    return "userId={$sess->getUserId()} dc={$dc['dc_id']}";
});

// ── T39 ───────────────────────────────────────────────────────────────────
test('on(NewMessage) — daftar handler', function () use ($client) {
    $client->on(new NewMessageFilter(incoming: true), function ($event) {});
    return 'handler terdaftar tanpa exception';
});

// ── T40 ───────────────────────────────────────────────────────────────────
test('onUpdate() — daftar raw handler', function () use ($client) {
    $client->onUpdate(function (RawUpdateEvent $event) {});
    return 'raw handler terdaftar tanpa exception';
});

// ── T41 ───────────────────────────────────────────────────────────────────
test('removeHandlers()', function () use ($client) {
    $client->removeHandlers();
    return 'semua handler dihapus tanpa exception';
});

// ── T42 ───────────────────────────────────────────────────────────────────
test('pollOnce() — non-blocking (timeout 1 detik)', function () use ($client) {
    $start   = microtime(true);
    $client->pollOnce(1);
    $elapsed = microtime(true) - $start;
    if ($elapsed > 3.5) throw new \AssertionError(sprintf('pollOnce terlalu lama: %.2fs (maks 3.5s)', $elapsed));
    return sprintf('elapsed=%.2fs', $elapsed);
});

// ── T43 ───────────────────────────────────────────────────────────────────
test('deleteMessages() — bersihkan pesan test', function () use ($client, &$sentMsgIds) {
    if (empty($sentMsgIds)) return 'tidak ada pesan test untuk dihapus';

    $total  = 0;
    $chunks = array_chunk(array_unique($sentMsgIds), 100);
    foreach ($chunks as $chunk) {
        $result = $client->deleteMessages($chunk);
        if ($result['deleted'] !== true) throw new \AssertionError("'deleted' bukan true");
        $total += count($chunk);
    }
    $sentMsgIds = [];
    return "dihapus $total pesan test";
});

// ── T44 ───────────────────────────────────────────────────────────────────
test('disconnect()', function () use ($client) {
    $client->disconnect();
    if ($client->isConnected()) throw new \AssertionError('Masih terhubung setelah disconnect()');
    return 'isConnected=false';
});

// ═══════════════════════════════════════════════════════════════════════════
// RINGKASAN
// ═══════════════════════════════════════════════════════════════════════════

printSummary();
exit($fail > 0 ? 1 : 0);
