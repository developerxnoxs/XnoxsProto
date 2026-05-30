<?php
/**
 * XnoxsProto — Script Uji Fitur Lengkap
 */

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;
use XnoxsProto\Exceptions\RPCException;
use XnoxsProto\Events\NewMessage;

// ── Kredensial ─────────────────────────────────────────────────────────────
$API_ID   = 19001991;
$API_HASH = 'f3eb78228439ad8ac3b81729df992a9a';

// ── Cari session yang sudah ada ────────────────────────────────────────────
$sessionsDir = __DIR__ . '/sessions';
@mkdir($sessionsDir, 0755, true);

$sessionFiles = glob($sessionsDir . '/*.session') ?: [];
$sessionFile = !empty($sessionFiles) ? $sessionFiles[0] : null;

// ══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ══════════════════════════════════════════════════════════════════════════════

function inp(string $prompt = ''): string
{
    if ($prompt) echo $prompt;
    return trim(fgets(STDIN));
}

function jeda(string $msg = ''): void
{
    echo ($msg ?: "\n[Tekan Enter untuk lanjut...]");
    fgets(STDIN);
}

function baris(int $n = 60, string $c = '─'): void
{
    echo str_repeat($c, $n) . "\n";
}

function judul(string $teks): void
{
    echo "\n";
    baris(60, '═');
    echo "  " . strtoupper($teks) . "\n";
    baris(60, '═');
    echo "\n";
}

function subjudul(string $s): void
{
    echo "\n";
    baris(60, '─');
    echo "  $s\n";
    baris(60, '─');
}

function ok(string $msg): void  { echo "  ✅  $msg\n"; }
function err(string $msg): void { echo "  ❌  $msg\n"; }
function info(string $msg): void{ echo "  ℹ️   $msg\n"; }

function coba(callable $fn): mixed
{
    try {
        return $fn();
    } catch (\Throwable $e) {
        err($e->getMessage());
        return null;
    }
}

/**
 * Tampilkan daftar bernomor dan minta pilihan.
 * $items = [ ['label' => '...', 'data' => ...], ... ]
 * Kembalikan item terpilih atau null jika batal/kosong.
 */
function pilihList(array $items, string $prompt = 'Pilih nomor'): ?array
{
    if (empty($items)) {
        info("Daftar kosong.");
        return null;
    }
    foreach ($items as $i => $item) {
        printf("  [%d] %s\n", $i + 1, $item['label']);
    }
    echo "  [0] Batal\n";
    $n = (int)inp("$prompt (0-" . count($items) . "): ");
    if ($n < 1 || $n > count($items)) return null;
    return $items[$n - 1];
}

/** Ambil dialog dan buat list untuk dipilih. */
function pilihDialog(TelegramClient $c, string $prompt = 'Pilih chat'): ?array
{
    echo "  Mengambil dialog...\n";
    $dialogs = coba(fn() => $c->getDialogs(100));
    if (!$dialogs) return null;
    $items = array_map(fn($d) => [
        'label' => sprintf("%-30s %s",
            substr($d['title'] ?? 'Tanpa Nama', 0, 30),
            !empty($d['username']) ? "@{$d['username']}" : "ID:{$d['id']}"),
        'data'  => $d,
    ], $dialogs);
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

/** Ambil kontak dan buat list untuk dipilih. */
function pilihKontak(TelegramClient $c, string $prompt = 'Pilih kontak'): ?array
{
    echo "  Mengambil kontak...\n";
    $contacts = coba(fn() => $c->getContacts());
    if (!$contacts) return null;
    $items = array_map(fn($k) => [
        'label' => sprintf("%s%s%s",
            $k['display'] ?? ($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''),
            !empty($k['username']) ? " (@{$k['username']})" : '',
            !empty($k['phone']) ? " [{$k['phone']}]" : ''),
        'data'  => $k,
    ], $contacts);
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

/**
 * Tanya user: pilih tujuan dari Dialog atau dari Kontak.
 * Mengembalikan array dengan setidaknya key 'id' dan 'title'/'display'.
 */
function pilihTujuan(TelegramClient $c, string $label = 'tujuan'): ?array
{
    echo "\n  Pilih $label dari:\n";
    echo "  [1] Dialog (riwayat chat)\n";
    echo "  [2] Kontak\n";
    echo "  [0] Batal\n";
    $pilihan = inp("  Pilihan: ");
    if ($pilihan === '1') {
        return pilihDialog($c, "Pilih $label");
    } elseif ($pilihan === '2') {
        $k = pilihKontak($c, "Pilih $label");
        if (!$k) return null;
        // Normalisasi ke format mirip dialog agar 'id' selalu ada
        $k['title'] = $k['display'] ?? (($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''));
        return $k;
    }
    return null;
}

/**
 * Library hanya mengembalikan 3 nilai 'type': 'user', 'chat', 'channel'.
 * Supergroup & broadcast channel keduanya bertipe 'channel',
 * dibedakan lewat flag 'is_supergroup' dan 'is_channel'.
 */
function _subtype(array $d): string
{
    if ($d['type'] === 'chat')    return 'Grup Biasa';
    if ($d['type'] === 'channel') {
        if (!empty($d['is_supergroup'])) return 'Supergroup';
        if (!empty($d['is_channel']))    return 'Channel';
        return 'Channel/SG';   // fallback jika flag belum diisi
    }
    if ($d['type'] === 'user') return !empty($d['bot']) ? 'Bot' : 'User';
    return $d['type'];
}

/** Filter dialog hanya channel & supergroup (type='channel'). */
function pilihChannel(TelegramClient $c, string $prompt = 'Pilih channel/supergroup'): ?array
{
    echo "  Mengambil dialog...\n";
    $dialogs = coba(fn() => $c->getDialogs(80));
    if (!$dialogs) return null;
    $filtered = array_values(array_filter($dialogs, fn($d) => ($d['type'] ?? '') === 'channel'));
    if (empty($filtered)) { info("Tidak ada channel/supergroup di dialog."); return null; }
    $items = array_map(fn($d) => [
        'label' => sprintf("[%-12s] %s%s",
            _subtype($d),
            $d['title'] ?? 'Tanpa Nama',
            !empty($d['username']) ? " (@{$d['username']})" : ''),
        'data'  => $d,
    ], $filtered);
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

/**
 * Ambil anggota dari grup/channel yang sudah dipilih, lalu tampilkan untuk dipilih.
 * Mendukung tipe 'chat' (getChatMembers) dan 'channel' (getChannelMembers).
 */
function pilihAnggotaDariGrup(TelegramClient $c, array $grup, string $prompt = 'Pilih anggota', int $limit = 50): ?array
{
    $tipe = $grup['type'] ?? '';
    echo "  Mengambil daftar anggota...\n";
    if ($tipe === 'channel') {
        $members = coba(fn() => $c->getChannelMembers($grup['id'], $limit));
    } elseif ($tipe === 'chat') {
        $members = coba(fn() => $c->getChatMembers($grup['id']));
    } else {
        err("Tipe tidak dikenal: $tipe");
        return null;
    }
    if (!$members) { info("Tidak bisa mengambil daftar anggota."); return null; }

    // ── Enrichment: coba isi nama untuk User#xxx ──────────────────────────
    // Langkah 1: ambil dari session entity cache (gratis, tanpa API call)
    $session = $c->getSession();
    foreach ($members as &$m) {
        $uid = $m['user_id'] ?? $m['id'] ?? null;
        if (!$uid) continue;
        $disp = $m['display'] ?? '';
        if ($disp !== '' && !str_starts_with($disp, 'User#')) continue; // sudah ada nama
        $entity = $session->getEntityRowsById($uid);
        if ($entity) {
            $fn = trim(($entity['first_name'] ?? '') . ' ' . ($entity['last_name'] ?? ''));
            if ($fn !== '')                  $m['display'] = $fn;
            elseif (!empty($entity['username'])) $m['display'] = '@' . $entity['username'];
            if (!empty($entity['username'])) $m['username'] = $entity['username'];
        }
    }
    unset($m);

    // Langkah 2: untuk yang masih User#xxx, coba batch fetch via users.getUsers
    $needEnrich = array_filter($members, fn($m) => str_starts_with($m['display'] ?? 'User#', 'User#'));
    if (!empty($needEnrich)) {
        $batchInput = array_values(array_map(fn($m) => [
            'id'          => $m['user_id'] ?? $m['id'] ?? 0,
            'access_hash' => $m['access_hash'] ?? 0,
        ], $needEnrich));
        $fetched = coba(fn() => $c->getMessages()->batchFetchUsers($batchInput));
        if ($fetched) {
            foreach ($members as &$m) {
                $uid = $m['user_id'] ?? $m['id'] ?? null;
                if (!$uid || !isset($fetched[$uid])) continue;
                $u = $fetched[$uid];
                $name = method_exists($u, 'getDisplayName') ? $u->getDisplayName() : null;
                if ($name && !str_starts_with($name, 'User#')) {
                    $m['display']    = $name;
                    $m['first_name'] = $u->firstName ?? $m['first_name'] ?? '';
                    $m['last_name']  = $u->lastName  ?? $m['last_name']  ?? '';
                    $m['username']   = $u->username  ?? $m['username']   ?? null;
                }
            }
            unset($m);
        }
    }
    // ── End enrichment ────────────────────────────────────────────────────

    // Normalisasi ke format seragam untuk ditampilkan
    $items = [];
    foreach ($members as $m) {
        $uid   = $m['user_id'] ?? $m['id'] ?? null;
        $nama  = $m['display'] ?? (trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: 'User#' . $uid);
        $uname = !empty($m['username']) ? " (@{$m['username']})" : '';
        $role  = isset($m['role']) ? ' [' . $m['role'] . ']' : '';
        $items[] = [
            'label' => $nama . $uname . $role,
            'data'  => array_merge($m, ['id' => $uid]),
        ];
    }
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

/** Filter dialog hanya grup biasa (type='chat'). */
function pilihGrup(TelegramClient $c, string $prompt = 'Pilih grup biasa'): ?array
{
    echo "  Mengambil dialog...\n";
    $dialogs = coba(fn() => $c->getDialogs(80));
    if (!$dialogs) return null;
    $filtered = array_values(array_filter($dialogs, fn($d) => ($d['type'] ?? '') === 'chat'));
    if (empty($filtered)) { info("Tidak ada grup biasa di dialog."); return null; }
    $items = array_map(fn($d) => [
        'label' => sprintf("[Grup Biasa] %s  (%d anggota)",
            $d['title'] ?? 'Tanpa Nama',
            $d['members'] ?? 0),
        'data'  => $d,
    ], $filtered);
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

/** Filter: grup biasa (chat) DAN channel/supergroup — untuk operasi yang mendukung keduanya. */
function pilihGrupAtauChannel(TelegramClient $c, string $prompt = 'Pilih grup/channel'): ?array
{
    echo "  Mengambil dialog...\n";
    $dialogs = coba(fn() => $c->getDialogs(80));
    if (!$dialogs) return null;
    $filtered = array_values(array_filter($dialogs, fn($d) => in_array($d['type'] ?? '', ['chat', 'channel'])));
    if (empty($filtered)) { info("Tidak ada grup atau channel di dialog."); return null; }
    $items = array_map(fn($d) => [
        'label' => sprintf("[%-12s] %s",
            _subtype($d),
            $d['title'] ?? 'Tanpa Nama'),
        'data'  => $d,
    ], $filtered);
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

/** Ambil riwayat chat dan buat list pesan untuk dipilih. */
function pilihPesan(TelegramClient $c, $peer, int $limit = 15, string $prompt = 'Pilih pesan'): ?array
{
    echo "  Mengambil riwayat...\n";
    $msgs = coba(fn() => $c->getHistory($peer, $limit));
    if (!$msgs) return null;
    $items = array_map(fn($m) => [
        'label' => sprintf("#%-6d | %-10s | %s",
            $m['id'],
            substr($m['date'] ?? '', 0, 10),
            substr(strip_tags($m['text'] ?? ($m['media']['type'] ?? '[no text]')), 0, 40)),
        'data'  => $m,
    ], $msgs);
    $pick = pilihList($items, $prompt);
    return $pick ? $pick['data'] : null;
}

// ── File aset uji ─────────────────────────────────────────────────────────
$ASSET_PHOTO = __DIR__ . '/test_assets/test_photo.jpg';
$ASSET_DOC   = __DIR__ . '/test_assets/test_doc.txt';
$ASSET_AUDIO = __DIR__ . '/test_assets/test_audio.mp3';

// ══════════════════════════════════════════════════════════════════════════════
// KONEKSI & LOGIN OTOMATIS
// ══════════════════════════════════════════════════════════════════════════════

echo "\n";
baris(60, '═');
echo "  XNOXSPROTO — TESTER FITUR LENGKAP\n";
baris(60, '═');

TelegramClient::setSessionsDir($sessionsDir);
$client = TelegramClient::create($API_ID, $API_HASH, $sessionFile);

if ($sessionFile) {
    echo "  Session : $sessionFile\n";
    echo "  Menghubungkan...\n";
    // Coba pakai sesi yang ada — kalau sudah auth, langsung lanjut
    try {
        $client->start();
    } catch (\Throwable $e) {
        // Sesi kadaluarsa atau tidak valid — minta login ulang
        echo "  ⚠️   Sesi tidak valid atau kadaluarsa: " . $e->getMessage() . "\n";
        $sessionFile = null;
    }
}

if (!$sessionFile) {
    // Tidak ada sesi atau sesi gagal — lakukan login baru
    echo "\n";
    baris(60, '─');
    echo "  LOGIN BARU\n";
    baris(60, '─');
    $phone = inp("  Nomor telepon (contoh: +628123456789): ");
    if (empty(trim($phone))) {
        die("[ERROR] Nomor telepon tidak boleh kosong.\n");
    }
    try {
        $client->start(phone: $phone);
    } catch (\Throwable $e) {
        die("[ERROR] Login gagal: " . $e->getMessage() . "\n");
    }
}

$me = coba(fn() => $client->getMe());
if ($me) {
    echo "  ✅  Login : " . ($me['first_name'] ?? '') . " " . ($me['last_name'] ?? '') . " (ID: {$me['id']})\n";
} else {
    die("[ERROR] Gagal mengambil info akun setelah login.\n");
}
baris(60, '═');

// ══════════════════════════════════════════════════════════════════════════════
// SUBMENUS
// ══════════════════════════════════════════════════════════════════════════════

// ─── 1. Manajemen Akun ────────────────────────────────────────────────────
function menu_akun(TelegramClient $c, string $sessionsDir, string $activeSession): void
{
    while (true) {
        judul("1. Manajemen Akun");
        echo "  [1]  Info akun saya\n";
        echo "  [2]  Edit nama depan / nama belakang / bio\n";
        echo "  [3]  Edit username\n";
        echo "  [4]  Upload foto profil\n";
        echo "  [5]  Lihat foto profil\n";
        echo "  [6]  Hapus foto profil\n";
        echo "  [7]  Lihat sesi aktif\n";
        echo "  [8]  Hapus sesi tertentu\n";
        echo "  [9]  Keluar semua sesi lain\n";
        echo "  [10] Lihat pengaturan privasi\n";
        echo "  [11] Ubah pengaturan privasi\n";
        echo "  [12] Cabut session lokal (.session file)\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Info akun
                subjudul("Info Akun Saya");
                $me = coba(fn() => $c->getMe());
                if ($me) {
                    foreach (['id','first_name','last_name','username','phone','premium','bot'] as $k) {
                        if (isset($me[$k])) printf("  %-15s: %s\n", $k, var_export($me[$k], true));
                    }
                }
                jeda();
                break;

            case '2': // ── Edit profil
                subjudul("Edit Nama / Bio");
                $fn    = inp("  Nama depan baru (kosong = tidak ubah): ");
                $ln    = inp("  Nama belakang baru (kosong = tidak ubah): ");
                $about = inp("  Bio baru (kosong = tidak ubah): ");
                $res = coba(fn() => $c->getAccount()->updateProfile(
                    $fn    !== '' ? $fn    : null,
                    $ln    !== '' ? $ln    : null,
                    $about !== '' ? $about : null
                ));
                if ($res) ok("Profil diperbarui: " . ($res['first_name'] ?? '') . " " . ($res['last_name'] ?? ''));
                jeda();
                break;

            case '3': // ── Edit username
                subjudul("Edit Username");
                $u = inp("  Username baru (kosong = hapus username): ");
                $res = coba(fn() => $c->getAccount()->updateUsername($u));
                if ($res) ok("Username diperbarui: " . ($res['username'] ?? '(dihapus)'));
                jeda();
                break;

            case '4': // ── Upload foto profil
                subjudul("Upload Foto Profil");
                $path = inp("  Path file foto (Enter = pakai test_photo.jpg): ");
                if ($path === '') $path = __DIR__ . '/test_assets/test_photo.jpg';
                if (!file_exists($path)) { err("File tidak ditemukan: $path"); jeda(); break; }
                $res = coba(function () use ($c, $path) {
                    return $c->getAccount()->uploadProfilePhoto($path, function ($p, $t, $pct) {
                        echo "\r  Upload: $pct% ($p/$t chunk)";
                    });
                });
                echo "\n";
                if ($res) ok("Foto profil diupload. photo_id=" . ($res['photo_id'] ?? '?'));
                jeda();
                break;

            case '5': // ── Lihat foto profil
                subjudul("Foto Profil");
                $photos = coba(fn() => $c->getAccount()->getProfilePhotos());
                if ($photos !== null) {
                    if (empty($photos)) {
                        info("Tidak ada foto profil tersimpan.");
                    } else {
                        foreach ($photos as $i => $p) {
                            printf("  [%d] ID=%-20s  tanggal=%s\n",
                                $i + 1,
                                $p['id'],
                                date('d/m/Y H:i', $p['date'])
                            );
                        }
                    }
                }
                jeda();
                break;

            case '6': // ── Hapus foto profil
                subjudul("Hapus Foto Profil");
                $photos = coba(fn() => $c->getAccount()->getProfilePhotos());
                if ($photos === null) { jeda(); break; }
                if (empty($photos)) { info("Tidak ada foto profil untuk dihapus."); jeda(); break; }
                $items = array_map(fn($p) => [
                    'label' => sprintf("ID=%-20s  tanggal=%s", $p['id'], date('d/m/Y H:i', $p['date'])),
                    'data'  => $p,
                ], $photos);
                $pick = pilihList($items, "Pilih foto yang akan dihapus");
                if (!$pick) { jeda(); break; }
                $konfirm = inp("  Hapus foto ini? (ya/tidak): ");
                if (strtolower($konfirm) === 'ya') {
                    $res = coba(fn() => $c->getAccount()->deleteProfilePhoto($pick['data']['id']));
                    if ($res === true) ok("Foto berhasil dihapus.");
                    elseif ($res === false) err("Server tidak mengkonfirmasi penghapusan.");
                } else {
                    info("Dibatalkan.");
                }
                jeda();
                break;

            case '7': // ── Sesi aktif
                subjudul("Sesi Aktif");
                $sessions = coba(fn() => $c->getAccount()->getAuthorizations());
                if ($sessions) {
                    foreach ($sessions as $i => $s) {
                        printf("  [%d] %s — %s — %s — IP:%s (%s)\n",
                            $i + 1,
                            $s['device_model'] ?? '?',
                            $s['app_name']     ?? '?',
                            $s['date_active']  ?? '?',
                            $s['ip']           ?? '?',
                            $s['country']      ?? '?'
                        );
                        if (!empty($s['current'])) echo "       ★ SESI INI\n";
                    }
                }
                jeda();
                break;

            case '8': // ── Hapus sesi tertentu
                subjudul("Hapus Sesi Tertentu");
                $sessions = coba(fn() => $c->getAccount()->getAuthorizations());
                if (!$sessions) { jeda(); break; }
                // Tampilkan semua sesi — sesi aktif ditandai ★ AKTIF (tidak bisa dipilih)
                echo "\n  Semua sesi aktif:\n";
                foreach ($sessions as $i => $s) {
                    $aktif = !empty($s['current']);
                    printf("  %s %-2s %s — %s — IP:%s (%s) — %s\n",
                        $aktif ? '★' : ' ',
                        $aktif ? '[AKTIF]' : '',
                        $s['device_model'] ?? '?',
                        $s['app_name']     ?? '?',
                        $s['ip']           ?? '?',
                        $s['country']      ?? '?',
                        $s['date_active']  ?? '?'
                    );
                }
                echo "\n";
                $nonCurrent = array_values(array_filter($sessions, fn($s) => empty($s['current'])));
                if (empty($nonCurrent)) { info("Tidak ada sesi lain selain sesi aktif ini."); jeda(); break; }
                $items = array_map(fn($s) => [
                    'label' => sprintf("%s — %s — IP:%s (%s)",
                        $s['device_model'] ?? '?',
                        $s['app_name']     ?? '?',
                        $s['ip']           ?? '?',
                        $s['country']      ?? '?'),
                    'data'  => $s,
                ], $nonCurrent);
                $pick = pilihList($items, "Pilih sesi yang akan dihapus");
                if ($pick) {
                    $res = coba(fn() => $c->getAccount()->resetAuthorization($pick['data']['hash']));
                    if ($res) ok("Sesi dihapus.");
                }
                jeda();
                break;

            case '9': // ── Keluar semua sesi lain
                subjudul("Keluar Semua Sesi Lain");
                $konfirm = inp("  Yakin? Semua perangkat lain akan logout. (ya/tidak): ");
                if ($konfirm === 'ya') {
                    $n = coba(fn() => $c->getAccount()->terminateAllOtherSessions());
                    if ($n !== null) ok("$n sesi dihentikan.");
                }
                jeda();
                break;

            case '10': // ── Lihat privasi
                subjudul("Lihat Pengaturan Privasi");
                $privacyKeys = [
                    'Last Seen / Online'  => AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP,
                    'Undang ke Grup'      => AccountGetPrivacyRequest::KEY_CHAT_INVITE,
                    'Panggilan Suara'     => AccountGetPrivacyRequest::KEY_PHONE_CALL,
                    'P2P Panggilan'       => AccountGetPrivacyRequest::KEY_PHONE_P2P,
                    'Forward Pesan'       => AccountGetPrivacyRequest::KEY_FORWARDS,
                    'Foto Profil'         => AccountGetPrivacyRequest::KEY_PROFILE_PHOTO,
                    'Nomor Telepon'       => AccountGetPrivacyRequest::KEY_PHONE_NUMBER,
                    'Tambah via Telepon'  => AccountGetPrivacyRequest::KEY_ADDED_BY_PHONE,
                    'Pesan Suara'         => AccountGetPrivacyRequest::KEY_VOICE_MESSAGES,
                    'Bio / About'         => AccountGetPrivacyRequest::KEY_ABOUT,
                ];
                $keyItems = array_map(fn($label, $val) => ['label' => $label, 'data' => $val], array_keys($privacyKeys), array_values($privacyKeys));
                $pick = pilihList($keyItems, "Pilih jenis privasi");
                if ($pick) {
                    $res = coba(fn() => $c->getAccount()->getPrivacy($pick['data']));
                    if ($res && !empty($res['rules'])) {
                        $labelMap = [
                            'allow_all'                  => 'Semua orang',
                            'allow_contacts'             => 'Hanya kontak',
                            'allow_close_friends'        => 'Teman dekat saja',
                            'allow_premium'              => 'Pengguna Premium saja',
                            'allow_bots'                 => 'Bot saja',
                            'allow_users'                => 'Pengguna tertentu (allow)',
                            'allow_chat_participants'    => 'Anggota chat tertentu (allow)',
                            'disallow_all'               => 'Tidak ada',
                            'disallow_contacts'          => 'Kecuali kontak',
                            'disallow_bots'              => 'Kecuali bot',
                            'disallow_users'             => 'Pengguna tertentu (block)',
                            'disallow_chat_participants' => 'Anggota chat tertentu (block)',
                        ];
                        foreach ($res['rules'] as $rule) {
                            $tampil = $labelMap[$rule] ?? $rule;
                            info("Aturan: $tampil");
                        }
                    } elseif ($res !== null) {
                        info("Tidak ada aturan privasi yang tersimpan.");
                    }
                }
                jeda();
                break;

            case '11': // ── Ubah privasi
                subjudul("Ubah Pengaturan Privasi");
                $privacyKeys = [
                    'Last Seen / Online'  => AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP,
                    'Undang ke Grup'      => AccountGetPrivacyRequest::KEY_CHAT_INVITE,
                    'Panggilan Suara'     => AccountGetPrivacyRequest::KEY_PHONE_CALL,
                    'Forward Pesan'       => AccountGetPrivacyRequest::KEY_FORWARDS,
                    'Foto Profil'         => AccountGetPrivacyRequest::KEY_PROFILE_PHOTO,
                    'Nomor Telepon'       => AccountGetPrivacyRequest::KEY_PHONE_NUMBER,
                    'Bio / About'         => AccountGetPrivacyRequest::KEY_ABOUT,
                ];
                $keyItems = array_map(fn($l, $v) => ['label' => $l, 'data' => $v], array_keys($privacyKeys), array_values($privacyKeys));
                $pick = pilihList($keyItems, "Pilih jenis privasi");
                if (!$pick) break;
                echo "  Aturan: [1] Semua orang  [2] Kontak saja  [3] Tidak ada\n";
                $r = inp("  Pilih aturan: ");
                $rules = match($r) {
                    '1' => ['allow_all'],
                    '2' => ['allow_contacts'],
                    '3' => ['disallow_all'],
                    default => null,
                };
                if ($rules) {
                    $res = coba(fn() => $c->getAccount()->setPrivacy($pick['data'], $rules));
                    if ($res) ok("Pengaturan privasi diperbarui.");
                } else {
                    err("Pilihan tidak valid.");
                }
                jeda();
                break;

            case '12': // ── Cabut session lokal
                subjudul("Cabut Session Lokal");
                $files = glob($sessionsDir . '/*.session') ?: [];
                if (empty($files)) {
                    info("Tidak ada file session di folder sessions/.");
                    jeda();
                    break;
                }
                $items = array_map(function ($f) use ($activeSession) {
                    $nama  = basename($f);
                    $tanda = (realpath($f) === realpath($activeSession)) ? ' ★ AKTIF' : '';
                    return ['label' => $nama . $tanda, 'data' => $f];
                }, $files);
                $pick = pilihList($items, "Pilih session yang akan dicabut");
                if (!$pick) break;
                $target    = $pick['data'];
                $isAktif   = (realpath($target) === realpath($activeSession));
                $namaFile  = basename($target);
                if ($isAktif) {
                    echo "\n  ⚠️   Ini adalah session yang SEDANG AKTIF.\n";
                    echo "  Setelah dicabut, Anda harus login ulang saat restart.\n";
                }
                $konfirm = inp("  Hapus file '$namaFile'? (ya/tidak): ");
                if (strtolower($konfirm) === 'ya') {
                    if (@unlink($target)) {
                        ok("File session '$namaFile' berhasil dihapus.");
                        if ($isAktif) info("Restart program untuk login ulang.");
                    } else {
                        err("Gagal menghapus file: $target");
                    }
                } else {
                    info("Dibatalkan.");
                }
                jeda();
                break;

            case '0': return;
        }
    }
}

// ─── 2. Pesan & Chat ─────────────────────────────────────────────────────
function menu_pesan(TelegramClient $c): void
{
    while (true) {
        judul("2. Pesan & Chat");
        echo "  [1]  Kirim pesan teks\n";
        echo "  [2]  Lihat riwayat chat\n";
        echo "  [3]  Edit pesan\n";
        echo "  [4]  Hapus pesan\n";
        echo "  [5]  Forward pesan\n";
        echo "  [6]  Cari pesan dalam chat\n";
        echo "  [7]  Cari pesan global\n";
        echo "  [8]  Pin pesan\n";
        echo "  [9]  Unpin pesan\n";
        echo "  [10] Kirim polling\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Kirim pesan teks
                subjudul("Kirim Pesan Teks");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $teks = inp("  Teks pesan: ");
                if ($teks === '') break;
                $res = coba(fn() => $c->sendMessage($dialog['id'], $teks));
                if ($res) ok("Pesan terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '2': // ── Riwayat chat
                subjudul("Riwayat Chat");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $limit = (int)(inp("  Jumlah pesan (Enter=10): ") ?: 10);
                $msgs = coba(fn() => $c->getHistory($dialog['id'], max(1, $limit)));
                if ($msgs) {
                    foreach ($msgs as $m) {
                        printf("  #%-6d | %s | %s\n",
                            $m['id'],
                            substr($m['date'] ?? '', 0, 16),
                            substr($m['text'] ?? ('[' . ($m['media']['type'] ?? 'service') . ']'), 0, 60)
                        );
                    }
                    info("Total: " . count($msgs) . " pesan.");
                }
                jeda();
                break;

            case '3': // ── Edit pesan
                subjudul("Edit Pesan");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $msg = pilihPesan($c, $dialog['id'], 15, "Pilih pesan yang akan diedit");
                if (!$msg) break;
                $teks = inp("  Teks baru: ");
                if ($teks === '') break;
                $res = coba(fn() => $c->editMessage($dialog['id'], $msg['id'], $teks));
                if ($res) ok("Pesan diedit.");
                jeda();
                break;

            case '4': // ── Hapus pesan
                subjudul("Hapus Pesan");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $msg = pilihPesan($c, $dialog['id'], 15, "Pilih pesan yang akan dihapus");
                if (!$msg) break;
                $revoke = strtolower(inp("  Hapus untuk semua orang? (ya/tidak): ")) === 'ya';
                $res = coba(fn() => $c->deleteMessages([$msg['id']], $dialog['id'], $revoke));
                if ($res) ok("Pesan dihapus.");
                jeda();
                break;

            case '5': // ── Forward pesan
                subjudul("Forward Pesan");
                echo "  Pilih sumber:\n";
                $from = pilihDialog($c, "Pilih chat sumber");
                if (!$from) break;
                $msg = pilihPesan($c, $from['id'], 15, "Pilih pesan yang akan diteruskan");
                if (!$msg) break;
                echo "  Pilih tujuan:\n";
                $to = pilihTujuan($c, "tujuan forward");
                if (!$to) break;
                $res = coba(fn() => $c->forwardMessages($to['id'], [$msg['id']], $from['id']));
                if ($res) ok("Pesan diteruskan.");
                jeda();
                break;

            case '6': // ── Cari dalam chat
                subjudul("Cari Pesan dalam Chat");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $q = inp("  Kata kunci: ");
                if ($q === '') break;
                $msgs = coba(fn() => $c->search($dialog['id'], $q, 20));
                if ($msgs) {
                    foreach ($msgs as $m) {
                        printf("  #%-6d | %s\n", $m['id'], substr($m['text'] ?? '', 0, 70));
                    }
                    info(count($msgs) . " hasil ditemukan.");
                }
                jeda();
                break;

            case '7': // ── Cari global
                subjudul("Cari Pesan Global");
                $q = inp("  Kata kunci: ");
                if ($q === '') break;
                $msgs = coba(fn() => $c->searchGlobal($q, 20));
                if ($msgs) {
                    foreach ($msgs as $m) {
                        printf("  #%-6d | %s | %s\n", $m['id'], $m['peer_title'] ?? '?', substr($m['text'] ?? '', 0, 50));
                    }
                    info(count($msgs) . " hasil ditemukan.");
                }
                jeda();
                break;

            case '8': // ── Pin pesan
                subjudul("Pin Pesan");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $msg = pilihPesan($c, $dialog['id'], 15, "Pilih pesan yang akan di-pin");
                if (!$msg) break;
                $silent = strtolower(inp("  Tanpa notifikasi? (ya/tidak): ")) === 'ya';
                $res = coba(fn() => $c->pinMessage($dialog['id'], $msg['id'], $silent));
                if ($res) ok("Pesan di-pin.");
                jeda();
                break;

            case '9': // ── Unpin pesan
                subjudul("Unpin Pesan");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $msg = pilihPesan($c, $dialog['id'], 15, "Pilih pesan yang akan di-unpin");
                if (!$msg) break;
                $res = coba(fn() => $c->unpinMessage($dialog['id'], $msg['id']));
                if ($res) ok("Pesan di-unpin.");
                jeda();
                break;

            case '10': // ── Kirim polling
                subjudul("Kirim Polling");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $pertanyaan = inp("  Pertanyaan polling: ");
                if ($pertanyaan === '') break;
                $opsi = [];
                echo "  Masukkan pilihan jawaban (kosongkan untuk selesai, minimal 2):\n";
                for ($i = 1; $i <= 10; $i++) {
                    $o = inp("  Pilihan $i: ");
                    if ($o === '') break;
                    $opsi[] = $o;
                }
                if (count($opsi) < 2) { err("Minimal 2 pilihan."); jeda(); break; }
                $kuis = strtolower(inp("  Mode kuis? (ya/tidak): ")) === 'ya';
                $correctIdx = 0;
                if ($kuis) {
                    echo "  Pilihan benar (1-" . count($opsi) . "): ";
                    $correctIdx = max(0, (int)inp('') - 1);
                }
                $res = coba(fn() => $c->sendPoll($dialog['id'], $pertanyaan, $opsi, $kuis, $correctIdx));
                if ($res) ok("Polling terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '0': return;
        }
    }
}

// ─── 3. Media ──────────────────────────────────────────────────────────────
function menu_media(TelegramClient $c, string $assetPhoto, string $assetDoc, string $assetAudio): void
{
    while (true) {
        judul("3. Media");
        echo "  [1]  Kirim foto\n";
        echo "  [2]  Kirim video\n";
        echo "  [3]  Kirim audio / MP3\n";
        echo "  [4]  Kirim dokumen\n";
        echo "  [5]  Kirim pesan suara (voice)\n";
        echo "  [6]  Download media dari riwayat chat\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Kirim foto
                subjudul("Kirim Foto");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $path = inp("  Path foto (Enter = pakai test_photo.jpg): ");
                if ($path === '') $path = $assetPhoto;
                if (!file_exists($path)) { err("File tidak ditemukan: $path"); jeda(); break; }
                $caption = inp("  Caption (opsional): ");
                $res = coba(fn() => $c->sendPhoto($dialog['id'], $path, $caption ?: null));
                if ($res) ok("Foto terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '2': // ── Kirim video
                subjudul("Kirim Video");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $path = inp("  Path video (mp4): ");
                if ($path === '' || !file_exists($path)) { err("File tidak ditemukan."); jeda(); break; }
                $caption = inp("  Caption (opsional): ");
                $res = coba(fn() => $c->sendVideo($dialog['id'], $path, $caption ?: null));
                if ($res) ok("Video terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '3': // ── Kirim audio
                subjudul("Kirim Audio");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $path = inp("  Path audio (Enter = pakai test_audio.mp3): ");
                if ($path === '') $path = $assetAudio;
                if (!file_exists($path)) { err("File tidak ditemukan: $path"); jeda(); break; }
                $caption = inp("  Caption (opsional): ");
                $res = coba(fn() => $c->sendAudio($dialog['id'], $path, $caption ?: null));
                if ($res) ok("Audio terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '4': // ── Kirim dokumen
                subjudul("Kirim Dokumen");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $path = inp("  Path file (Enter = pakai test_doc.txt): ");
                if ($path === '') $path = $assetDoc;
                if (!file_exists($path)) { err("File tidak ditemukan: $path"); jeda(); break; }
                $caption = inp("  Caption (opsional): ");
                $res = coba(fn() => $c->sendDocument($dialog['id'], $path, $caption ?: null));
                if ($res) ok("Dokumen terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '5': // ── Kirim voice
                subjudul("Kirim Pesan Suara (Voice)");
                $dialog = pilihTujuan($c, "tujuan");
                if (!$dialog) break;
                $path = inp("  Path file ogg/mp3: ");
                if ($path === '' || !file_exists($path)) { err("File tidak ditemukan."); jeda(); break; }
                $res = coba(fn() => $c->sendVoice($dialog['id'], $path));
                if ($res) ok("Voice message terkirim. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '6': // ── Download media
                subjudul("Download Media dari Riwayat");
                $dialog = pilihDialog($c, "Pilih chat");
                if (!$dialog) break;
                $msgs = coba(fn() => $c->getHistory($dialog['id'], 20));
                if (!$msgs) break;
                $mediaMsgs = array_values(array_filter($msgs, fn($m) => !empty($m['media'])));
                if (empty($mediaMsgs)) { info("Tidak ada pesan media di riwayat terbaru."); jeda(); break; }
                $items = array_map(fn($m) => [
                    'label' => sprintf("#%-6d | %-10s | %s | %s bytes",
                        $m['id'],
                        $m['media']['type'] ?? '?',
                        $m['media']['mime'] ?? '?',
                        number_format($m['media']['size'] ?? 0)),
                    'data'  => $m,
                ], $mediaMsgs);
                $pick = pilihList($items, "Pilih media yang akan didownload");
                if (!$pick) break;
                $msg = $pick['data'];
                $ext  = $c->getMediaExtension($msg['media']);
                $dest = __DIR__ . '/downloads/dl_' . $msg['id'] . '.' . $ext;
                @mkdir(__DIR__ . '/downloads', 0755, true);
                $res = coba(function () use ($c, $msg, $dest) {
                    return $c->downloadMedia($msg, $dest, function ($recv, $total, $pct) {
                        $bar = str_repeat('█', (int)($pct / 5)) . str_repeat('░', 20 - (int)($pct / 5));
                        echo "\r  [$bar] $pct% — " . number_format($recv) . " bytes";
                    });
                });
                echo "\n";
                if ($res) ok("Tersimpan: $dest (" . number_format(filesize($dest)) . " bytes)");
                jeda();
                break;

            case '0': return;
        }
    }
}

// ─── 4. Kontak & Dialog ───────────────────────────────────────────────────
function menu_kontak(TelegramClient $c): void
{
    while (true) {
        judul("4. Kontak & Dialog");
        echo "  [1]  Lihat semua dialog\n";
        echo "  [2]  Lihat daftar kontak\n";
        echo "  [3]  Info lengkap pengguna\n";
        echo "  [4]  Info lengkap chat\n";
        echo "  [5]  Info lengkap channel\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Semua dialog
                subjudul("Semua Dialog");
                $dialogs = coba(fn() => $c->getDialogs(100));
                if ($dialogs) {
                    $grouped = [];
                    foreach ($dialogs as $d) $grouped[$d['type'] ?? 'unknown'][] = $d;
                    foreach ($grouped as $tipe => $list) {
                        echo "\n  ── " . strtoupper($tipe) . " (" . count($list) . ") ──\n";
                        foreach ($list as $d) {
                            printf("  %-30s %s\n",
                                substr($d['title'] ?? 'Tanpa Nama', 0, 30),
                                !empty($d['username']) ? "@{$d['username']}" : "ID:{$d['id']}"
                            );
                        }
                    }
                    info("Total: " . count($dialogs) . " dialog.");
                }
                jeda();
                break;

            case '2': // ── Kontak
                subjudul("Daftar Kontak");
                $contacts = coba(fn() => $c->getContacts());
                if ($contacts) {
                    foreach ($contacts as $k) {
                        printf("  %-30s %s%s\n",
                            substr($k['display'] ?? ($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? ''), 0, 30),
                            !empty($k['username']) ? "@{$k['username']}  " : '',
                            !empty($k['phone'])    ? "+{$k['phone']}"     : ''
                        );
                    }
                    info("Total: " . count($contacts) . " kontak.");
                }
                jeda();
                break;

            case '3': // ── Info pengguna
                subjudul("Info Lengkap Pengguna");
                $kontak = pilihKontak($c, "Pilih pengguna");
                if (!$kontak) break;
                $info = coba(fn() => $c->getFullUser($kontak['id']));
                if ($info) {
                    foreach ($info as $k => $v) {
                        if ($v !== null && $v !== false && $v !== '')
                            printf("  %-25s: %s\n", $k, is_bool($v) ? ($v ? 'ya' : 'tidak') : $v);
                    }
                }
                jeda();
                break;

            case '4': // ── Info chat
                subjudul("Info Lengkap Chat");
                $dialog = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$dialog) break;
                if (($dialog['type'] ?? '') === 'channel') {
                    $info = coba(fn() => $c->getFullChannel($dialog['id']));
                } else {
                    $info = coba(fn() => $c->getFullChat($dialog['id']));
                }
                if ($info) {
                    foreach ($info as $k => $v) {
                        if ($v !== null && $v !== false && $v !== '' && !is_array($v))
                            printf("  %-25s: %s\n", $k, is_bool($v) ? ($v ? 'ya' : 'tidak') : $v);
                    }
                }
                jeda();
                break;

            case '5': // ── Info channel
                subjudul("Info Lengkap Channel");
                $ch = pilihChannel($c, "Pilih channel/supergroup");
                if (!$ch) break;
                $info = coba(fn() => $c->getFullChannel($ch['id']));
                if ($info) {
                    foreach ($info as $k => $v) {
                        if ($v !== null && $v !== false && $v !== '' && !is_array($v))
                            printf("  %-25s: %s\n", $k, is_bool($v) ? ($v ? 'ya' : 'tidak') : $v);
                    }
                }
                jeda();
                break;

            case '0': return;
        }
    }
}

// ─── 5. Grup & Channel ────────────────────────────────────────────────────
function menu_grup(TelegramClient $c): void
{
    while (true) {
        judul("5. Grup & Channel");
        echo "  [1]  Buat grup biasa\n";
        echo "  [2]  Buat supergroup\n";
        echo "  [3]  Buat channel broadcast\n";
        echo "  [4]  Gabung channel (username/link)\n";
        echo "  [5]  Keluar channel/supergroup\n";
        echo "  [6]  Undang anggota ke channel/supergroup\n";
        echo "  [7]  Tambah anggota ke grup biasa\n";
        echo "  [8]  Promosi admin\n";
        echo "  [9]  Turunkan admin\n";
        echo "  [10] Ban anggota\n";
        echo "  [11] Unban anggota\n";
        echo "  [12] Kick anggota\n";
        echo "  [13] Export link undangan\n";
        echo "  [14] Slow mode\n";
        echo "  [15] Edit judul\n";
        echo "  [16] Edit deskripsi\n";
        echo "  [17] Lihat anggota channel/supergroup\n";
        echo "  [18] Hapus grup/channel\n";
        echo "  [19] Lihat anggota grup biasa\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Buat grup biasa
                subjudul("Buat Grup Biasa");
                $judul = inp("  Judul grup: ");
                if ($judul === '') break;
                $kontak = pilihKontak($c, "Pilih anggota pertama (wajib)");
                if (!$kontak) break;
                $res = coba(fn() => $c->createChat($judul, $kontak['id']));
                if ($res) ok("Grup dibuat: " . ($res['title'] ?? $judul) . " ID=" . ($res['chat_id'] ?? '?'));
                jeda();
                break;

            case '2': // ── Buat supergroup
                subjudul("Buat Supergroup");
                $judul = inp("  Judul supergroup: ");
                if ($judul === '') break;
                $about = inp("  Deskripsi (opsional): ");
                $res = coba(fn() => $c->createChannel($judul, $about, megagroup: true));
                if ($res) ok("Supergroup dibuat: " . ($res['title'] ?? $judul));
                jeda();
                break;

            case '3': // ── Buat channel broadcast
                subjudul("Buat Channel Broadcast");
                $judul = inp("  Judul channel: ");
                if ($judul === '') break;
                $about = inp("  Deskripsi (opsional): ");
                $res = coba(fn() => $c->createChannel($judul, $about, megagroup: false));
                if ($res) ok("Channel dibuat: " . ($res['title'] ?? $judul));
                jeda();
                break;

            case '4': // ── Gabung channel
                subjudul("Gabung Channel");
                $link = inp("  Username atau link (misal: @channel atau t.me/channel): ");
                if ($link === '') break;
                $res = coba(fn() => $c->joinChannel($link));
                if ($res) ok("Berhasil bergabung.");
                jeda();
                break;

            case '5': // ── Keluar channel
                subjudul("Keluar Channel/Supergroup");
                $ch = pilihChannel($c, "Pilih yang akan ditinggalkan");
                if (!$ch) break;
                $res = coba(fn() => $c->leaveChannel($ch['id']));
                if ($res) ok("Berhasil keluar dari: " . ($ch['title'] ?? '?'));
                jeda();
                break;

            case '6': // ── Undang ke channel
                subjudul("Undang Anggota ke Channel/Supergroup");
                $ch = pilihChannel($c, "Pilih channel");
                if (!$ch) break;
                $kontak = pilihKontak($c, "Pilih anggota yang akan diundang");
                if (!$kontak) break;
                $res = coba(fn() => $c->inviteToChannel($ch['id'], $kontak['id']));
                if ($res) ok("Anggota diundang: " . ($kontak['display'] ?? $kontak['first_name']));
                jeda();
                break;

            case '7': // ── Tambah ke grup biasa
                subjudul("Tambah Anggota ke Grup Biasa");
                $grup = pilihGrup($c, "Pilih grup");
                if (!$grup) break;
                $kontak = pilihKontak($c, "Pilih anggota yang akan ditambahkan");
                if (!$kontak) break;
                $res = coba(fn() => $c->addChatUser($grup['id'], $kontak['id']));
                if ($res) ok("Anggota ditambahkan.");
                jeda();
                break;

            case '8': // ── Promosi admin
                subjudul("Promosi Admin");
                $ch = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$ch) break;
                $anggota = pilihAnggotaDariGrup($c, $ch, "Pilih anggota yang akan dipromosi");
                if (!$anggota) break;
                $res = coba(fn() => $c->promoteAdmin($ch['id'], $anggota['id']));
                if ($res) ok("Admin dipromosi: " . ($anggota['display'] ?? $anggota['first_name'] ?? 'ID:' . $anggota['id']));
                jeda();
                break;

            case '9': // ── Turunkan admin
                subjudul("Turunkan Admin");
                $ch = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$ch) break;
                $anggota = pilihAnggotaDariGrup($c, $ch, "Pilih admin yang akan diturunkan");
                if (!$anggota) break;
                $res = coba(fn() => $c->demoteAdmin($ch['id'], $anggota['id']));
                if ($res) ok("Admin diturunkan.");
                jeda();
                break;

            case '10': // ── Ban
                subjudul("Ban Anggota");
                $ch = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$ch) break;
                $anggota = pilihAnggotaDariGrup($c, $ch, "Pilih anggota yang akan di-ban");
                if (!$anggota) break;
                $res = coba(fn() => $c->banUser($ch['id'], $anggota['id']));
                if ($res) ok("Anggota di-ban.");
                jeda();
                break;

            case '11': // ── Unban
                subjudul("Unban Anggota");
                $ch = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$ch) break;
                $anggota = pilihAnggotaDariGrup($c, $ch, "Pilih anggota yang akan di-unban");
                if (!$anggota) break;
                $res = coba(fn() => $c->unbanUser($ch['id'], $anggota['id']));
                if ($res) ok("Anggota di-unban.");
                jeda();
                break;

            case '12': // ── Kick
                subjudul("Kick Anggota");
                $ch = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$ch) break;
                $anggota = pilihAnggotaDariGrup($c, $ch, "Pilih anggota yang akan di-kick");
                if (!$anggota) break;
                $res = coba(fn() => $c->kickUser($ch['id'], $anggota['id']));
                if ($res) ok("Anggota di-kick.");
                jeda();
                break;

            case '13': // ── Export link undangan
                subjudul("Export Link Undangan");
                $dialog = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$dialog) break;
                $res = coba(fn() => $c->exportInviteLink($dialog['id']));
                if ($res) ok("Link undangan: " . ($res['link'] ?? '?'));
                jeda();
                break;

            case '14': // ── Slow mode
                subjudul("Slow Mode");
                $ch = pilihChannel($c, "Pilih supergroup");
                if (!$ch) break;
                echo "  Detik (0=matikan, 10, 30, 60, 300, 900, 3600): ";
                $detik = (int)inp('');
                $res = coba(fn() => $c->toggleSlowMode($ch['id'], $detik));
                if ($res) ok("Slow mode diatur ke $detik detik.");
                jeda();
                break;

            case '15': // ── Edit judul
                subjudul("Edit Judul");
                $dialog = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$dialog) break;
                $judul = inp("  Judul baru: ");
                if ($judul === '') break;
                $res = coba(fn() => $c->editChatTitle($dialog['id'], $judul));
                if ($res) ok("Judul diperbarui.");
                jeda();
                break;

            case '16': // ── Edit deskripsi
                subjudul("Edit Deskripsi");
                $dialog = pilihGrupAtauChannel($c, "Pilih grup/channel");
                if (!$dialog) break;
                $desc = inp("  Deskripsi baru: ");
                $res = coba(fn() => $c->editChatAbout($dialog['id'], $desc));
                if ($res) ok("Deskripsi diperbarui.");
                jeda();
                break;

            case '17': // ── Lihat anggota channel
                subjudul("Anggota Channel");
                $ch = pilihChannel($c, "Pilih channel/supergroup");
                if (!$ch) break;
                $limit = (int)(inp("  Jumlah anggota (Enter=20): ") ?: 20);
                $members = coba(fn() => $c->getChannelMembers($ch['id'], max(1, $limit)));
                if ($members) {
                    foreach ($members as $m) {
                        printf("  %-30s %s\n",
                            substr($m['display'] ?? ($m['first_name'] ?? 'User#' . $m['id']), 0, 30),
                            !empty($m['username']) ? "@{$m['username']}" : "ID:{$m['id']}"
                        );
                    }
                    info("Total: " . count($members) . " anggota.");
                }
                jeda();
                break;

            case '18': // ── Hapus grup/channel
                subjudul("Hapus Grup/Channel");
                $dialog = pilihGrupAtauChannel($c, "Pilih yang akan dihapus");
                if (!$dialog) break;
                $konfirm = inp("  ⚠️  HAPUS PERMANEN '{$dialog['title']}'? Ketik 'HAPUS' untuk konfirmasi: ");
                if ($konfirm === 'HAPUS') {
                    $res = coba(fn() => $c->deleteChat($dialog['id']));
                    if ($res) ok("Dihapus permanen.");
                } else {
                    info("Dibatalkan.");
                }
                jeda();
                break;

            case '19': // ── Lihat anggota grup biasa
                subjudul("Anggota Grup Biasa");
                $grup = pilihGrup($c, "Pilih grup biasa");
                if (!$grup) break;
                echo "  Mengambil daftar anggota...\n";
                $members = coba(fn() => $c->getChatMembers($grup['id']));
                if ($members) {
                    $icons = ['creator' => '👑', 'admin' => '🛡️', 'member' => '👤'];
                    foreach ($members as $m) {
                        $icon = $icons[$m['role']] ?? '👤';
                        printf("  %s  %-30s  %-15s  %s\n",
                            $icon,
                            substr($m['display'], 0, 30),
                            !empty($m['username']) ? "@{$m['username']}" : "ID:{$m['user_id']}",
                            '[' . $m['role'] . ']'
                        );
                    }
                    echo "\n";
                    $roles = array_count_values(array_column($members, 'role'));
                    info(sprintf("Total: %d anggota  |  👑 %d creator  |  🛡️ %d admin  |  👤 %d member",
                        count($members),
                        $roles['creator'] ?? 0,
                        $roles['admin']   ?? 0,
                        $roles['member']  ?? 0
                    ));
                }
                jeda();
                break;

            case '0': return;
        }
    }
}

// ─── 6. Bot & Interaksi ───────────────────────────────────────────────────
function menu_bot(TelegramClient $c): void
{
    while (true) {
        judul("6. Bot & Interaksi");
        echo "  [1]  Mulai bot dengan /start\n";
        echo "  [2]  Klik tombol inline dari pesan\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Start bot
                subjudul("Mulai Bot");
                $bot = inp("  Username bot (misal: @mybot): ");
                if ($bot === '') break;
                $peer = inp("  Di chat mana? (Enter = kirim ke bot langsung): ");
                if ($peer === '') $peer = $bot;
                $param = inp("  Start parameter (opsional): ");
                $res = coba(fn() => $c->startBot($bot, $peer, $param));
                if ($res) ok("Bot dimulai. ID=" . ($res['id'] ?? '?'));
                jeda();
                break;

            case '2': // ── Klik tombol inline
                subjudul("Klik Tombol Inline");
                $dialog = pilihDialog($c, "Pilih chat yang berisi tombol");
                if (!$dialog) break;
                $msgs = coba(fn() => $c->getHistory($dialog['id'], 20));
                if (!$msgs) break;
                $btnMsgs = array_values(array_filter($msgs, fn($m) => !empty($m['reply_markup'])));
                if (empty($btnMsgs)) { info("Tidak ada pesan dengan tombol di riwayat terbaru."); jeda(); break; }
                $items = array_map(fn($m) => [
                    'label' => sprintf("#%d | %s", $m['id'], substr($m['text'] ?? '[no text]', 0, 50)),
                    'data'  => $m,
                ], $btnMsgs);
                $pick = pilihList($items, "Pilih pesan dengan tombol");
                if (!$pick) break;
                $msg = $pick['data'];
                // Tampilkan tombol yang tersedia
                $rows = $msg['reply_markup']['rows'] ?? [];
                $allBtns = [];
                foreach ($rows as $row) {
                    foreach ($row['buttons'] ?? [] as $btn) {
                        $allBtns[] = ['label' => $btn['text'] ?? '?', 'data' => $btn];
                    }
                }
                if (empty($allBtns)) { info("Tidak ada tombol inline ditemukan."); jeda(); break; }
                $btnPick = pilihList($allBtns, "Pilih tombol yang akan diklik");
                if (!$btnPick) break;
                $btn = $btnPick['data'];
                $inputPeer = $c->resolvePeer($dialog['id']);
                $res = coba(fn() => $c->clickButton($inputPeer, $msg['id'], $btn['data'] ?? null));
                if ($res !== null) ok("Tombol diklik. Respons: " . (is_array($res) ? json_encode($res) : $res));
                else info("Tombol diklik (tidak ada respons callback).");
                jeda();
                break;

            case '0': return;
        }
    }
}

// ─── 7. Update & Event ────────────────────────────────────────────────────
function menu_event(TelegramClient $c): void
{
    while (true) {
        judul("7. Update & Event");
        echo "  [1]  Poll sekali (cek update terbaru)\n";
        echo "  [2]  Listen pesan masuk (filter kata kunci)\n";
        echo "  [3]  Listen SEMUA update mentah\n";
        echo "  [4]  Mode Chat Realtime (buka chat + kirim & balas)\n";
        echo "  [0]  Kembali\n\n";

        switch (inp("Pilih: ")) {

            case '1': // ── Poll sekali
                subjudul("Poll Sekali");
                echo "  Menunggu update (timeout 3 detik)...\n";
                $ada = coba(fn() => $c->pollOnce(3));
                if ($ada) ok("Ada update diterima.");
                else       info("Tidak ada update baru.");
                jeda();
                break;

            case '2': // ── Listen dengan filter
                subjudul("Listen Pesan Masuk (Ctrl+C untuk berhenti)");
                $kw = inp("  Filter kata kunci (kosong = semua pesan): ");
                echo "\n  Mendengarkan pesan baru... tekan Ctrl+C untuk berhenti.\n\n";
                $filter = new NewMessage(
                    pattern: $kw !== '' ? $kw : null
                );
                $c->on($filter, function ($event) {
                    $msg = $event->message;
                    printf("  [%s] %s: %s\n",
                        date('H:i:s'),
                        $msg['from_name'] ?? ('ID:' . ($msg['from_id'] ?? '?')),
                        substr($msg['text'] ?? ('[' . ($msg['media']['type'] ?? 'media') . ']'), 0, 80)
                    );
                });
                coba(fn() => $c->runUntilDisconnected());
                break;

            case '3': // ── Listen raw update
                subjudul("Listen Semua Update Mentah (Ctrl+C untuk berhenti)");
                echo "\n  Mendengarkan update... tekan Ctrl+C untuk berhenti.\n\n";
                $c->onUpdate(function ($event) {
                    $detail = '';
                    switch ($event->type) {
                        case 'new_message':
                            $m      = $event->message;
                            $from   = $m->fromUserId ? "dari ID:{$m->fromUserId}" : "(outgoing)";
                            $teks   = substr($m->text ?? '[media/service]', 0, 60);
                            $detail = "$from | \"{$teks}\"";
                            break;
                        case 'edit_message':
                            $m      = $event->message;
                            $detail = "msg_id:{$m->id} teks baru: " . substr($m->text ?? '', 0, 50);
                            break;
                        case 'delete_messages':
                            $detail = "ids: " . implode(',', $event->ids ?? []);
                            break;
                        case 'user_status':
                            $detail = "user_id:{$event->user_id} " . ($event->online ? 'ONLINE' : 'offline');
                            break;
                        case 'read_history':
                            $detail = "max_id:{$event->max_id} arah:{$event->direction}";
                            break;
                        default:
                            $detail = substr(json_encode($event->data), 0, 100);
                    }
                    printf("  [%s] %-20s %s\n", date('H:i:s'), $event->type, $detail);
                    flush();
                });
                coba(fn() => $c->runUntilDisconnected());
                break;

            case '4': // ── Mode Chat Realtime
                chat_realtime($c);
                break;

            case '0': return;
        }
    }
}

// ─── Mode Chat Realtime ───────────────────────────────────────────────────
function chat_realtime(TelegramClient $c): void
{
    judul("Mode Chat Realtime");

    // 1. Pilih chat tujuan
    $dialog = pilihDialog($c, 'Pilih chat yang akan dibuka');
    if (!$dialog) return;

    $peerId   = $dialog['id'];
    $namaPeer = trim(($dialog['title'] ?? '') ?: ($dialog['username'] ?? "ID:$peerId"));

    // Bangun InputPeer menggunakan resolvePeer — peerCache sudah diisi oleh getDialogs
    // di dalam pilihDialog, termasuk batch-fetch access_hash untuk min-user.
    // Jangan bangun manual dengan (int)($dialog['access_hash'] ?? 0) karena null→0 menyebabkan
    // [400] PEER_ID_INVALID untuk user yang access_hash-nya belum tersedia saat parse dialog.
    try {
        $inputPeer = $c->resolvePeer($peerId);
    } catch (\Throwable $e) {
        err("Tidak dapat resolve peer: " . $e->getMessage());
        return;
    }

    // 2. Tampilkan 10 pesan terakhir sebagai konteks
    subjudul("Riwayat 10 Pesan Terakhir — $namaPeer");
    $history = coba(fn() => $c->getHistory($inputPeer, 10));
    if ($history) {
        foreach (array_reverse($history) as $msg) {
            $from = $msg['from_name'] ?? 'Saya';
            $teks = $msg['text'] ?? ('[' . ($msg['media']['type'] ?? 'media') . ']');
            $time = !empty($msg['date']) ? date('H:i:s', (int)$msg['date']) : '--:--:--';
            printf("  [%s] %-20s %s\n", $time, $from . ':', substr($teks, 0, 80));
        }
    } else {
        info("Tidak ada riwayat pesan.");
    }

    echo "\n";
    baris(60, '─');
    echo "  Chat: $namaPeer  |  Perintah:\n";
    echo "  /r [teks]   — balas pesan terakhir yang diterima\n";
    echo "  /quit       — keluar dari mode chat\n";
    baris(60, '─');
    echo "\n";

    // 3. Daftarkan event handler untuk pesan masuk
    //    Bersihkan handler lama agar tidak menumpuk saat mode ini dipanggil ulang
    $c->removeHandlers();

    $lastMsgId   = null;
    $lastMsgFrom = null;
    $inputBuffer = '';

    $c->on(new NewMessage(), function ($event) use (
        $inputPeer, &$lastMsgId, &$lastMsgFrom, &$inputBuffer
    ) {
        $msg  = $event->message;
        $from = $msg['from_name'] ?? ('ID:' . ($msg['from_id'] ?? '?'));
        $teks = $msg['text'] ?? ('[' . ($msg['media']['type'] ?? 'media') . ']');
        $time = date('H:i:s');
        $replyInfo = '';
        if (!empty($msg['reply_to_msg_id'])) {
            $replyInfo = " (↩ #" . $msg['reply_to_msg_id'] . ")";
        }

        // Hapus baris prompt yang sedang aktif, cetak pesan, tulis ulang prompt
        echo "\r\033[K";
        printf("  [%s] %-20s %s%s\n", $time, $from . ':', substr($teks, 0, 80), $replyInfo);

        $lastMsgId   = $msg['id'] ?? null;
        $lastMsgFrom = $from;

        // Tulis ulang prompt + isi buffer yang sedang diketik
        echo "  >> " . $inputBuffer;
        flush();
    });

    // 4. Loop non-blocking: poll update + baca stdin karakter per karakter
    stream_set_blocking(STDIN, false);
    echo "  >> ";
    flush();

    $jalan = true;
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGINT, function () use (&$jalan) { $jalan = false; });
    }

    while ($jalan) {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        // Poll update dari Telegram (non-blocking, timeout=0)
        try { $c->pollOnce(0); } catch (\Throwable) {}

        // Baca satu karakter dari stdin (non-blocking)
        $ch = fgetc(STDIN);
        if ($ch === false) {
            usleep(30_000); // 30ms jeda agar CPU tidak penuh
            continue;
        }

        if ($ch === "\n") {
            // ── Enter: proses perintah / kirim pesan ──────────────────────
            $input = trim($inputBuffer);
            $inputBuffer = '';
            echo "\n";

            if ($input === '/quit' || $input === '/keluar') {
                $jalan = false;
                break;
            }

            if ($input === '') {
                echo "  >> ";
                flush();
                continue;
            }

            // Cek apakah perintah balas (/r teks)
            $replyTo = null;
            if (preg_match('/^\/r\s+(.+)$/su', $input, $m)) {
                if ($lastMsgId !== null) {
                    $input   = $m[1];
                    $replyTo = $lastMsgId;
                } else {
                    err("Belum ada pesan yang diterima untuk dibalas.");
                    echo "  >> ";
                    flush();
                    continue;
                }
            }

            // Kirim pesan
            $res = coba(fn() => $c->sendMessage($inputPeer, $input, $replyTo));
            if ($res) {
                $timeStr = date('H:i:s');
                $replyInfo = $replyTo ? " (↩ #$replyTo {$lastMsgFrom})" : '';
                printf("  [%s] %-20s %s%s\n", $timeStr, 'Saya:', $input, $replyInfo);
            }

            echo "  >> ";
            flush();

        } elseif ($ch === "\x7f" || $ch === "\x08") {
            // ── Backspace ─────────────────────────────────────────────────
            if ($inputBuffer !== '') {
                $inputBuffer = mb_substr($inputBuffer, 0, -1);
                echo "\x08 \x08";
                flush();
            }

        } elseif ($ch === "\x03") {
            // ── Ctrl+C eksplisit (jika pcntl tidak tersedia) ──────────────
            $jalan = false;
            break;

        } elseif (ord($ch) >= 32 || $ch === "\t") {
            // ── Karakter biasa: tambahkan ke buffer dan echo ───────────────
            $inputBuffer .= $ch;
            echo $ch;
            flush();
        }
    }

    // Kembalikan stdin ke mode blocking
    stream_set_blocking(STDIN, true);

    echo "\n\n";
    ok("Keluar dari mode chat realtime.");
    jeda();
}

// ══════════════════════════════════════════════════════════════════════════════
// MENU UTAMA
// ══════════════════════════════════════════════════════════════════════════════

while (true) {
    echo "\n";
    baris(60, '═');
    echo "  MENU UTAMA — XNOXSPROTO TESTER\n";
    baris(60, '═');
    echo "  [1]  Manajemen Akun\n";
    echo "  [2]  Pesan & Chat\n";
    echo "  [3]  Media\n";
    echo "  [4]  Kontak & Dialog\n";
    echo "  [5]  Grup & Channel\n";
    echo "  [6]  Bot & Interaksi\n";
    echo "  [7]  Update & Event\n";
    baris(60, '─');
    echo "  [0]  Keluar\n";
    baris(60, '═');
    echo "\n";

    switch (inp("Pilih menu: ")) {
        case '1': menu_akun($client, $sessionsDir, $sessionFile ?? ''); break;
        case '2': menu_pesan($client);                           break;
        case '3': menu_media($client, $ASSET_PHOTO, $ASSET_DOC, $ASSET_AUDIO); break;
        case '4': menu_kontak($client);                          break;
        case '5': menu_grup($client);                            break;
        case '6': menu_bot($client);                             break;
        case '7': menu_event($client);                           break;
        case '0':
            echo "\n  Disconnecting...\n";
            coba(fn() => $client->disconnect());
            echo "  Sampai jumpa!\n\n";
            exit(0);
        default:
            err("Pilihan tidak valid.");
    }
}
