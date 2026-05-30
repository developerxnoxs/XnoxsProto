# Dokumentasi XnoxsProto — PHP MTProto Library

> Library PHP untuk berkomunikasi langsung dengan server Telegram menggunakan protokol MTProto.  
> Terinspirasi dari Telethon (Python), dirancang dengan API yang bersih dan mudah digunakan.

---

## Daftar Isi

1. [Persiapan & Instalasi](#1-persiapan--instalasi)
2. [Login](#2-login)
3. [Manajemen Session](#3-manajemen-session)
4. [Get Contact](#4-get-contact)
5. [Join & Leave Channel](#5-join--leave-channel)
6. [Kirim Pesan](#6-kirim-pesan)
7. [Kirim Media (Foto, Video, Audio, File)](#7-kirim-media-foto-video-audio-file)
8. [Interaksi Tombol Inline (Click Button)](#8-interaksi-tombol-inline-click-button)
9. [Get History](#9-get-history)
10. [Get Dialog](#10-get-dialog)
11. [Forward Message](#11-forward-message)
12. [Event Handler (Real-time)](#12-event-handler-real-time)
13. [Referensi Lengkap](#13-referensi-lengkap)
14. [startBot() — Mulai & Interaksi dengan Bot](#14-startbot--mulai--interaksi-dengan-bot)
15. [Skenario Automation Lengkap](#15-skenario-automation-lengkap)
16. [Kirim Voice Note & Poll](#16-kirim-voice-note--poll)
17. [Pin & Unpin Pesan](#17-pin--unpin-pesan)
18. [Manajemen Admin & Ban](#18-manajemen-admin--ban) — supergroup, channel, **dan grup biasa**
19. [Cari Pesan (Search)](#19-cari-pesan-search)
20. [Info Lengkap User / Chat / Channel](#20-info-lengkap-user--chat--channel)
21. [Daftar Channel Admin (getAdminChannels)](#21-daftar-channel-admin-getadminchannels)
22. [Daftar Anggota Channel / Grup (getChannelMembers / getChatMembers)](#22-daftar-anggota-channel--grup-getchannelmembers--getchatmembers)
23. [Manajemen Akun (Account Module)](#23-manajemen-akun-account-module)
24. [Download Media (FileDownloader)](#24-download-media-filedownloader) — DC migration, FILE_REFERENCE_EXPIRED auto-refresh, progress % nyata
25. [Edit & Hapus Pesan](#25-edit--hapus-pesan)
26. [Proxy SOCKS5](#26-proxy-socks5)
27. [Resolve Peer & Username](#27-resolve-peer--username)
28. [Status Koneksi & Info](#28-status-koneksi--info)
29. [Raw Update Handler (onUpdate)](#29-raw-update-handler-onupdate)
30. [Catatan Kompatibilitas Layer 214](#30-catatan-kompatibilitas-layer-214)
31. [Pengaturan Privasi (Account Privacy)](#31-pengaturan-privasi-account-privacy)
32. [Manajemen Grup, Supergroup & Channel](#32-manajemen-grup-supergroup--channel) — createChannel, deleteChat, editChatTitle, toggleSignatures, toggleSlowMode, setDefaultPermissions, toggleJoinToSend, toggleJoinRequest, exportInviteLink
33. [Script Uji Interaktif (xnoxs_tester.php)](#33-script-uji-interaktif-xnoxs_testerphp) — menu CLI lengkap, pilih peer dari daftar, semua fitur library

---

## 1. Persiapan & Instalasi

### Prasyarat

- PHP **8.2+**
- Ekstensi wajib: `gmp`, `openssl`, `mbstring`, `json`
- `curl` disarankan (tidak wajib)

```bash
php -m | grep -E 'gmp|openssl|mbstring|json|curl'
```

### Dapatkan API ID & API Hash

1. Buka [https://my.telegram.org/apps](https://my.telegram.org/apps)
2. Login dengan nomor telepon Telegram kamu
3. Buat aplikasi baru → catat **API ID** dan **API Hash**

### Struktur Dasar

```php
<?php
require_once 'src/autoload.php'; // sesuaikan path autoloader

use XnoxsProto\Client\TelegramClient;

$apiId   = 123456;           // Ganti dengan API ID kamu
$apiHash = 'abc123def456';   // Ganti dengan API Hash kamu

$client = new TelegramClient($apiId, $apiHash, 'nama_session');
```

---

## 2. Login

Library mendukung dua cara login: **otomatis** (`start()`) dan **manual** (sendCode + signIn).

### 2.1 Login Otomatis (Direkomendasikan)

Metode `start()` menangani semua langkah login secara otomatis, termasuk 2FA. Jika session sudah ada, langsung lanjut tanpa login ulang.

```php
<?php
require_once 'src/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(123456, 'abc123def456', 'my_session');

// Jika belum login, akan meminta kode via stdin secara otomatis
$client->start('+6281234567890');

$me = $client->getMe();
echo "Login sebagai: " . $me['first_name'] . " (@" . ($me['username'] ?? 'tanpa username') . ")\n";
echo "User ID: " . $me['id'] . "\n";
echo "Premium : " . ($me['premium'] ? 'Ya' : 'Tidak') . "\n";
```

**Return value `getMe()`:**
```php
[
    'id'         => 123456789,
    'first_name' => 'Budi',
    'last_name'  => 'Santoso',
    'username'   => 'budisantoso',     // null jika tidak ada
    'phone'      => '+6281234567890',
    'bot'        => false,
    'verified'   => false,
    'premium'    => true,
]
```

**Dengan callback kode kustom** (untuk aplikasi non-interaktif):

```php
$client->start('+6281234567890', function () {
    echo "Masukkan kode Telegram: ";
    return trim(fgets(STDIN));
});
```

**Dengan callback kode + callback 2FA** (untuk akun dengan Two-Step Verification):

```php
$client->start(
    '+6281234567890',
    codeCallback: function () {
        echo "Masukkan kode OTP: ";
        return trim(fgets(STDIN));
    },
    passwordCallback: function () {
        echo "Masukkan password 2FA: ";
        return trim(fgets(STDIN));
    }
);
```

Jika `passwordCallback` tidak disediakan dan akun memiliki 2FA, library akan otomatis meminta password via `STDIN`.

**Signature lengkap `start()`:**
```php
start(
    string    $phone            = '',       // Nomor telepon (contoh: '+6281234567890')
    ?callable $codeCallback     = null,     // fn() → string kode OTP
    ?callable $passwordCallback = null,     // fn() → string password 2FA
    string    $botToken         = ''        // Bot token dari @BotFather (alternatif phone)
): void
```

### 2.1.1 Login sebagai Bot

Gunakan `start(botToken: ...)` untuk login sebagai bot:

```php
$client = new TelegramClient(API_ID, API_HASH, 'my_bot');
$client->start(botToken: '123456789:ABCDefGhIJKlmNOPqrSTUVwxYZ');

$me = $client->getMe();
echo "Login sebagai bot: @" . $me['username'] . "\n";
```

Atau gunakan method manual:

```php
$result = $client->getAuth()->loginAsBot('123456789:ABCDefGhIJKlmNOPqrSTUVwxYZ');
echo "Bot ID   : " . $result['user']['id'] . "\n";
echo "Username : @" . $result['user']['username'] . "\n";
```

### 2.2 Login Manual (Langkah per Langkah)

```php
$client = new TelegramClient(123456, 'abc123def456', 'my_session');
$auth   = $client->getAuth();

// Langkah 1: Kirim kode verifikasi ke nomor telepon
$sentCode = $auth->sendCode('+6281234567890');
echo "Tipe pengiriman: " . $sentCode['type'] . "\n"; // 'app' atau 'sms'

// Langkah 2: Masukkan kode yang diterima
echo "Masukkan kode: ";
$code = trim(fgets(STDIN));

// Langkah 3: Sign in
$result = $auth->signIn('+6281234567890', $sentCode['phone_code_hash'], $code);
echo "Berhasil login! ID: " . $result['user']['id'] . "\n";
```

**Return value `sendCode()`:**
```php
[
    'phone_number'    => '+6281234567890',
    'phone_code_hash' => 'abc123...',   // dibutuhkan di signIn()
    'type'            => 'app',          // 'app' = via Telegram app, 'sms' = via SMS
]
```

**Return value `signIn()`:**
```php
[
    'user' => [
        'id'         => 123456789,
        'first_name' => 'Budi',
        'last_name'  => 'Santoso',
        'username'   => 'budisantoso',
        'phone'      => '+6281234567890',
        'authorized' => true,
    ]
]
```

### 2.3 Cek Status Login

```php
$auth = $client->getAuth();

if ($auth->isAuthorized()) {
    echo "Sudah login\n";
} else {
    echo "Belum login\n";
}
```

### 2.4 Logout

```php
$client->getAuth()->logOut();
echo "Berhasil logout\n";
```

### 2.5 DC Migration Otomatis

Library secara otomatis menangani perpindahan DC (Data Center) Telegram. Jika server mengembalikan error `PHONE_MIGRATE_X` atau `USER_MIGRATE_X`, library akan otomatis reconnect ke DC yang benar tanpa intervensi manual.

### 2.6 Cek Info 2FA

```php
$auth = $client->getAuth();
$info = $auth->getPasswordInfo();

echo "Punya password: " . ($info['has_password'] ? 'Ya' : 'Tidak') . "\n";
echo "Hint          : " . ($info['hint'] ?? '-') . "\n";
echo "Ada recovery  : " . ($info['has_recovery'] ? 'Ya' : 'Tidak') . "\n";
```

---

## 3. Manajemen Session

Session menyimpan auth key, DC info, dan status login sehingga kamu tidak perlu login ulang setiap kali script dijalankan.

### 3.1 FileSession (Persisten — Direkomendasikan)

Data disimpan ke file binary terenkripsi di disk. Bertahan setelah restart script.

```php
use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;

// Cara 1: Lewatkan nama sesi (otomatis buat file "akun_saya.session")
$client = new TelegramClient($apiId, $apiHash, 'akun_saya');

// Cara 2: Lewatkan objek FileSession secara eksplisit
$session = new FileSession('path/ke/akun_saya.session');
$client  = new TelegramClient($apiId, $apiHash, $session);
```

### 3.2 MemorySession (Sementara)

Data hanya ada selama script berjalan. Hilang saat script selesai.

```php
use XnoxsProto\Sessions\MemorySession;

$session = new MemorySession();
$client  = new TelegramClient($apiId, $apiHash, $session);

// atau lewatkan null untuk MemorySession otomatis
$client = new TelegramClient($apiId, $apiHash, null);
```

### 3.3 Operasi Session

```php
$session = $client->getSession();

// Cek apakah sudah login
$session->isUserAuthorized(); // bool

// Dapatkan User ID yang sedang login
$session->getUserId(); // ?int

// Simpan session secara manual
$session->save();

// Hapus semua data session
$session->delete();
```

### 3.4 Multi-Akun

```php
$client1 = new TelegramClient($apiId, $apiHash, 'akun_pertama');
$client2 = new TelegramClient($apiId, $apiHash, 'akun_kedua');

$client1->start('+6281111111111');
$client2->start('+6282222222222');
```

---

## 4. Get Contact

Ambil daftar kontak yang tersimpan di akun Telegram.

```php
$contacts = $client->getContacts();

foreach ($contacts as $contact) {
    echo "Nama     : " . $contact['display'] . "\n";
    echo "ID       : " . $contact['id'] . "\n";
    echo "Username : @" . ($contact['username'] ?? '-') . "\n";
    echo "Telepon  : " . ($contact['phone'] ?? '-') . "\n";
    echo "Mutual   : " . ($contact['mutual'] ? 'Ya' : 'Tidak') . "\n";
    echo "Bot      : " . ($contact['bot'] ? 'Ya' : 'Tidak') . "\n";
    echo "---\n";
}
```

**Return value — array of:**
```php
[
    'id'          => 123456789,
    'access_hash' => 9876543210,
    'first_name'  => 'Budi',
    'last_name'   => 'Santoso',
    'username'    => 'budisantoso',   // null jika tidak ada
    'phone'       => '+6281234567890',
    'mutual'      => true,             // true = saling kontak
    'bot'         => false,
    'display'     => 'Budi Santoso',  // nama lengkap untuk display
]
```

---

## 5. Join & Leave Channel

### 5.1 Join Channel

```php
// Dengan @username
$result = $client->joinChannel('@nama_channel');

// Dengan link t.me
$result = $client->joinChannel('t.me/nama_channel');

// Link invite private
$result = $client->joinChannel('https://t.me/joinchat/AbCdEfGhIjKlMn');

// Link invite format baru
$result = $client->joinChannel('https://t.me/+AbCdEfGhIjKlMn');

echo $result['joined'] ? "Berhasil join!\n" : "Gagal\n";
```

**Return value:**
```php
// Via username:
['joined' => true, 'peer' => '@nama_channel']

// Via invite link:
['joined' => true, 'via' => 'invite_link']
```

### 5.2 Leave Channel

```php
$result = $client->leaveChannel('@nama_channel');
echo $result['left'] ? "Berhasil keluar!\n" : "Gagal\n";
// Returns: ['left' => true, 'peer' => '@nama_channel']
```

---

## 6. Kirim Pesan

### 6.1 Kirim ke Username

```php
$result = $client->sendMessage('@username_tujuan', 'Halo dari XnoxsProto!');
echo "Message ID : " . $result['message_id'] . "\n";
echo "Waktu      : " . date('Y-m-d H:i:s', $result['date']) . "\n";
```

### 6.2 Berbagai Format Peer

```php
$client->sendMessage('+6281234567890', 'Halo via nomor HP!');
$client->sendMessage('me', 'Catatan untuk diri sendiri');
$client->sendMessage(123456789, 'Halo via ID!');
$client->sendMessage(-100123456789, 'Ke channel/supergroup via ID Bot API');
$client->sendMessage('@grupkita', 'Pesan ke grup');
```

### 6.3 Kirim dengan Reply (Balas Pesan)

```php
$msgId  = 42; // ID pesan yang ingin dibalas
$result = $client->sendMessage('@username', 'Ini balasan!', replyTo: $msgId);
```

### 6.4 Kirim dengan InputPeer Langsung (Low-level)

```php
use XnoxsProto\TL\Types\InputPeer;

$peer   = InputPeer::user(123456789, 987654321);
$result = $client->sendMessage($peer, 'Pesan ke user');

$peer   = InputPeer::chat(123456789);
$result = $client->sendMessage($peer, 'Pesan ke grup');

$peer   = InputPeer::channel(123456789, 987654321);
$result = $client->sendMessage($peer, 'Pesan ke channel');

$peer   = InputPeer::self();
$result = $client->sendMessage($peer, 'Ke saved messages');
```

**Return value `sendMessage()`:**
```php
[
    'sent'       => true,
    'message_id' => 12345,
    'date'       => 1700000000,
    'peer_type'  => 'user',     // 'user'|'chat'|'channel'
    'peer_id'    => 123456789,
]
```

---

## 7. Kirim Media (Foto, Video, Audio, File)

Library mendukung upload dan pengiriman berbagai jenis media langsung dari file lokal. Upload dilakukan secara chunked (512 KB per chunk) sesuai protokol MTProto, dan mendukung file besar (big file mode otomatis untuk file ≥ 10 MB).

### 7.1 `sendFile()` — Auto-detect Tipe

`sendFile()` otomatis mendeteksi tipe media berdasarkan ekstensi file:

```php
// JPG/PNG/WebP → dikirim sebagai FOTO (tampil inline)
$result = $client->sendFile('@username', '/path/foto.jpg', caption: 'Foto keren!');

// MP4/MOV/AVI  → dikirim sebagai VIDEO (player inline)
$result = $client->sendFile('@username', '/path/video.mp4', caption: 'Video nih');

// MP3/OGG/FLAC → dikirim sebagai AUDIO (player audio)
$result = $client->sendFile('@username', '/path/lagu.mp3', caption: 'Dengerin ini');

// PDF/ZIP/APK  → dikirim sebagai DOKUMEN (ikon file)
$result = $client->sendFile('@username', '/path/laporan.pdf', caption: 'Laporan');

echo "Terkirim! ID: " . $result['message_id'] . "\n";
```

**Paksa kirim sebagai dokumen:**
```php
$result = $client->sendFile('@username', '/path/gambar.png', forceDocument: true);
```

**Return value `sendFile()`:**
```php
[
    'sent'       => true,
    'message_id' => 12345,
    'date'       => 1700000000,
    'caption'    => 'Caption teks',
    'type'       => 'photo',    // 'photo' | 'video' | 'audio' | 'document'
    'mime'       => 'image/jpeg',
    'filename'   => 'foto.jpg',
]
```

### 7.2 Kirim Foto

```php
// Paling sederhana
$result = $client->sendPhoto('@username', '/path/foto.jpg');

// Dengan caption dan reply
$result = $client->sendPhoto('@username', '/path/foto.jpg',
    caption: 'Ini foto saya!',
    replyTo: 42
);

// Dengan progress upload
$result = $client->sendPhoto('@username', '/path/foto-besar.jpg',
    caption: 'Upload foto...',
    onProgress: function (int $part, int $total, int $pct) {
        echo "Upload: $pct% ($part/$total chunk)\n";
    }
);
```

**Format yang didukung sebagai foto:** `jpg`, `jpeg`, `png`, `webp`

### 7.3 Kirim Video

```php
$result = $client->sendVideo('@username', '/path/video.mp4',
    caption:    'Video tutorial',
    duration:   120.5,   // detik
    width:      1920,
    height:     1080,
    onProgress: fn($p, $t, $pct) => print("Video upload: $pct%\r")
);
```

**Format yang didukung sebagai video:** `mp4`, `mov`, `avi`, `mkv`, `webm`, `flv`

### 7.4 Kirim Audio

```php
$result = $client->sendAudio('@username', '/path/lagu.mp3',
    caption:   'Dengerin nih!',
    duration:  237,
    title:     'Bohemian Rhapsody',
    performer: 'Queen',
);
```

**Format yang didukung sebagai audio:** `mp3`, `ogg`, `oga`, `flac`, `wav`, `m4a`, `aac`, `opus`

### 7.5 Kirim Dokumen / File

```php
// File PDF dengan nama kustom
$result = $client->sendDocument('@username', '/path/file_123abc.pdf',
    caption:  'Dokumen resmi',
    filename: 'Laporan-Keuangan-2024.pdf'
);

// Dengan progress
$result = $client->sendDocument('@username', '/path/besar.zip',
    caption:    'File besar',
    onProgress: function (int $part, int $total, int $pct) {
        echo "\rProgress: $pct% ($part/$total)";
    }
);
```

### 7.6 Kirim Voice Note

```php
$result = $client->sendVoice('@username', '/path/suara.ogg', duration: 15);
```

Format yang direkomendasikan: `.ogg` (Opus codec).

### 7.7 Progress Upload

Semua method menerima parameter `onProgress`:

```php
$client->sendFile('@username', '/path/file-besar.zip',
    caption:    'File 500 MB',
    onProgress: function (int $part, int $total, int $percent) {
        echo "\r  Upload: [{$percent}%] chunk {$part}/{$total}";
        if ($percent === 100) echo "\n";
    }
);
```

### 7.8 Tabel Dukungan Format File

| Format | Ekstensi | Cara Kirim | Keterangan |
|--------|----------|------------|------------|
| **Foto** | jpg, jpeg, png, webp | Inline photo | Tampil langsung di chat |
| **Video** | mp4, mov, avi, mkv, webm | Video player | Putar inline |
| **Audio** | mp3, ogg, flac, wav, m4a, aac | Audio player | Tampil sebagai musik |
| **Voice** | ogg (opus) | Voice note | Tampil sebagai waveform suara |
| **PDF** | pdf | Dokumen | Preview tersedia di Telegram |
| **Arsip** | zip, rar, 7z, tar, gz | Dokumen | — |
| **Office** | doc, docx, xls, xlsx, ppt, pptx | Dokumen | — |
| **Lainnya** | \* | Dokumen | Semua ekstensi lain otomatis jadi dokumen |

> **Batas ukuran:** File < 10 MB menggunakan `upload.saveFilePart` (small). File ≥ 10 MB menggunakan `upload.saveBigFilePart` (big file mode) — keduanya otomatis dipilih oleh library.

---

## 8. Interaksi Tombol Inline (Click Button)

### 8.1 Click Button via Event Handler (Paling Mudah)

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client->on(new NewMessage(incoming: true), function (NewMessageEvent $event) {
    $msg = $event->message;

    if ($msg->replyMarkup !== null && !empty($msg->replyMarkup['rows'])) {
        $rows = $msg->replyMarkup['rows'];

        // Tampilkan semua tombol
        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $button) {
                echo "Tombol [{$rowIdx}][{$colIdx}]: " . $button['text'] . "\n";
            }
        }

        // Click berdasarkan posisi (baris 0, kolom 0)
        $result = $msg->click(0, 0);

        // Click berdasarkan label teks — exact match (case-sensitive)
        $result = $msg->click('📖 Bantuan');

        // Jika exact match gagal → fallback partial match (case-insensitive)
        $result = $msg->click('bantuan'); // cocok dengan '📖 Bantuan'
    }
});

$client->runUntilDisconnected();
```

**Urutan pencarian `click(string $label)`:**
1. **Exact match** — teks identik persis
2. **Partial match** — `mb_strpos` case-insensitive
3. Jika tidak ditemukan → throw `RuntimeException`

### 8.2 Click Button via `clickButton()` (Low-level)

```php
use XnoxsProto\TL\Types\InputPeer;

$peer  = InputPeer::channel(123456789, 987654321);
$msgId = 42;
$data  = 'payload'; // dari replyMarkup['rows'][x][y]['data']

$result = $client->clickButton($peer, $msgId, $data);
// Returns: ['clicked' => true, 'constructor' => '0x...']
```

### 8.3 Baca URL Tombol Tanpa Klik

```php
$url  = $event->message->getButtonUrl(0, 0);   // row=0, col=0
$text = $event->message->getButtonText(0, 0);

echo "Teks  : $text\n";
echo "URL   : $url\n";
```

### 8.4 Struktur `replyMarkup`

```php
[
    'rows' => [
        // Baris 0
        [
            ['text' => 'Tombol 1', 'data' => 'btn_1', 'type' => 'callback'],
            ['text' => 'Tombol 2', 'url'  => 'https://example.com', 'type' => 'url'],
        ],
        // Baris 1
        [
            ['text' => 'Tombol 3', 'data' => 'btn_3', 'type' => 'callback'],
        ],
    ]
]
```

**Tipe tombol yang tersedia:**
- `callback` — klik dikirim ke bot sebagai `getBotCallbackAnswer`
- `url` — tombol link, membuka URL
- `game` — tombol game inline

---

## 9. Get History

Ambil riwayat pesan dari sebuah chat, grup, atau channel.

### 9.1 Dasar

```php
$messages = $client->getHistory('@username', limit: 20);

foreach ($messages as $msg) {
    $waktu = date('d/m/Y H:i', $msg['date']);
    $arah  = $msg['out'] ? '→ (kita kirim)' : '← (diterima)';
    echo "[$waktu] $arah {$msg['text']}\n";
}
```

> **Penting:** `getHistory()` mengembalikan flat array langsung — **bukan** `['messages' => [...]]`.
> Iterasi langsung atas hasil `getHistory()`.

### 9.2 Dari Berbagai Jenis Peer

```php
$messages = $client->getHistory('@durov', limit: 10);
$messages = $client->getHistory('+6281234567890', limit: 10);
$messages = $client->getHistory(123456789, limit: 10);
$messages = $client->getHistory('me', limit: 10);
$messages = $client->getHistory(-100123456789, limit: 10);
```

### 9.3 Dengan Pagination & Filter

```php
// Ambil 50 pesan, mulai dari pesan ID tertentu (ke bawah)
$messages = $client->getHistory('@username',
    limit:    50,
    offsetId: 1000,  // mulai dari pesan ID 1000 ke bawah
);
```

### 9.4 Tampilkan dengan Tombol Inline

```php
$messages = $client->getHistory('@nama_bot', limit: 10);

foreach ($messages as $msg) {
    echo "ID: {$msg['id']} | {$msg['text']}\n";

    if (!empty($msg['reply_markup']['rows'])) {
        foreach ($msg['reply_markup']['rows'] as $ri => $row) {
            foreach ($row as $ci => $btn) {
                echo "  Tombol [{$ri}][{$ci}]: {$btn['text']}\n";
            }
        }
    }
    echo "\n";
}
```

**Return value — array of:**
```php
[
    'id'           => 12345,
    'date'         => 1700000000,
    'text'         => 'Isi pesan',
    'out'          => false,
    'from_id'      => 123456789,
    'type'         => 'message',   // 'message', 'service', atau 'empty'
    'reply_markup' => [            // null jika tidak ada tombol
        'rows' => [
            [['text' => 'Tombol', 'data' => 'btn', 'type' => 'callback']],
        ]
    ],
    'media'        => null,        // array jika ada media
]
```

---

## 10. Get Dialog

Ambil daftar semua percakapan (DM, grup, channel) yang ada di akun.

### 10.1 Dasar

```php
$dialogs = $client->getDialogs(limit: 50);

foreach ($dialogs as $dialog) {
    $tipe    = strtoupper($dialog['type']); // USER, CHAT, atau CHANNEL
    $nama    = $dialog['title'];
    $unread  = $dialog['unread_count'];

    echo "[$tipe] $nama\n";
    echo "  Belum dibaca: $unread\n";
    echo "  Pesan terakhir ID: {$dialog['top_message']}\n";
    echo "\n";
}
```

### 10.2 Ambil Semua Dialog

```php
$dialogs = $client->getDialogs(limit: 500, allPages: true);
echo "Total dialog: " . count($dialogs) . "\n";
```

### 10.3 Filter berdasarkan Tipe

> **Penting — Tiga tipe saja:**  
> Library mengembalikan tiga nilai `type`: `'user'`, `'chat'`, `'channel'`.  
> Supergroup dan broadcast channel **keduanya bertipe `'channel'`** — dibedakan via flag `is_supergroup` dan `is_channel`.

```php
$dialogs = $client->getDialogs(limit: 100);

// DM dengan manusia biasa
$users = array_filter($dialogs, fn($d) => $d['type'] === 'user' && !$d['bot']);

// Bot (DM dengan bot)
$bots = array_filter($dialogs, fn($d) => $d['type'] === 'user' && $d['bot']);

// Grup biasa (basic group, maks 200 anggota)
$chats = array_filter($dialogs, fn($d) => $d['type'] === 'chat');

// Supergroup
$supergroups = array_filter($dialogs, fn($d) => $d['type'] === 'channel' && $d['is_supergroup']);

// Channel broadcast
$channels = array_filter($dialogs, fn($d) => $d['type'] === 'channel' && $d['is_channel']);
```

**Ringkasan tipe:**

| `type` | `bot` | `is_supergroup` | `is_channel` | Artinya |
|--------|-------|-----------------|--------------|---------|
| `user` | false | — | — | DM dengan pengguna biasa |
| `user` | true  | — | — | DM dengan bot |
| `chat` | — | false | false | Grup biasa (≤200 anggota) |
| `channel` | — | true | false | Supergroup (grup besar) |
| `channel` | — | false | true | Channel broadcast |

### 10.4 Return Value Lengkap

```php
// Tipe: user (DM)
[
    'type'           => 'user',
    'id'             => 123456789,
    'access_hash'    => 987654321,
    'title'          => 'Budi Santoso',
    'username'       => 'budisantoso',
    'phone'          => '+6281234567890',
    'bot'            => false,
    'unread_count'   => 3,
    'top_message'    => 999,    // ID pesan terakhir
    'is_channel'     => false,
    'is_supergroup'  => false,
]

// Tipe: channel (channel broadcast atau supergroup)
[
    'type'           => 'channel',
    'id'             => 123456789,
    'access_hash'    => 987654321,
    'title'          => 'Nama Channel',
    'username'       => 'nama_channel',
    'unread_count'   => 5,
    'top_message'    => 200,
    'is_channel'     => true,   // true = broadcast channel
    'is_supergroup'  => false,  // true = supergroup
]
```

---

## 11. Forward Message

Forward (teruskan) satu atau beberapa pesan ke peer lain.

### 11.1 Forward Satu Pesan

```php
$result = $client->forwardMessages(
    to:   '@channel_tujuan',
    ids:  [42],
    from: '@channel_asal'
);
echo $result['forwarded'] ? "Berhasil diforward!\n" : "Gagal\n";
```

### 11.2 Forward Beberapa Pesan Sekaligus

```php
$result = $client->forwardMessages(
    to:   '@tujuan',
    ids:  [10, 11, 12, 15],
    from: '@asal'
);
echo "Diforward " . count($result['ids']) . " pesan\n";
```

### 11.3 Forward Tanpa Atribusi (Anonymous Forward)

```php
$result = $client->forwardMessages(
    to:         '@tujuan',
    ids:        [42],
    from:       '@asal',
    dropAuthor: true    // sembunyikan pengirim asli
);
```

**Return value:**
```php
[
    'forwarded' => true,
    'ids'       => [42, 43],
]
```

---

## 12. Event Handler (Real-time)

### 12.1 Handler Dasar

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client->on(new NewMessage(incoming: true), function (NewMessageEvent $event) use ($client) {
    echo "Pesan baru dari: " . $event->message->fromUserId . "\n";
    echo "Isi: " . $event->rawText . "\n";
});

$client->runUntilDisconnected();
```

### 12.2 Filter Pesan Masuk/Keluar

```php
// Hanya pesan masuk (dari orang lain)
$client->on(new NewMessage(incoming: true), function (NewMessageEvent $event) {
    echo "Pesan masuk: " . $event->rawText . "\n";
});

// Hanya pesan keluar (yang kita kirim)
$client->on(new NewMessage(incoming: false), function (NewMessageEvent $event) {
    echo "Pesan keluar: " . $event->rawText . "\n";
});

// Semua pesan (masuk dan keluar)
$client->on(new NewMessage(), function (NewMessageEvent $event) {
    $arah = $event->isIncoming ? "←" : "→";
    echo "$arah " . $event->rawText . "\n";
});
```

### 12.3 Filter dari Peer Tertentu

```php
// Hanya dari bot/user tertentu
$client->on(new NewMessage(fromUsers: '@nama_bot'), function (NewMessageEvent $event) use ($client) {
    echo "Pesan dari bot: " . $event->rawText . "\n";
    $client->sendMessage('@nama_bot', 'Diterima!');
});

// Dari beberapa peer sekaligus
$client->on(new NewMessage(fromUsers: ['@bot1', '@bot2', 123456789]), function (NewMessageEvent $event) {
    echo "Pesan dari salah satu target: " . $event->rawText . "\n";
});
```

### 12.4 Filter berdasarkan Kata Kunci

```php
$client->on(new NewMessage(pattern: 'halo'), function (NewMessageEvent $event) {
    echo "Ada yang bilang halo!\n";
});
```

### 12.5 Kombinasi Filter

```php
$client->on(
    new NewMessage(fromUsers: '@mybot', incoming: true, pattern: 'berhasil'),
    function (NewMessageEvent $event) {
        echo "Bot mengatakan berhasil!\n";
        if ($event->message->replyMarkup !== null) {
            $event->message->click(0, 0);
        }
    }
);
```

### 12.6 Akses Informasi Sender

```php
$client->on(new NewMessage(incoming: true), function (NewMessageEvent $event) {
    $sender = $event->getSender(); // ?User
    if ($sender !== null) {
        echo "Pengirim: " . $sender->getDisplayName() . "\n";
        echo "ID: " . $sender->id . "\n";
    }

    $chat = $event->getChat(); // ?Chat — jika pesan dari grup/channel
    if ($chat !== null) {
        echo "Di grup: " . $chat->getDisplayName() . "\n";
    }

    echo "Message ID   : " . $event->message->id . "\n";
    echo "Peer type    : " . $event->message->peerType . "\n";
    echo "Peer ID      : " . $event->message->peerId . "\n";
    echo "Waktu        : " . date('H:i:s', $event->message->date) . "\n";
});
```

### 12.7 Poll Manual (Non-blocking)

```php
// Cek satu update dengan timeout (detik)
$got = $client->pollOnce(timeoutSeconds: 1);

// Polling dalam loop kustom
while (true) {
    $client->pollOnce(1);
    // lakukan pekerjaan lain di sini...
}
```

### 12.8 Stop Event Loop

```php
$count = 0;
$client->on(new NewMessage(incoming: true), function (NewMessageEvent $event) use ($client, &$count) {
    $count++;
    echo "Pesan ke-$count: " . $event->rawText . "\n";

    if ($count >= 5) {
        $client->disconnect(); // stop loop setelah 5 pesan
    }
});

$client->runUntilDisconnected();
```

### 12.9 Properti `NewMessageEvent`

| Properti               | Tipe         | Keterangan                                       |
|------------------------|--------------|--------------------------------------------------|
| `$event->rawText`      | `string`     | Teks pesan mentah                                |
| `$event->message`      | `FullMessage`| Objek pesan lengkap (mendukung `click()`)        |
| `$event->isIncoming`   | `bool`       | `true` jika pesan dari orang lain                |
| `$event->isOutgoing`   | `bool`       | `true` jika pesan dikirim oleh kita              |
| `$event->users`        | `User[]`     | Map user yang hadir dalam update ini             |
| `$event->chats`        | `Chat[]`     | Map chat yang hadir dalam update ini             |
| `$event->originalUpdate` | `array`    | Raw update array                                 |

---

## 13. Referensi Lengkap

### TelegramClient — Semua Method

| Method | Deskripsi | Section |
|--------|-----------|---------|
| `connect()` | Konek ke Telegram DC | 1, 28 |
| `disconnect()` | Putus koneksi | 28 |
| `isConnected()` | Cek status koneksi | 28 |
| `getLayer()` | Dapatkan API layer yang dinegosiasikan | 28 |
| `setProxy()` | Set proxy SOCKS5 sebelum connect | 26 |
| `clearProxy()` | Hapus pengaturan proxy | 26 |
| `start()` | Login otomatis (phone / bot token) | 2 |
| `getMe()` | Info akun yang sedang login | 1 |
| `sendMessage()` | Kirim pesan teks | 6 |
| `editMessage()` | Edit isi pesan yang sudah dikirim | 25 |
| `deleteMessages()` | Hapus pesan | 25 |
| `forwardMessages()` | Forward pesan ke peer lain | 11 |
| `sendFile()` | Kirim file (auto-detect tipe) | 7 |
| `sendPhoto()` | Kirim foto | 7 |
| `sendVideo()` | Kirim video | 7 |
| `sendAudio()` | Kirim audio | 7 |
| `sendDocument()` | Kirim dokumen | 7 |
| `sendVoice()` | Kirim voice note | 7, 16 |
| `sendPoll()` | Buat jajak pendapat / kuis | 16 |
| `pinMessage()` | Pin pesan | 17 |
| `unpinMessage()` | Unpin pesan | 17 |
| `promoteAdmin()` | Jadikan user sebagai admin | 18 |
| `demoteAdmin()` | Cabut status admin | 18 |
| `banUser()` | Ban user dari grup/supergroup/channel | 18 |
| `unbanUser()` | Unban user | 18 |
| `kickUser()` | Kick user dari grup | 18 |
| `muteUser()` | Mute user — larang kirim pesan | 18 |
| `readOnlyUser()` | Read-only — larang semua jenis konten | 18 |
| `restrictUser()` | Restrict dengan flag kustom | 18 |
| `inviteToChannel()` | Undang user ke supergroup/channel | 18, 32 |
| `search()` | Cari pesan di dalam chat | 19 |
| `searchGlobal()` | Cari pesan di semua chat | 19 |
| `getFullUser()` | Info lengkap user | 20 |
| `getFullChat()` | Info lengkap basic group | 20 |
| `getFullChannel()` | Info lengkap channel/supergroup | 20 |
| `getAdminChannels()` | Daftar channel di mana kita adalah admin | 21 |
| `getChannelMembers()` | Daftar anggota channel/supergroup/grup | 22 |
| `getChatMembers()` | Daftar anggota basic group | 22 |
| `downloadMedia()` | Download media dari pesan history | 24 |
| `downloadDocument()` | Download dokumen by ID | 24 |
| `downloadPhoto()` | Download foto by ID | 24 |
| `joinChannel()` | Join channel atau supergroup | 5 |
| `leaveChannel()` | Leave channel atau supergroup | 5 |
| `getHistory()` | Ambil riwayat pesan | 9 |
| `getDialogs()` | Ambil daftar dialog | 10 |
| `getContacts()` | Ambil daftar kontak | 4 |
| `clickButton()` | Klik tombol inline keyboard (low-level) | 8 |
| `startBot()` | Start bot dengan parameter | 14 |
| `resolvePeer()` | Resolve peer ke InputPeer | 27 |
| `on()` | Daftarkan event handler pesan baru | 12 |
| `onUpdate()` | Daftarkan raw update handler | 29 |
| `removeHandlers()` | Hapus semua event handler | 12 |
| `runUntilDisconnected()` | Jalankan event loop (blocking) | 12 |
| `pollOnce()` | Poll satu update (non-blocking) | 12 |
| `invoke()` | Kirim TL request mentah | — |
| `getSession()` | Akses objek session | 3 |
| `getAuth()` | Akses Auth module | 2 |
| `getMessages()` | Akses Messages module | 6 |
| `getAccount()` | Akses Account module | 23 |
| `getDownloader()` | Akses FileDownloader module | 24 |
| `createChat()` | Buat basic group baru | 32 |
| `createChannel()` | Buat channel/supergroup baru | 32 |
| `deleteChat()` | Hapus grup/channel (permanen) | 32 |
| `migrateChat()` | Upgrade basic group → supergroup | 32 |
| `editChatTitle()` | Ubah judul | 32 |
| `editChatAbout()` | Ubah deskripsi grup/channel/supergroup | 32 |
| `addChatUser()` | Tambah user ke basic group | 32 |
| `toggleSlowMode()` | Slow mode supergroup | 32 |
| `exportInviteLink()` | Generate link undangan | 32 |
| `setDefaultPermissions()` | Default permission anggota | 32 |
| `toggleSignatures()` | Tanda tangan admin di channel | 32 |
| `toggleJoinToSend()` | Wajib join sebelum kirim pesan | 32 |
| `toggleJoinRequest()` | Persetujuan admin untuk join | 32 |

### Format Peer yang Didukung

| Format | Contoh | Keterangan |
|--------|--------|------------|
| `@username` | `'@durov'` | Username dengan tanda @ |
| `username` | `'durov'` | Username tanpa tanda @ (juga valid) |
| `+phone` | `'+6281234567890'` | Nomor telepon internasional |
| `int` | `123456789` | User/chat/channel ID |
| `'me'`/`'self'` | `'me'` | Saved Messages sendiri |
| `t.me/...` | `'t.me/telegram'` | Link publik |
| `InputPeer` | `InputPeer::user(...)` | Object low-level |

### Exception yang Mungkin Terjadi

```php
try {
    $client->sendMessage('@username', 'Halo');
} catch (\RuntimeException $e) {
    // Format: "[ERROR_CODE] ERROR_MESSAGE"
    echo "Error: " . $e->getMessage() . "\n";

    // Contoh error umum:
    // [400] PEER_ID_INVALID     — peer tidak valid
    // [400] USER_PRIVACY_RESTRICTED — user memblokir DM
    // [400] CHANNEL_PRIVATE    — channel private, perlu join dulu
    // [420] FLOOD_WAIT_X       — rate limit, tunggu X detik
    // [401] AUTH_KEY_UNREGISTERED — session tidak valid, login ulang
}
```

### DC Telegram yang Tersedia

| DC ID | IP | Port | Lokasi |
|-------|----|------|--------|
| 1 | 149.154.175.53 | 443 | Miami, USA |
| 2 | 149.154.167.51 | 443 | Amsterdam (default) |
| 3 | 149.154.175.100 | 443 | Miami, USA |
| 4 | 149.154.167.91 | 443 | Amsterdam |
| 5 | 91.108.56.130 | 443 | Singapore |

---

## 14. startBot() — Mulai & Interaksi dengan Bot

`startBot()` digunakan untuk membuka percakapan dengan bot sekaligus mengirimkan parameter `/start`. Setara dengan menekan tombol **START** di Telegram atau mengirim `/start <parameter>`.

### 14.1 Start Bot Tanpa Parameter

```php
$result = $client->startBot('@nama_bot', 'me');
echo $result['started'] ? "Bot berhasil distart!\n" : "Gagal\n";
```

**Signature lengkap:**
```php
startBot(
    string|int           $bot,         // username atau ID bot
    string|int|InputPeer $peer,        // peer tempat start dikirim (biasanya 'me')
    string               $startParam = '' // parameter /start (opsional)
): array
```

### 14.2 Start Bot dengan Parameter (Deep Link)

```php
// Setara dengan membuka link: t.me/nama_bot?start=REF123
$result = $client->startBot('@nama_bot', 'me', startParam: 'REF123');
echo "Start param: " . $result['start_param'] . "\n";
```

### 14.3 Alur Lengkap: Start Bot → Tunggu Respons → Klik Tombol

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$klikSelesai = false;

// Daftarkan handler SEBELUM start bot
$client->on(
    new NewMessage(fromUsers: '@nama_bot', incoming: true),
    function (NewMessageEvent $event) use ($client, &$klikSelesai) {
        if ($klikSelesai) return;

        echo "Bot menjawab: " . $event->rawText . "\n";

        if (!empty($event->message->replyMarkup['rows'])) {
            $rows = $event->message->replyMarkup['rows'];
            foreach ($rows as $ri => $row) {
                foreach ($row as $ci => $btn) {
                    echo "  Tombol [{$ri}][{$ci}]: {$btn['text']}\n";
                }
            }

            try {
                $event->message->click(0, 0); // klik tombol pertama
                echo "Tombol diklik!\n";
            } catch (\Exception $e) {
                echo "Gagal klik: " . $e->getMessage() . "\n";
            }
        }

        $klikSelesai = true;
        $client->disconnect();
    }
);

// Start bot setelah handler terdaftar
echo "Memulai bot @nama_bot...\n";
$client->startBot('@nama_bot', 'me');

$client->runUntilDisconnected();
echo "Selesai.\n";
```

**Return value `startBot()`:**
```php
[
    'started'     => true,
    'start_param' => 'REF123',  // string kosong jika tidak ada parameter
]
```

---

## 15. Skenario Automation Lengkap

### Skenario A: Auto-Join Channel + Kirim Laporan

```php
<?php
require_once 'src/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(123456, 'abc123def456', 'auto_join');
$client->start('+6281234567890');

$channelList = [
    '@telegram',
    '@durov',
    'https://t.me/+AbCdEfGhIjKlMn',
];

$berhasil = [];
$gagal    = [];

foreach ($channelList as $channel) {
    try {
        $client->joinChannel($channel);
        $berhasil[] = $channel;
        echo "✓ Joined: $channel\n";
        sleep(2);
    } catch (\Exception $e) {
        $gagal[] = "$channel: " . $e->getMessage();
        echo "✗ Gagal: $channel — " . $e->getMessage() . "\n";
    }
}

$laporan  = "Laporan Auto-Join — " . date('d/m/Y H:i') . "\n\n";
$laporan .= "✓ Berhasil (" . count($berhasil) . "):\n";
foreach ($berhasil as $c) $laporan .= "  • $c\n";
$laporan .= "\n✗ Gagal (" . count($gagal) . "):\n";
foreach ($gagal as $c) $laporan .= "  • $c\n";

$client->sendMessage('me', $laporan);
echo "Laporan dikirim ke Saved Messages.\n";
```

---

### Skenario B: Bot Clicker — Auto-Start Bot & Klik Semua Tombol

```php
<?php
require_once 'src/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client = new TelegramClient(123456, 'abc123def456', 'bot_clicker');
$client->start('+6281234567890');

$TARGET_BOT  = '@nama_bot';
$klikSelesai = false;

$client->on(
    new NewMessage(fromUsers: $TARGET_BOT, incoming: true),
    function (NewMessageEvent $event) use ($client, $TARGET_BOT, &$klikSelesai) {
        if ($klikSelesai) return;

        echo "Bot menjawab: " . $event->rawText . "\n";

        if (empty($event->message->replyMarkup['rows'])) {
            echo "Tidak ada tombol di pesan ini.\n";
            return;
        }

        $rows = $event->message->replyMarkup['rows'];

        foreach ($rows as $ri => $row) {
            foreach ($row as $ci => $btn) {
                $teks = $btn['text'];
                $tipe = $btn['type'];

                echo "Mengklik [{$ri}][{$ci}] '$teks' (tipe: $tipe)...\n";

                if ($tipe === 'url') {
                    echo "  → Tombol URL: " . ($btn['url'] ?? '-') . " (tidak diklik)\n";
                    continue;
                }

                try {
                    $event->message->click($ri, $ci);
                    echo "  → Klik berhasil!\n";
                    sleep(1);
                } catch (\Exception $e) {
                    echo "  → Gagal: " . $e->getMessage() . "\n";
                }
            }
        }

        $klikSelesai = true;
        $client->disconnect();
    }
);

echo "Memulai bot $TARGET_BOT...\n";
$client->startBot($TARGET_BOT, 'me');
$client->runUntilDisconnected();
echo "Selesai.\n";
```

---

### Skenario C: Monitor & Forward Pesan dari Channel ke Channel Lain

```php
<?php
require_once 'src/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client = new TelegramClient(123456, 'abc123def456', 'forwarder');
$client->start('+6281234567890');

$SUMBER  = '@channel_sumber';
$TUJUAN  = '@channel_tujuan';
$counter = 0;

$client->on(
    new NewMessage(fromUsers: $SUMBER, incoming: true),
    function (NewMessageEvent $event) use ($client, $SUMBER, $TUJUAN, &$counter) {
        $msgId = $event->message->id;
        echo "Pesan baru dari $SUMBER (ID: $msgId): " . substr($event->rawText, 0, 50) . "\n";

        try {
            $client->forwardMessages(to: $TUJUAN, ids: [$msgId], from: $SUMBER);
            $counter++;
            echo "  ✓ Diforward ke $TUJUAN (total: $counter)\n";
        } catch (\Exception $e) {
            echo "  ✗ Gagal forward: " . $e->getMessage() . "\n";
        }
    }
);

echo "Memantau $SUMBER... (Ctrl+C untuk berhenti)\n";
$client->runUntilDisconnected();
```

---

### Skenario D: Auto-Reply Bot Sederhana

```php
<?php
require_once 'src/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client = new TelegramClient(123456, 'abc123def456', 'auto_reply');
$client->start('+6281234567890');

$balasan = [
    'halo'    => 'Halo juga! Ada yang bisa dibantu?',
    'hai'     => 'Hai! 👋',
    'help'    => "Perintah tersedia:\n/start — mulai\n/info — info akun",
];

$client->on(new NewMessage(incoming: true), function (NewMessageEvent $event) use ($client, $balasan) {
    $teks     = strtolower(trim($event->rawText));
    $peerId   = $event->message->peerId;
    $peerType = $event->message->peerType;

    $replyPeer = match ($peerType) {
        'user'    => $event->message->fromUserId ?? $peerId,
        default   => $peerId,
    };

    foreach ($balasan as $keyword => $reply) {
        if (str_contains($teks, $keyword)) {
            try {
                $client->sendMessage($replyPeer, $reply, replyTo: $event->message->id);
                echo "Auto-reply terkirim: $reply\n";
            } catch (\Exception $e) {
                echo "Gagal reply: " . $e->getMessage() . "\n";
            }
            return;
        }
    }
});

$me = $client->getMe();
echo "Auto-reply aktif sebagai: {$me['first_name']} (Ctrl+C untuk berhenti)\n";
$client->runUntilDisconnected();
```

---

### Tips & Best Practice

| Situasi | Saran |
|---------|-------|
| Loop kirim pesan | Minimal `sleep(2)` antar pesan |
| Join banyak channel | Minimal `sleep(3)` antar join |
| Error `FLOOD_WAIT_X` | Tunggu `X` detik sebelum retry |
| Error `AUTH_KEY_UNREGISTERED` | Hapus file `.session` dan login ulang |
| Error `PEER_ID_INVALID` | Gunakan `@username` atau resolve peer dulu lewat `getDialogs()` |
| Jangan hardcode kredensial | Gunakan `getenv()` untuk `API_ID` dan `API_HASH` |

```php
// Contoh aman: baca kredensial dari environment variable
$client = new TelegramClient(
    (int) getenv('TG_API_ID'),
    getenv('TG_API_HASH'),
    'session'
);
$client->start(getenv('TG_PHONE'));
```

---

## 16. Kirim Voice Note & Poll

### 16.1 Voice Note

```php
// Kirim voice note sederhana
$result = $client->sendVoice('@username', '/path/suara.ogg');

// Dengan durasi dan reply
$result = $client->sendVoice('@username', '/path/suara.ogg',
    duration:   30,
    replyTo:    42,
    onProgress: fn($p, $t, $pct) => print("Voice upload: $pct%\r")
);
```

### 16.2 Poll (Jajak Pendapat)

```php
// Poll biasa
$result = $client->sendPoll('@username', 'Bahasa favorit?', ['PHP', 'Python', 'Go']);

// Quiz mode — satu jawaban benar
$result = $client->sendPoll('@group', 'Versi PHP terbaru?', ['7.4', '8.0', '8.2'],
    isQuiz:       true,
    correctIndex: 2,
    solution:     'PHP 8.2 adalah versi LTS yang direkomendasikan'
);

// Multiple choice + tampilkan voter + auto-close
$result = $client->sendPoll('@channel', 'Pilih framework:', ['Laravel', 'Symfony', 'Slim'],
    multipleChoice: true,
    publicVoters:   true,
    closePeriod:    3600    // auto-tutup setelah 1 jam
);
```

**Signature lengkap:**
```php
sendPoll(
    string|int|InputPeer $peer,
    string               $question,
    array                $answers,
    bool                 $isQuiz         = false,
    int                  $correctIndex   = 0,
    string               $solution       = '',
    bool                 $multipleChoice = false,
    bool                 $publicVoters   = false,
    int                  $closePeriod    = 0,
    ?int                 $replyTo        = null
): array
```

---

## 17. Pin & Unpin Pesan

### 17.1 Pin Pesan

```php
// Pin pesan di grup / channel / DM
$result = $client->pinMessage('@supergroup', $msgId);
// Returns: ['pinned' => true, 'message_id' => int]

// Pin tanpa notifikasi (silent)
$result = $client->pinMessage('@supergroup', $msgId, silent: true);
```

### 17.2 Unpin Pesan

```php
$result = $client->unpinMessage('@supergroup', $msgId);
// Returns: ['unpinned' => true, 'message_id' => int]
```

> Pin/unpin di channel dan supergroup membutuhkan hak admin `PIN_MESSAGES`.

---

## 18. Manajemen Admin & Ban

Library mendukung operasi admin di **tiga jenis grup** — semuanya pakai method yang sama.

| Operasi | Supergroup / Channel | Grup Biasa |
|---------|---------------------|------------|
| Jadikan admin | ✅ (bisa set hak & judul) | ✅ (hanya on/off) |
| Cabut admin | ✅ | ✅ |
| Kick user | ✅ | ✅ |
| Ban user | ✅ (bisa sementara) | ✅ (permanen) |
| Unban user | ✅ | ❌ tidak ada ban list |
| Mute / Restrict | ✅ | ❌ — gunakan kickUser |

---

### 18.1 Jadikan Admin

```php
// Supergroup / Channel — promosi dengan semua hak dasar
$result = $client->promoteAdmin('@supergroup', '@user');

// Supergroup / Channel — hak kustom + custom title
$result = $client->promoteAdmin('@supergroup', '@user',
    rights: TelegramClient::ADMIN_DELETE_MESSAGES
          | TelegramClient::ADMIN_BAN_USERS
          | TelegramClient::ADMIN_PIN_MESSAGES
          | TelegramClient::ADMIN_OTHER,   // WAJIB agar status admin aktif
    rank: 'Moderator'
);

// Grup biasa — hanya bisa on/off, tanpa hak kustom atau judul
$result = $client->promoteAdmin(123456789, '@user');
```

**Return value:**
```php
['promoted' => true, 'user_id' => 123456, 'rights' => 4248, 'rank' => 'Moderator']

// Untuk grup biasa:
['promoted' => true, 'user_id' => 123456, 'rights' => 0, 'rank' => '',
 'note' => 'basic group — rank dan custom rights tidak didukung']
```

**Daftar konstanta `ADMIN_*`:**

| Konstanta | Deskripsi | Grup Biasa |
|-----------|-----------|:---:|
| `TelegramClient::ADMIN_CHANGE_INFO` | Ubah nama, foto, deskripsi | ❌ |
| `TelegramClient::ADMIN_POST_MESSAGES` | Posting di channel broadcast | ❌ |
| `TelegramClient::ADMIN_EDIT_MESSAGES` | Edit pesan orang lain (channel) | ❌ |
| `TelegramClient::ADMIN_DELETE_MESSAGES` | Hapus pesan anggota | ❌ |
| `TelegramClient::ADMIN_BAN_USERS` | Ban / restrict anggota | ❌ |
| `TelegramClient::ADMIN_INVITE_USERS` | Undang anggota baru | ❌ |
| `TelegramClient::ADMIN_PIN_MESSAGES` | Pin pesan | ❌ |
| `TelegramClient::ADMIN_ADD_ADMINS` | Jadikan anggota lain admin | ❌ |
| `TelegramClient::ADMIN_ANONYMOUS` | Posting anonim atas nama grup | ❌ |
| `TelegramClient::ADMIN_MANAGE_CALL` | Kelola video call / live stream | ❌ |
| `TelegramClient::ADMIN_OTHER` | **Wajib** agar status admin aktif | ❌ |
| `TelegramClient::ADMIN_MANAGE_TOPICS` | Kelola topik di forum | ❌ |

---

### 18.2 Cabut Status Admin

```php
$result = $client->demoteAdmin('@supergroup', '@user');
// Returns: ['demoted' => true, 'user_id' => 123456]
```

---

### 18.3 Mute User

Larang user kirim pesan, tapi masih bisa membaca chat. Hanya berlaku untuk supergroup/channel.

```php
$client->muteUser('@supergroup', '@spammer');          // mute selamanya
$client->muteUser('@supergroup', '@user', 3600);       // 1 jam
$client->muteUser('@supergroup', '@user', 86400);      // 1 hari
$client->muteUser('@supergroup', '@user', 604800);     // 1 minggu
```

**Return value:**
```php
['restricted' => true, 'user_id' => 123456, 'muted_until' => 'selamanya']
['restricted' => true, 'user_id' => 123456, 'muted_until' => '2026-06-01 10:00:00']
```

---

### 18.4 Read-Only User

Larang user mengirim semua jenis konten. Hanya berlaku untuk supergroup/channel.

```php
$result = $client->readOnlyUser('@supergroup', '@user');          // read-only selamanya
$result = $client->readOnlyUser('@supergroup', '@user', 86400);   // 1 hari

// Returns:
['restricted' => true, 'user_id' => 123456, 'until' => 'selamanya']
['restricted' => true, 'user_id' => 123456, 'until' => '2026-06-01 10:00:00']
```

---

### 18.5 Kick User

User yang di-kick bisa bergabung kembali via link undangan.

```php
$result = $client->kickUser('@supergroup', '@user');
$result = $client->kickUser(123456789, '@user');  // juga berlaku di basic group
// Returns: ['kicked' => true, 'user_id' => 123456]
```

---

### 18.6 Ban User

```php
// Ban permanen
$client->banUser('@supergroup', '@spammer');

// Ban sementara
$client->banUser('@supergroup', '@user', untilDate: time() + 86400); // 1 hari

// Returns: ['banned' => true, 'user_id' => 123456, 'until' => 0]
```

> **Grup biasa:** `banUser()` langsung mengeluarkan user (tidak ada ban list).

---

### 18.7 Unban User

```php
$client->unbanUser('@supergroup', '@user');
// Returns: ['unbanned' => true, 'user_id' => 123456]
```

---

### 18.8 Restrict Kustom (Tingkat Lanjut)

Untuk kontrol lebih detail — pilih kombinasi hak yang ingin dilarang. Hanya berlaku untuk supergroup/channel.

```php
// Larang media dan stiker, tapi masih bisa kirim teks
$client->restrictUser('@supergroup', '@user',
    bannedFlags: TelegramClient::BAN_SEND_MEDIA
               | TelegramClient::BAN_SEND_STICKERS
               | TelegramClient::BAN_SEND_GIFS,
    untilDate: time() + 86400  // 1 hari
);
```

**Daftar konstanta `BAN_*`:**

| Konstanta | Apa yang dilarang |
|-----------|-------------------|
| `TelegramClient::BAN_VIEW_MESSAGES` | Lihat pesan (ban total) |
| `TelegramClient::BAN_SEND_MESSAGES` | Kirim pesan teks |
| `TelegramClient::BAN_SEND_MEDIA` | Kirim semua media |
| `TelegramClient::BAN_SEND_PHOTOS` | Kirim foto |
| `TelegramClient::BAN_SEND_VIDEOS` | Kirim video |
| `TelegramClient::BAN_SEND_AUDIOS` | Kirim audio |
| `TelegramClient::BAN_SEND_DOCS` | Kirim dokumen/file |
| `TelegramClient::BAN_SEND_STICKERS` | Kirim stiker |
| `TelegramClient::BAN_SEND_GIFS` | Kirim GIF |
| `TelegramClient::BAN_SEND_POLLS` | Buat polling |
| `TelegramClient::BAN_EMBED_LINKS` | Kirim link/URL |
| `TelegramClient::BAN_SEND_INLINE` | Pakai inline bot |
| `TelegramClient::BAN_SEND_GAMES` | Main game Telegram |
| `TelegramClient::BAN_CHANGE_INFO` | Ubah info grup |
| `TelegramClient::BAN_INVITE_USERS` | Undang anggota |
| `TelegramClient::BAN_PIN_MESSAGES` | Pin pesan |

---

### 18.9 Undang User ke Supergroup / Channel

```php
// Undang satu user ke supergroup
$result = $client->inviteToChannel('@supergroup', '@user1');

// Undang beberapa user sekaligus
$result = $client->inviteToChannel('@supergroup', ['@user1', '@user2', 123456789]);
// Returns: ['invited' => true, 'channel_id' => int, 'user_ids' => [int, ...]]
```

> **Keterbatasan Telegram API:** Mengundang bot ke **channel broadcast** akan gagal dengan `[400] USER_BOT`. Bot **bisa** diundang ke **supergroup** tanpa masalah. Untuk menambahkan bot sebagai admin channel broadcast, gunakan `promoteAdmin()`.

---

### Contoh Lengkap — Bot Moderasi Otomatis

```php
$group = '@mygroup';

// Jadikan moderator
$client->promoteAdmin($group, '@user1',
    rights: TelegramClient::ADMIN_DELETE_MESSAGES
          | TelegramClient::ADMIN_BAN_USERS
          | TelegramClient::ADMIN_OTHER,
    rank: 'Moderator'
);

// Mute @spammer selama 24 jam
$client->muteUser($group, '@spammer', 86400);

// Jadikan @user2 read-only (tidak bisa kirim apa pun) selama 1 minggu
$client->readOnlyUser($group, '@user2', 604800);

// Ban permanen @badguy
$client->banUser($group, '@badguy');

// Kick @user3 (bisa kembali via link)
$client->kickUser($group, '@user3');

// Cabut status admin @user1
$client->demoteAdmin($group, '@user1');
```

---

## 19. Cari Pesan (Search)

### 19.1 Cari di Chat Tertentu

```php
$messages = $client->search('@supergroup', 'hello world', limit: 50);

foreach ($messages as $msg) {
    echo "[{$msg['id']}] {$msg['text']}\n";
}
```

### 19.2 Cari Global (Semua Chat)

```php
$messages = $client->searchGlobal('hello world', limit: 20);
```

### 19.3 Cari berdasarkan Tipe Media

```php
use XnoxsProto\TL\Functions\MessagesSearchRequest;

$photos = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_PHOTOS);

$videos = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_VIDEO);

$docs = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_DOCUMENT);
```

**Semua konstanta `FILTER_*`:**

| Konstanta | Deskripsi |
|-----------|-----------|
| `FILTER_EMPTY` | Semua pesan (default) |
| `FILTER_PHOTOS` | Foto saja |
| `FILTER_VIDEO` | Video saja |
| `FILTER_DOCUMENT` | Dokumen saja |
| `FILTER_VOICE` | Pesan suara saja |
| `FILTER_MUSIC` | Audio/musik saja |
| `FILTER_GIF` | GIF saja |
| `FILTER_URL` | Pesan yang mengandung URL |

---

## 20. Info Lengkap User / Chat / Channel

### 20.1 Info Lengkap User

```php
$info = $client->getFullUser('@username');

echo "Nama    : {$info['first_name']} {$info['last_name']}\n";
echo "Bio     : {$info['about']}\n";
echo "Common  : {$info['common_chats_count']} grup bersama\n";
echo "Diblokir: " . ($info['is_blocked'] ? 'Ya' : 'Tidak') . "\n";
```

**Return value:**
```php
[
    'id'                 => 123456789,
    'first_name'         => 'Budi',
    'last_name'          => 'Santoso',
    'username'           => 'budisantoso',
    'phone'              => '+6281234567890',
    'bot'                => false,
    'premium'            => false,
    'is_blocked'         => false,
    'about'              => 'Bio user...',
    'common_chats_count' => 5,
    'pinned_msg_id'      => 42,   // null jika tidak ada
]
```

### 20.2 Info Lengkap Basic Group

```php
$info = $client->getFullChat($chatId);

echo "ID     : {$info['id']}\n";
echo "Judul  : {$info['title']}\n";
echo "Anggota: {$info['participants_count']}\n";
```

**Return value:**
```php
[
    'id'                 => 5016290987,
    'title'              => 'Nama Grup',
    'about'              => '',
    'participants_count' => 2,
    'type'               => 'chat',
]
```

> **Catatan:** `title` mungkin kosong jika `getFullChat()` dipanggil segera setelah `createChat()`. Simpan `$result['title']` dari `createChat()` sebagai fallback.

### 20.3 Info Lengkap Channel / Supergroup

```php
$info = $client->getFullChannel('@mychannel');

echo "Judul  : {$info['title']}\n";
echo "Tentang: {$info['about']}\n";
echo "Anggota: {$info['participants_count']}\n";
echo "Admin  : {$info['admins_count']}\n";
echo "Online : {$info['online_count']}\n";
```

**Return value:**
```php
[
    'id'                 => 123456789,
    'title'              => 'Nama Channel',
    'username'           => 'nama_channel',
    'about'              => 'Deskripsi channel',
    'participants_count' => 10000,
    'admins_count'       => 5,
    'banned_count'       => 12,
    'online_count'       => 300,
    'type'               => 'channel',   // atau 'supergroup'
    'access_hash'        => 987654321,
]
```

---

## 21. Daftar Channel Admin (getAdminChannels)

Ambil semua channel dan supergroup di mana akun ini berperan sebagai **admin** atau **creator**.

```php
$channels = $client->getAdminChannels(dialogLimit: 200);

foreach ($channels as $ch) {
    $tipe = $ch['is_supergroup'] ? 'Supergroup' : 'Channel';
    echo "[$tipe] {$ch['title']} — role: {$ch['role']} — {$ch['members']} anggota\n";
}
```

**Return value — array of:**
```php
[
    'id'            => 123456789,
    'access_hash'   => 987654321,
    'title'         => 'Nama Channel',
    'username'      => 'nama_channel',
    'members'       => 5000,
    'is_supergroup' => false,
    'is_channel'    => true,
    'role'          => 'creator',   // 'creator' atau 'admin'
]
```

---

## 22. Daftar Anggota Channel / Grup (getChannelMembers / getChatMembers)

Ambil daftar anggota dari channel broadcast, supergroup, maupun grup biasa. Method ini otomatis mendeteksi tipe peer dan menggunakan API yang tepat.

### 22.1 Cara Kerja Auto-Deteksi

| Tipe peer | API yang digunakan | Keterangan |
|-----------|-------------------|------------|
| **Grup biasa** (`type='chat'`) | `messages.getFullChat` | Mengembalikan semua anggota sekaligus; parameter `filter`/`offset`/`limit` diabaikan |
| **Supergroup / Channel** (`type='channel'`) | `channels.getParticipants` | Mendukung filter, offset, dan limit hingga 200 |

### 22.2 Dasar

```php
// Bekerja untuk supergroup, channel, maupun grup biasa
$members = $client->getChannelMembers('@supergroup');

foreach ($members as $m) {
    $icon = match($m['role']) {
        'creator' => '👑',
        'admin'   => '🛡️',
        default   => '👤',
    };
    echo "$icon {$m['display']} (ID: {$m['user_id']}) — {$m['role']}\n";
}
```

### 22.3 Filter Anggota (Supergroup & Channel saja)

```php
$admins = $client->getChannelMembers('@supergroup', filter: 'admins');
$bots   = $client->getChannelMembers('@supergroup', filter: 'bots');
$banned = $client->getChannelMembers('@supergroup', filter: 'banned');
$recent = $client->getChannelMembers('@supergroup', filter: 'recent');
```

### 22.4 Pagination

```php
$page1 = $client->getChannelMembers('@supergroup', offset: 0,   limit: 100);
$page2 = $client->getChannelMembers('@supergroup', offset: 100, limit: 100);
```

### 22.5 Return Value

```php
[
    'user_id'     => 123456789,
    'username'    => 'budisantoso',
    'first_name'  => 'Budi',
    'last_name'   => 'Santoso',
    'display'     => 'Budi Santoso',
    'phone'       => null,
    'bot'         => false,
    'role'        => 'member',      // 'creator' | 'admin' | 'member' | 'banned' | 'left'
    'rank'        => null,          // custom title admin (misal "Moderator")
    'date'        => 1700000000,    // unix timestamp bergabung
    'access_hash' => 987654321,
]
```

### 22.6 getChatMembers() — Dedicated Method untuk Grup Biasa

```php
$members = $client->getChatMembers(123456789);   // ID numerik
$members = $client->getChatMembers('@grupku');   // username (jika ada)

echo "Total anggota: " . count($members) . "\n";
foreach ($members as $m) {
    printf("  [%-7s]  ID:%-12d  %s\n", $m['role'], $m['user_id'], $m['display']);
}
```

**Signature:**
```php
getChannelMembers(
    string|int|InputPeer $channel,
    string               $filter = 'recent',  // 'recent' | 'admins' | 'bots' | 'banned'
    int                  $offset = 0,
    int                  $limit  = 100
): array

getChatMembers(
    int|string|InputPeer $chat   // ID numerik, username, atau InputPeer grup biasa
): array
// Throws \InvalidArgumentException jika peer bukan type='chat'
```

---

## 23. Manajemen Akun (Account Module)

Semua method diakses via `$client->getAccount()`.

### 23.1 Update Profil

```php
$account = $client->getAccount();

// Ubah nama saja
$result = $account->updateProfile(firstName: 'Budi Baru');

// Ubah bio saja
$result = $account->updateProfile(about: 'PHP developer | XnoxsProto user');

// Ubah semua sekaligus
$result = $account->updateProfile(
    firstName: 'Budi',
    lastName:  'Santoso',
    about:     'Developer | Telegram automation'
);

echo "Profil diperbarui: {$result['first_name']} {$result['last_name']}\n";
```

**Return value:**
```php
[
    'id'         => 123456789,
    'first_name' => 'Budi',
    'last_name'  => 'Santoso',
    'username'   => 'budisantoso',
    'phone'      => '+6281234567890',
]
```

### 23.2 Update Username

```php
$account->updateUsername('username_baru');
$account->updateUsername(''); // hapus username
```

### 23.3 Upload Foto Profil

```php
$account = $client->getAccount();

// Upload sederhana
$result = $account->uploadProfilePhoto('/path/foto.jpg');
echo "Foto ID: {$result['photo_id']}\n";

// Dengan progress callback (parameter ke-2, opsional)
$result = $account->uploadProfilePhoto(
    '/path/foto-hd.jpg',
    function (int $part, int $total, int $pct) {
        echo "\rUpload: $pct% ($part/$total chunk)";
        flush();
    }
);
echo "\n";
```

**Return value:**
```php
[
    'photo_id' => 6113664965953655333,  // int — ID foto yang baru diset
    'date'     => 1780149586,           // int — Unix timestamp saat upload
]
```

**Catatan:**
- Format file yang didukung: JPG, PNG (rekomendasikan JPEG ≥ 640×640 px)
- File < 10 MB → `inputFile`, file ≥ 10 MB → `inputFileBig` (otomatis)
- Upload dilakukan chunked 512 KB per bagian
- Foto yang diupload langsung menjadi foto profil aktif
- Diuji nyata: foto 4.5 KB (1 chunk) berhasil dalam ~0.36 detik

### 23.4 Lihat Foto Profil

```php
$account = $client->getAccount();

$photos = $account->getProfilePhotos();        // default limit 100
$photos = $account->getProfilePhotos(limit: 10); // opsional

foreach ($photos as $i => $p) {
    printf("[%d] ID=%-20s  tanggal=%s\n",
        $i + 1,
        $p['id'],
        date('d/m/Y H:i', $p['date'])
    );
}
```

**Return value — array of:**
```php
[
    'id'             => 6113664965953655333,  // int — ID foto
    'access_hash'    => -4892103847265019228, // int — diperlukan untuk operasi lanjut
    'file_reference' => "\x01\x02...",        // string binary
    'date'           => 1780149586,           // int — Unix timestamp upload
]
```

**Catatan:**
- Foto terbaru berada di indeks pertama (urutan Telegram: terbaru → terlama)
- Diuji nyata: berhasil mengembalikan foto dengan `id`, `access_hash`, `file_reference`, dan `date`

---

### 23.5 Hapus Foto Profil

```php
$account = $client->getAccount();

// Hapus satu foto berdasarkan photo_id
$berhasil = $account->deleteProfilePhoto(6113664965953655333); // bool

// Hapus beberapa sekaligus
$deletedIds = $account->deleteProfilePhotos([
    6113664965953655333,
    5998123456789012345,
]);
// Returns: array int[] — photo_id yang dikonfirmasi terhapus server

// Pola umum: ambil daftar → pilih → hapus
$photos = $account->getProfilePhotos();
if (!empty($photos)) {
    $foto = $photos[count($photos) - 1]; // foto terlama
    $berhasil = $account->deleteProfilePhoto($foto['id']);
}
```

**Catatan:**
- `deleteProfilePhoto()` secara internal memanggil `getProfilePhotos()` terlebih dahulu
  untuk mendapatkan `access_hash` dan `file_reference` yang dibutuhkan Telegram
- Foto profil aktif (paling atas) bisa dihapus; Telegram akan otomatis menggunakan foto berikutnya
- Melempar `\InvalidArgumentException` jika `photo_id` tidak ditemukan di profil
- Diuji nyata: `deleteProfilePhoto(6113903495552372846)` → `true` (sukses, dikonfirmasi server)

---

### 23.6 Lihat Semua Sesi Aktif

```php
$sessions = $account->getAuthorizations();

foreach ($sessions as $sesi) {
    $aktif = $sesi['current'] ? ' ← SESI INI' : '';
    $resmi = $sesi['official_app'] ? '[Resmi]' : '[Third-party]';
    echo "$resmi {$sesi['app_name']} v{$sesi['app_version']}{$aktif}\n";
    echo "  Perangkat : {$sesi['device_model']} — {$sesi['platform']} {$sesi['system_version']}\n";
    echo "  Login dari: {$sesi['country']} ({$sesi['ip']})\n";
    echo "  Terakhir  : " . date('d/m/Y H:i', $sesi['date_active']) . "\n\n";
}
```

**Return value — array of:**
```php
[
    'hash'             => 1234567890,
    'current'          => true,
    'official_app'     => false,
    'password_pending' => false,
    'device_model'     => 'PC',
    'platform'         => 'Linux',
    'system_version'   => 'Ubuntu 22.04',
    'api_id'           => 123456,
    'app_name'         => 'XnoxsProto',
    'app_version'      => '1.0',
    'date_created'     => 1700000000,
    'date_active'      => 1700100000,
    'ip'               => '1.2.3.4',
    'country'          => 'ID',
    'region'           => 'Jakarta',
]
```

### 23.7 Terminate Sesi Tertentu

```php
$sessions = $account->getAuthorizations();
foreach ($sessions as $sesi) {
    if (!$sesi['current']) {
        $berhasil = $account->resetAuthorization($sesi['hash']); // bool
        echo $berhasil ? "Sesi {$sesi['device_model']} diterminasi\n" : "Gagal\n";
    }
}
```

### 23.8 Terminate Semua Sesi Lain

```php
$jumlah = $account->terminateAllOtherSessions();
echo "Berhasil menutup $jumlah sesi lain.\n";
```

---

## 24. Download Media (FileDownloader)

Diakses via `$client->downloadMedia()` (shortcut) atau `$client->getDownloader()` (akses penuh).

> **DC Migration Otomatis:** Library secara otomatis mendeteksi apakah file berada di DC yang berbeda dari session aktif. Jika berbeda, koneksi sementara ke DC file dibuat, auth di-export/import, lalu file diunduh dari sana.

> **FILE_REFERENCE_EXPIRED:** Untuk dokumen (video, audio, file), library secara otomatis me-refresh file_reference yang kadaluarsa. Ketika Telegram mengembalikan error ini, library re-fetch pesan asli dari Telegram, ambil file_reference baru, dan lanjutkan download — tanpa perlu tindakan dari user.

### 24.1 Download dari Pesan History (Cara Termudah)

```php
$messages = $client->getHistory('@channel', 20);

foreach ($messages as $msg) {
    if (empty($msg['media'])) continue;

    $ext  = $client->getMediaExtension($msg['media']); // 'jpg', 'mp4', 'mp3', 'pdf', ...
    $path = $client->downloadMedia($msg, "downloads/file_{$msg['id']}.$ext");
    echo "✅ Tersimpan: $path\n";
}
```

**Dengan progress callback (persen nyata untuk dokumen):**
```php
$path = $client->downloadMedia($msg, '/tmp/video.mp4',
    function(int $received, int $total, int $pct) {
        if ($total > 0) {
            echo "\rMengunduh: $pct% — " . number_format($received) . "/" . number_format($total) . " bytes";
        } else {
            echo "\rMengunduh: " . number_format($received) . " bytes";
        }
        flush();
    }
);
```

### 24.2 Struktur Array `media` dalam Pesan

```php
$msg['media'] = [
    'type'           => 'photo',      // 'photo' | 'video' | 'audio' | 'voice' | 'document' | 'gif' | 'sticker'
    'mime'           => 'image/jpeg',
    'filename'       => '',           // Nama file asli (untuk dokumen)
    'id'             => 7485920374,   // Media ID
    'access_hash'    => -5821038473,
    'file_reference' => "\x01\x02...",
    'dc_id'          => 4,
    'size'           => 1048576,      // Ukuran bytes (dokumen)
    'thumb_size'     => 'y',          // Ukuran foto terbaik (foto)
];
```

**Cek dan download berdasarkan tipe:**
```php
$media = $msg['media'];

switch ($media['type']) {
    case 'photo':
        $client->downloadMedia($msg, "/tmp/foto_{$msg['id']}.jpg");
        break;
    case 'video':
        $mb = round($media['size'] / 1048576, 1);
        echo "Video {$mb} MB — {$media['filename']}\n";
        $client->downloadMedia($msg, "/tmp/{$media['filename']}");
        break;
    case 'document':
        echo "Dokumen: {$media['filename']} ({$media['mime']})\n";
        $client->downloadMedia($msg, "/tmp/{$media['filename']}");
        break;
}
```

### 24.3 Download via FileDownloader (Low-level)

```php
$dl = $client->getDownloader();

// Download foto dengan ID eksplisit
$path = $dl->downloadPhoto(
    photoId:    $media['id'],
    accessHash: $media['access_hash'],
    fileRef:    $media['file_reference'],
    savePath:   '/tmp/foto.jpg',
    thumbSize:  'y',   // 'w' > 'y' > 'x' > 'm' > 's' (terbesar ke terkecil)
    dcId:       $media['dc_id']
);

// Download dokumen/video/audio dengan ID eksplisit
$path = $dl->downloadDocument(
    docId:      $media['id'],
    accessHash: $media['access_hash'],
    fileRef:    $media['file_reference'],
    savePath:   '/tmp/video.mp4',
    dcId:       $media['dc_id']
);
```

### 24.4 Download ke Memori (untuk File Kecil)

```php
$dl = $client->getDownloader();

// Download dokumen ke string
$bytes = $dl->downloadToMemory(
    $media['id'], $media['access_hash'], $media['file_reference'],
    $media['dc_id'] ?? null
);

// Download foto ke string
$bytes = $dl->downloadPhotoToMemory(
    $media['id'], $media['access_hash'], $media['file_reference'],
    'y', $media['dc_id'] ?? null
);

echo "Ukuran: " . strlen($bytes) . " bytes\n";
```

### 24.5 Deteksi Ekstensi Otomatis

```php
// getMediaExtension() mencoba:
// 1. Nama file asli (ambil ekstensi dari 'filename')
// 2. MIME type (peta ke ekstensi umum)
// 3. Tipe media ('photo' → 'jpg', 'video' → 'mp4', dst.)
$ext = $client->getMediaExtension($msg['media']);
```

### 24.6 Signature Lengkap

```php
// Shortcut di TelegramClient:
$client->downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
$client->downloadDocument(int $docId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null, ?int $dcId = null, int $totalSize = 0): string
$client->downloadPhoto(int $photoId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null, string $thumbSize = 'y', ?int $dcId = null): string
$client->getMediaExtension(array $media): string

// Via FileDownloader:
$dl = $client->getDownloader();
$dl->downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
$dl->downloadDocument(int $docId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null, ?int $dcId = null, int $totalSize = 0): string
$dl->downloadPhoto(int $photoId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null, string $thumbSize = 'y', ?int $dcId = null): string
$dl->downloadToMemory(int $docId, int $accessHash, string $fileRef, ?int $dcId = null, int $totalSize = 0): string
$dl->downloadPhotoToMemory(int $photoId, int $accessHash, string $fileRef, string $thumbSize = 'y', ?int $dcId = null): string
```

> **Chunk size:** 512 KB per request. DC migration otomatis. Progress callback: `fn(int $received, int $total, int $pct)`.  
> `$total` = ukuran file bytes (hanya untuk dokumen; 0 untuk foto).  
> `$pct` = persentase 0–100 (hanya untuk dokumen; 0 untuk foto).

---

## 25. Edit & Hapus Pesan

### 25.1 Edit Pesan

```php
// Edit pesan di DM / grup
$result = $client->editMessage('@username', $msgId, 'Teks yang sudah diedit');
// Returns: ['edited' => true, 'message_id' => int]

// Edit pesan di channel (perlu hak admin edit messages)
$result = $client->editMessage('@channel', $msgId, 'Pengumuman diperbarui!');
```

> **Batas waktu edit:** Telegram membatasi edit pesan hingga 48 jam setelah dikirim (untuk akun biasa). Channel tidak ada batas waktu jika kamu admin.

### 25.2 Hapus Pesan

```php
// Hapus satu atau beberapa pesan di DM / grup biasa
$result = $client->deleteMessages([$msgId1, $msgId2]);
// Returns: ['deleted' => true, 'ids' => [int, ...]]

// Hapus pesan di channel / supergroup (peer wajib diisi)
$result = $client->deleteMessages([$msgId1, $msgId2], peer: '@channel');

// Hapus hanya dari sisi sendiri
$result = $client->deleteMessages([$msgId], revoke: false);
```

### 25.3 Contoh: Edit lalu Hapus Setelah Delay

```php
$sent  = $client->sendMessage('@username', 'Pesan sementara...');
$msgId = $sent['message_id'];

sleep(5);
$client->editMessage('@username', $msgId, 'Pesan sudah diperbarui!');

sleep(10);
$client->deleteMessages([$msgId], peer: '@username');
echo "Selesai\n";
```

---

## 26. Proxy SOCKS5

Routing semua traffic MTProto melalui proxy SOCKS5. Harus diset sebelum memanggil `start()`.

```php
$client = new TelegramClient($apiId, $apiHash, 'session');

// Proxy tanpa autentikasi
$client->setProxy('127.0.0.1', 1080);

// Proxy dengan username & password
$client->setProxy('proxy.example.com', 1080, 'user', 'pass');

// Setelah set proxy, baru start
$client->start('+6281234567890');
```

**Hapus proxy:**
```php
$client->clearProxy();
$client->disconnect();
$client->start('+6281234567890'); // reconnect tanpa proxy
```

**Signature:**
```php
setProxy(string $host, int $port, ?string $user = null, ?string $pass = null): void
clearProxy(): void
```

---

## 27. Resolve Peer & Username

### 27.1 resolvePeer() — Ubah Peer ke InputPeer

```php
$peer = $client->resolvePeer('@durov');
$peer = $client->resolvePeer('+6281234567890');
$peer = $client->resolvePeer(123456789);
$peer = $client->resolvePeer('me');
$peer = $client->resolvePeer('t.me/telegram');

// Gunakan hasilnya di method lain
$client->sendMessage($peer, 'Halo!');
$client->getHistory($peer, limit: 10);
```

Format peer yang didukung:

| Format | Contoh | Keterangan |
|--------|--------|------------|
| `@username` | `'@durov'` | Username dengan tanda @ |
| `username` | `'durov'` | Username tanpa tanda @ |
| `+phone` | `'+6281234567890'` | Nomor telepon internasional |
| `int` | `123456789` | User/chat/channel ID |
| `'me'`/`'self'` | `'me'` | Saved Messages sendiri |
| `t.me/...` | `'t.me/telegram'` | Link t.me langsung |

### 27.2 Messages.resolveUsername() — Cari Info by Username

```php
$info = $client->getMessages()->resolveUsername('telegram');

echo "Tipe     : {$info['type']}\n";      // 'user' | 'chat' | 'channel'
echo "ID       : {$info['id']}\n";
echo "Username : @{$info['username']}\n";
echo "Judul    : {$info['title']}\n";
```

### 27.3 InputPeer — Membuat Secara Manual (Low-level)

```php
use XnoxsProto\TL\Types\InputPeer;

$peer = InputPeer::user(123456789, 987654321);      // user biasa
$peer = InputPeer::chat(123456789);                  // basic group
$peer = InputPeer::channel(123456789, 987654321);    // channel/supergroup
$peer = InputPeer::self();                           // Saved Messages
```

---

## 28. Status Koneksi & Info

```php
if ($client->isConnected()) {
    echo "Terkoneksi ke Telegram\n";
}

$layer = $client->getLayer();
echo "API Layer: $layer\n"; // API Layer: 214

// Disconnect & Reconnect
$client->disconnect();
$client->connect();

// Konek ke DC tertentu
$client->connect(dcId: 5); // Singapore
$client->connect(dcId: 2); // Amsterdam (default)
```

**DC yang tersedia:**

| DC ID | IP | Lokasi |
|-------|----|--------|
| 1 | 149.154.175.53 | Miami, USA |
| 2 | 149.154.167.51 | Amsterdam (default) |
| 3 | 149.154.175.100 | Miami, USA |
| 4 | 149.154.167.91 | Amsterdam |
| 5 | 91.108.56.130 | Singapore |

**Signature:**
```php
$client->connect(?int $dcId = null, bool $isReconnect = false): void
$client->disconnect(): void
$client->isConnected(): bool
$client->getLayer(): int
```

---

## 29. Raw Update Handler (onUpdate)

Selain `on(NewMessage)`, library mendukung raw update handler yang menangkap **semua jenis update** dari Telegram.

### 29.1 Mendaftarkan Raw Handler

```php
use XnoxsProto\Events\RawUpdateEvent;

$client->onUpdate(function (RawUpdateEvent $event) use ($client) {
    switch ($event->type) {

        case 'new_message':
            $msg = $event->message; // objek FullMessage
            echo "[NEW] {$msg->peerType}#{$msg->peerId} — {$msg->text}\n";
            break;

        case 'edit_message':
            $msg = $event->message;
            echo "[EDIT] Pesan ID {$msg->id} diedit: {$msg->text}\n";
            break;

        case 'delete_messages':
            $ids = $event->ids; // int[]
            echo "[DELETE] Pesan dihapus: " . implode(', ', $ids) . "\n";
            break;

        case 'read_history':
            echo "[READ] Riwayat dibaca sampai ID {$event->max_id}\n";
            break;

        case 'pinned_messages':
            echo "[PIN] Ada perubahan pesan yang di-pin\n";
            break;

        case 'user_status':
            $userId = $event->user_id;
            $status = $event->online ? 'online' : 'offline';
            echo "[STATUS] User #$userId sekarang $status\n";
            break;
    }
});

$client->runUntilDisconnected();
```

### 29.2 Field RawUpdateEvent

```php
$event->type        // string — tipe update

// new_message, edit_message:
$event->message     // FullMessage

// delete_messages, pinned_messages:
$event->ids         // int[]

// delete_messages:
$event->channel_id  // ?int

// read_history:
$event->direction   // 'in' | 'out'
$event->max_id      // int
$event->peer        // array — peer info

// pinned_messages:
$event->pinned      // bool

// user_status:
$event->user_id     // int
$event->online      // bool
$event->was_online  // int — unix timestamp terakhir online
```

**Semua nilai `$event->type`:**

| Tipe | Deskripsi | Field Tambahan |
|------|-----------|----------------|
| `new_message` | Pesan baru diterima | `$event->message` |
| `edit_message` | Pesan yang ada diedit | `$event->message` |
| `delete_messages` | Pesan dihapus | `$event->ids`, `$event->channel_id` |
| `read_history` | Riwayat dibaca oleh peer | `$event->direction`, `$event->max_id` |
| `pinned_messages` | Perubahan pesan yang di-pin | `$event->ids`, `$event->pinned` |
| `user_status` | Status online user berubah | `$event->user_id`, `$event->online`, `$event->was_online` |

### 29.3 Field FullMessage (untuk new_message & edit_message)

```php
$msg = $event->message;

$msg->id          // int    — ID pesan
$msg->text        // string — teks pesan
$msg->out         // bool   — true jika pesan kita yang kirim
$msg->date        // int    — unix timestamp
$msg->peerId      // int    — ID chat/user/channel
$msg->peerType    // string — 'user' | 'chat' | 'channel'
$msg->fromUserId  // ?int   — ID pengirim
$msg->replyMarkup // ?array — inline keyboard

// Methods:
$msg->click(int|string $row = 0, int $col = 0)   // klik tombol inline keyboard
$msg->getButtonText(int $row, int $col): ?string
$msg->getButtonUrl(int $row, int $col): ?string
```

### 29.4 Gabungkan onUpdate dan on() Bersamaan

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;
use XnoxsProto\Events\RawUpdateEvent;

// Handler spesifik pesan baru
$client->on(new NewMessage(incoming: true, pattern: '/start'), function (NewMessageEvent $event) {
    echo "Ada yang /start!\n";
});

// Raw handler untuk semua update lainnya
$client->onUpdate(function (RawUpdateEvent $event) {
    if ($event->type === 'user_status') {
        $status = $event->online ? 'online' : 'offline';
        echo "User #{$event->userId} menjadi $status\n";
    }
    if ($event->type === 'delete_messages') {
        echo "Ada " . count($event->messageIds) . " pesan dihapus\n";
    }
});

$client->runUntilDisconnected();
```

### 29.5 Troubleshooting: Update Tidak Diterima

Jika `runUntilDisconnected()` berjalan tapi handler tidak pernah dipanggil:

**Langkah 1 — Verifikasi paket tiba:**

Tambahkan echo sementara di `MTProtoSender.php`:

```php
public function receiveUpdate(int $timeoutSeconds = 1): ?array
{
    $raw = $this->connection->tryRecv($timeoutSeconds);
    if ($raw === null) return null;

    fwrite(STDERR, '[recv ' . strlen($raw) . 'B] ');   // ← tambahkan ini
    // ... lanjut kode asli
```

**Langkah 2 — Baca constructor yang diterima:**

```php
$constructor = $plaintextReader->readInt();
fwrite(STDERR, sprintf('ctor=0x%08x', $constructor & 0xFFFFFFFF) . "\n");
```

**Langkah 3 — Diagnosa:**

| Output | Artinya | Solusi |
|--------|---------|--------|
| `[recv NB] ctor=0x313bc7f8` → tidak ada output handler | Constructor tidak dikenali | Update konstanta `UPDATE_SHORT_MESSAGE` di `UpdateParser.php` |
| `[recv NB] ctor=0x313bc7f8` → `parse_exc:Not enough data` | Struktur TL salah | Periksa urutan baca field di `parseShortMessage()` |
| Tidak ada output sama sekali | Koneksi tidak terbentuk | Periksa `isConnected()` |

**Konstanta yang harus benar di `UpdateParser.php`:**

```php
const UPDATE_SHORT_MESSAGE      = 0x313bc7f8;  // updateShortMessage (DM)
const UPDATE_SHORT_CHAT_MESSAGE = 0x4d6deea5;  // updateShortChatMessage (grup)
const UPDATE_SHORT              = 0x78d4dec1;  // updateShort (satu Update + date)
const UPDATES                   = 0x74ae4240;  // updates bundle
const UPDATES_COMBINED          = 0xae0b0d43;  // updatesCombined bundle
```

---

## 30. Catatan Kompatibilitas Layer 214

Library memperbarui parser TL untuk menyesuaikan perubahan konstruktor di **API Layer 214**.

### 30.1 Perubahan Konstruktor TL

**Update / Event Push (UpdateParser.php):**

| Konstruktor | ID Lama | ID Benar (saat ini) | Catatan |
|-------------|---------|----------------------|---------|
| `updateShortMessage` | `0x78d4dec1` | `0x313bc7f8` | Pesan DM masuk/keluar |
| `updateShortChatMessage` | `0x9e0d9b1f` | `0x4d6deea5` | Pesan grup biasa masuk/keluar |
| `updateShort` | `0x11f1331c` | `0x78d4dec1` | Wrapper satu Update + date |

> **Catatan penting:** `0x78d4dec1` dulu adalah ID `updateShortMessage`, sekarang merupakan ID `updateShort` (struktur berbeda: berisi satu inner `Update` + `date:int`). Menukar keduanya menyebabkan `"Not enough data to read"`.

**Member & Chat Parser:**

| Konstruktor | ID Lama | ID Baru (Layer 214) |
|-------------|---------|---------------------|
| `channels.channelParticipants` | `0xf0173fe9` | `0x9ab0feaf` |
| `channelParticipant` | `0x1bd54456` | `0xcb397619` |
| `chatFull` | `0x4dbdc099` | `0x2633421b` |
| `chatParticipants` | `0x3f460fed` | `0x3cbc93f8` |
| `chatParticipantCreator` | `0xda13538a` | `0xe46bcee4` |

### 30.2 Dampak & Gejala

| Gejala | Kemungkinan Penyebab |
|--------|----------------------|
| `"Not enough data to read"` saat listen update | Constructor `updateShortMessage` / `updateShort` tertukar |
| Update diterima tapi tidak masuk handler | Constructor baru diabaikan sebagai `ctor_unknown` |
| `getChannelMembers()` kosong padahal ada anggota | Constructor `channelParticipant*` tidak sesuai layer |

### 30.3 Perubahan `messageMediaDocument` (Layer 214+)

Layer 214 mengubah constructor `messageMediaDocument` dari `0x4cf4d72d` ke `0x52d8ccd9` dengan menambahkan field baru:

```
// Baru (0x52d8ccd9, Layer 214+):
messageMediaDocument flags:# nopremium:3 spoiler:4 video:6 round:7 voice:8
  document:0?Document  alt_documents:5?Vector<Document>
  video_cover:9?Photo  ← bit 9 sekarang = Photo (bukan int)
  video_timestamp:10?int  ← digeser ke bit 10
  ttl_seconds:2?int
```

Gejala jika tidak ter-handle: semua pesan dengan dokumen/audio/video tampak sebagai `type=empty` dengan ID sangat besar (garbage).

---

## 31. Pengaturan Privasi (Account Privacy)

### 31.1 Membaca Pengaturan Privasi

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;

$account = $client->getAccount();

$privasi = $account->getPrivacy(AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP);
echo "Status online: " . implode(', ', $privasi['rules']) . "\n";

$privasi = $account->getPrivacy(AccountGetPrivacyRequest::KEY_PROFILE_PHOTO);
echo "Foto profil: " . implode(', ', $privasi['rules']) . "\n";
```

**Return value `getPrivacy()`:**
```php
[
    'rules' => ['allow_all'],        // atau
    'rules' => ['allow_contacts'],   // atau
    'rules' => ['disallow_all'],
]
```

### 31.2 Mengubah Pengaturan Privasi

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;
use XnoxsProto\TL\Functions\AccountSetPrivacyRequest;

$account = $client->getAccount();

// Status online hanya untuk kontak
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP,
    ['allow_contacts']
);

// Foto profil untuk semua orang
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_PROFILE_PHOTO,
    ['allow_all']
);

// Sembunyikan nomor telepon dari siapa pun
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_PHONE_NUMBER,
    ['disallow_all']
);

// Bisa juga pakai konstanta integer:
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP,
    [AccountSetPrivacyRequest::RULE_DISALLOW_ALL]
);
```

### 31.3 Referensi Lengkap Key & Rules

**Key privasi yang tersedia:**

| Konstanta | Hex | Mengatur | Rules yang didukung |
|-----------|-----|----------|---------------------|
| `KEY_STATUS_TIMESTAMP` | `0x4f96cb18` | Kapan terakhir online | allow_all / allow_contacts / disallow_all |
| `KEY_CHAT_INVITE` | `0xbdfb0426` | Siapa bisa undang ke grup | allow_all / allow_contacts / disallow_all |
| `KEY_PHONE_CALL` | `0xfabadc5f` | Siapa bisa menelepon | allow_all / allow_contacts / disallow_all |
| `KEY_PHONE_P2P` | `0xdb9e70d2` | P2P call | allow_all / allow_contacts / disallow_all |
| `KEY_FORWARDS` | `0xa4dd4c08` | Atribusi saat pesan di-forward | allow_all / allow_contacts / disallow_all |
| `KEY_PROFILE_PHOTO` | `0x5719bacc` | Foto profil | allow_all / allow_contacts / disallow_all |
| `KEY_PHONE_NUMBER` | `0x0352dafa` | Nomor telepon | allow_all / allow_contacts / disallow_all |
| `KEY_ADDED_BY_PHONE` | `0xd1219bdd` | Siapa bisa tambahkan via nomor | ⚠️ allow_all / allow_contacts **saja** |
| `KEY_VOICE_MESSAGES` | `0xaee69d68` | Siapa bisa kirim voice note ke kamu | allow_all / allow_contacts / disallow_all |
| `KEY_ABOUT` | `0x3823cc40` | Bio/deskripsi profil | allow_all / allow_contacts / disallow_all |
| `KEY_BIRTHDAY` | `0xd65a11cc` | Tanggal ulang tahun | allow_all / allow_contacts / disallow_all |

> **⚠️ Constraint `KEY_ADDED_BY_PHONE`:** Key ini hanya menerima `allow_all` dan `allow_contacts`. Mengirim `disallow_all` akan menghasilkan error `[400] PRIVACY_VALUE_INVALID`.

**Rules yang valid (sebagai string):**

| Rule string | Artinya |
|-------------|---------|
| `'allow_all'` | Semua orang |
| `'allow_contacts'` | Hanya kontak |
| `'disallow_all'` | Tidak ada seorang pun |

**Konstanta integer RULE_*:**

| Konstanta | Nilai | Ekuivalen string |
|-----------|-------|-----------------|
| `AccountSetPrivacyRequest::RULE_ALLOW_ALL` | `0x184b35ce` | `'allow_all'` |
| `AccountSetPrivacyRequest::RULE_ALLOW_CONTACTS` | `0x0d09e07b` | `'allow_contacts'` |
| `AccountSetPrivacyRequest::RULE_DISALLOW_ALL` | `0xd66b66c9` | `'disallow_all'` |

### 31.4 Contoh: Reset Semua Privasi ke Default

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;

$account = $client->getAccount();

$keysDefault = [
    AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP => 'allow_contacts',
    AccountGetPrivacyRequest::KEY_PROFILE_PHOTO    => 'allow_all',
    AccountGetPrivacyRequest::KEY_PHONE_NUMBER     => 'allow_contacts',
    AccountGetPrivacyRequest::KEY_FORWARDS         => 'allow_all',
    AccountGetPrivacyRequest::KEY_CHAT_INVITE      => 'allow_all',
];

foreach ($keysDefault as $key => $rule) {
    $account->setPrivacy($key, [$rule]);
    echo "Set key 0x" . dechex($key) . " → $rule\n";
}
echo "Pengaturan privasi direset ke default.\n";
```

---

## 32. Manajemen Grup, Supergroup & Channel

Section ini mencakup semua operasi manajemen grup: membuat, menghapus, mengedit, mengundang anggota, slow mode, link undangan, dan pengaturan bergabung.

### 32.1 Membuat Grup Biasa (Basic Group)

```php
$result = $client->createChat('Nama Grup Kita', ['@user1', '@user2', 123456789]);

$chatId = $result['chat_id'];  // ID grup yang baru dibuat — simpan ini!
echo $result['title'];         // 'Nama Grup Kita'
```

**Return:**
```php
[
    'created'  => true,
    'title'    => 'Nama Grup Kita',
    'user_ids' => [123456789],
    'chat_id'  => 5016290987,
]
```

> **Penting:** Selalu simpan `$result['chat_id']` setelah `createChat()`. Nilai ini dipakai oleh `deleteChat()`, `addChatUser()`, `getFullChat()`, dan method lainnya.

**Batasan Basic Group vs Supergroup:**

| Fitur | Basic Group | Supergroup |
|-------|-------------|------------|
| Maks. anggota | 200 | Tidak terbatas |
| Deskripsi (`editChatAbout`) | ✅ | ✅ |
| Restrict parsial per user | ❌ | ✅ |
| Slow mode | ❌ | ✅ |
| Ban list | ❌ | ✅ |

---

### 32.2 Membuat Supergroup atau Channel Broadcast

```php
// Buat supergroup
$sg = $client->createChannel('Nama Supergroup', 'Deskripsi opsional', megagroup: true);

// Buat channel broadcast
$ch = $client->createChannel('Nama Channel', 'Deskripsi channel', megagroup: false);

// Buat supergroup dengan mode forum/topik
$forum = $client->createChannel('Forum Diskusi', 'Topik bebas', megagroup: true, forum: true);
```

**Return:**
```php
[
    'created'     => true,
    'title'       => 'Nama Supergroup',
    'about'       => 'Deskripsi opsional',
    'megagroup'   => true,
    'forum'       => false,
    'channel_id'  => 3991443490,
    'access_hash' => -1234567890,
]
```

> `channel_id` dan `access_hash` langsung tersedia di return value dan sudah otomatis disimpan ke `peerCache`. Kamu bisa langsung pakai `$result['channel_id']` tanpa perlu `getDialogs()`.

---

### 32.3 Menghapus Grup / Channel / Supergroup

```php
// Hapus basic group — gunakan chat_id dari createChat()
$result = $client->deleteChat($chatId);

// Atau pakai InputPeer eksplisit (jika sesi berbeda dari saat createChat)
use XnoxsProto\TL\Types\InputPeer;
$result = $client->deleteChat(InputPeer::chat(5016290987));

// Hapus channel/supergroup
$result = $client->deleteChat('@channelku');

echo $result['deleted'];  // true
echo $result['peer_id'];  // ID peer yang dihapus
```

> Hanya bisa dilakukan oleh **creator/owner**. Operasi ini **permanen** dan tidak bisa dibatalkan.

---

### 32.4 Upgrade Basic Group ke Supergroup

```php
$result = $client->migrateChat(123456789);

echo $result['migrated'];      // true
echo $result['old_chat_id'];   // ID lama yang sudah tidak berlaku
```

> Setelah migrasi, `chat_id` lama tidak bisa dipakai lagi. Semua anggota otomatis dipindahkan. Riwayat pesan terbawa.

---

### 32.5 Mengubah Judul Grup / Channel / Supergroup

```php
$result = $client->editChatTitle($chatId, 'Nama Baru Grup');        // basic group (int)
$result = $client->editChatTitle('@channelku', 'Judul Baru');       // channel via username
$result = $client->editChatTitle(-100123456789, 'Judul Baru');      // channel via ID Bot API

echo $result['updated'];   // true
echo $result['peer_id'];   // ID grup/channel yang diubah
echo $result['title'];     // 'Nama Baru Grup'
```

---

### 32.6 Mengubah Deskripsi Grup / Channel

```php
// Channel atau supergroup
$result = $client->editChatAbout('@channelku', 'Deskripsi baru yang lebih menarik');
$result = $client->editChatAbout('@supergroup', ''); // kosongkan deskripsi

// Basic group (gunakan chat_id integer)
$result = $client->editChatAbout(123456789, 'Deskripsi basic group saya');

echo $result['updated'];   // true (1)
echo $result['peer_id'];   // ID grup/channel yang diubah
echo $result['about'];     // deskripsi baru yang sudah disimpan
```

---

### 32.7 Tambah User ke Basic Group

```php
$result = $client->addChatUser(123456789, '@username');

// Dengan fwd_limit: user baru bisa lihat 50 pesan terakhir
$result = $client->addChatUser(123456789, 987654321, fwdLimit: 50);

echo $result['added'];    // true
echo $result['chat_id'];  // ID basic group
echo $result['user_id'];  // ID user yang ditambahkan
```

> Untuk **supergroup/channel**, gunakan `inviteToChannel()`.

---

### 32.8 Slow Mode

```php
// Aktifkan slow mode 30 detik
$result = $client->toggleSlowMode('@supergroup', 30);

// Nonaktifkan slow mode
$result = $client->toggleSlowMode('@supergroup', 0);

echo $result['updated'];            // true
echo $result['channel_id'];         // ID supergroup
echo $result['slow_mode_seconds'];  // 30
echo $result['slow_mode_enabled'];  // true / false
```

**Nilai `$seconds` yang valid:** `0` (off), `10`, `30`, `60`, `300`, `900`, `3600`

> Hanya berlaku untuk **supergroup**. Channel broadcast tidak mendukung slow mode.

---

### 32.9 Generate Link Undangan

```php
// Link biasa
$result = $client->exportInviteLink('@grupku');
echo $result['link'];  // 'https://t.me/+AbCdEfGhIjK'

// Revoke link lama dan buat baru
$result = $client->exportInviteLink('@grupku', revokePermanent: true);

// Link dengan batas waktu (kadaluarsa 24 jam)
$result = $client->exportInviteLink('@grupku', expireDate: time() + 86400);

// Link dengan batas pemakaian 50 kali + perlu approval admin
$result = $client->exportInviteLink('@grupku',
    usageLimit:    50,
    requestNeeded: true,
    title:         'Link VIP'
);

echo $result['link'];            // URL link undangan
echo $result['revoked'];         // bool — true jika link lama di-revoke
echo $result['expire_date'];     // Unix timestamp kadaluarsa (null = selamanya)
echo $result['usage_limit'];     // Batas pemakaian (null = unlimited)
echo $result['request_needed'];  // bool — perlu persetujuan admin
echo $result['title'];           // Label link (kosong jika tidak diset)
echo $result['peer_id'];         // ID peer
```

---

### 32.10 Default Permission Anggota

```php
// Larang anggota kirim stiker dan GIF
$result = $client->setDefaultPermissions(
    '@supergroup',
    TelegramClient::BAN_SEND_STICKERS | TelegramClient::BAN_SEND_GIFS
);

// Izinkan semua (hapus semua restriksi default)
$result = $client->setDefaultPermissions('@supergroup', 0);

echo $result['updated'];        // true
echo $result['peer_id'];        // ID grup/supergroup
echo $result['banned_rights'];  // bitmask flag yang dilarang
```

---

### 32.11 Tanda Tangan Admin di Channel (Signatures)

```php
// Aktifkan: setiap postingan tampilkan nama admin yang memposting
$result = $client->toggleSignatures('@channelku', true);

// Nonaktifkan: postingan atas nama channel (anonim)
$result = $client->toggleSignatures('@channelku', false);

// Returns: ['updated' => true, 'channel_id' => int, 'signatures_enabled' => bool]
```

> Hanya berlaku untuk **channel broadcast**. Supergroup tidak mendukung fitur ini.

---

### 32.12 Wajib Join Sebelum Kirim Pesan

```php
$result = $client->toggleJoinToSend('@supergroup', true);  // wajib join dulu
$result = $client->toggleJoinToSend('@supergroup', false); // tidak wajib

// Returns: ['updated' => true, 'channel_id' => int, 'join_to_send' => bool]
```

> **Syarat:** `toggleJoinToSend` hanya bisa diaktifkan pada supergroup yang sudah **di-link ke sebuah broadcast channel** sebagai discussion group-nya. Supergroup standalone akan menghasilkan error: `[400] DISCUSSION_CHAT_REQUIRED`.

---

### 32.13 Wajib Persetujuan Admin untuk Join

```php
$result = $client->toggleJoinRequest('@supergroup_publik', true);  // perlu persetujuan
$result = $client->toggleJoinRequest('@supergroup_publik', false); // langsung join

// Returns: ['updated' => true, 'channel_id' => int, 'join_request' => bool]
```

> **Syarat:** Hanya berlaku pada channel/supergroup yang sudah **berstatus publik** (memiliki username). Pada yang privat akan muncul error `[400] CHAT_PUBLIC_REQUIRED`.

---

### 32.14 Contoh Lengkap: Setup Supergroup Baru

```php
use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(API_ID, 'API_HASH', 'session');
$client->start('+6281234567890');

// 1. Buat supergroup baru
$sg      = $client->createChannel('Tim Alpha', 'Grup internal Tim Alpha', megagroup: true);
$groupId = $sg['channel_id'];   // langsung pakai, tidak perlu getDialogs()

echo "Supergroup dibuat: ID=$groupId\n";

// 2. Ubah judul
$client->editChatTitle($groupId, 'Tim Alpha — Sprint 1');

// 3. Ubah deskripsi
$client->editChatAbout($groupId, 'Supergroup tim pengembangan produk.');

// 4. Invite anggota
$client->inviteToChannel($groupId, ['@user1', '@user2']);

// 5. Set slow mode 30 detik
$client->toggleSlowMode($groupId, 30);

// 6. Larang anggota ubah info grup dan pin pesan
$client->setDefaultPermissions($groupId,
    TelegramClient::BAN_CHANGE_INFO | TelegramClient::BAN_PIN_MESSAGES
);

// 7. Generate link undangan dengan batas 100 orang
$invite = $client->exportInviteLink($groupId, usageLimit: 100);
echo "Link undangan: " . $invite['link'] . "\n";

echo "Setup supergroup selesai!\n";
```

---

### 32.15 Referensi Cepat — Semua Method Manajemen Grup

**Pembuatan & Penghapusan**

| Method | Kegunaan | Tipe peer |
|--------|----------|-----------|
| `createChat($title, $users)` | Buat basic group | — |
| `createChannel($title, $about, $megagroup)` | Buat supergroup/channel | — |
| `deleteChat($peer)` | Hapus grup/channel (permanen) | Semua |
| `migrateChat($chatId)` | Upgrade basic group → supergroup | Basic group |
| `getFullChat($chatId)` | Info lengkap basic group | Basic group |
| `getFullChannel($peer)` | Info lengkap supergroup/channel | Channel/supergroup |

**Edit Properti**

| Method | Kegunaan | Tipe peer |
|--------|----------|-----------|
| `editChatTitle($peer, $title)` | Ubah judul | Semua |
| `editChatAbout($peer, $about)` | Ubah deskripsi | Semua (basic group, supergroup, channel) |
| `exportInviteLink($peer, ...)` | Generate link undangan | Semua |
| `setDefaultPermissions($peer, $flags)` | Default permission anggota | Basic group & supergroup |
| `toggleSlowMode($channel, $seconds)` | Slow mode | Supergroup |
| `toggleSignatures($channel, $enabled)` | Tanda tangan admin | Channel broadcast |
| `toggleJoinToSend($channel, $enabled)` | Wajib join sebelum kirim | Supergroup (linked) |
| `toggleJoinRequest($channel, $enabled)` | Persetujuan admin untuk join | Channel/supergroup publik |

**Manajemen Anggota**

| Method | Kegunaan | Berlaku untuk basic group? |
|--------|----------|---------------------------|
| `addChatUser($chatId, $user, $fwdLimit)` | Tambah anggota | ✅ Khusus basic group |
| `inviteToChannel($channel, $users)` | Undang anggota | ❌ Channel/supergroup saja |
| `kickUser($peer, $user)` | Keluarkan user (bisa join kembali) | ✅ |
| `banUser($peer, $user, $untilDate)` | Ban permanen (basic group = kick) | ✅ |
| `unbanUser($peer, $user)` | Hapus ban | ❌ Throw di basic group |
| `restrictUser($peer, $user, $flags)` | Batasi hak user parsial | ❌ Throw di basic group |
| `promoteAdmin($peer, $user, $rights, $rank)` | Jadikan admin | ✅ (rank & custom rights diabaikan) |
| `demoteAdmin($peer, $user)` | Cabut admin | ✅ |
| `pinMessage($peer, $msgId)` | Pin pesan | ✅ |
| `unpinMessage($peer, $msgId)` | Unpin pesan | ✅ |

---

### 32.16 Contoh Lengkap: Siklus Hidup Basic Group

```php
use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient($apiId, $apiHash, 'sesi');
$client->start('+6281234567890');

// 1. Buat grup
$created = $client->createChat('Tim Proyek Alpha', '@teman1');
$chatId  = $created['chat_id'];
echo "Grup dibuat: ID=$chatId\n";

// 2. Baca info awal
$info = $client->getFullChat($chatId);
echo "Anggota: {$info['participants_count']}\n";

// 3. Kirim pesan sambutan
$msg   = $client->sendMessage($chatId, 'Selamat datang di Tim Proyek Alpha!');
$msgId = $msg['message_id'];

// 4. Pin pesan sambutan (silent)
$client->pinMessage($chatId, $msgId, silent: true);

// 5. Ubah judul
$client->editChatTitle($chatId, 'Tim Alpha — Final');

// 6. Atur izin default
$client->setDefaultPermissions($chatId,
    TelegramClient::BAN_CHANGE_INFO | TelegramClient::BAN_PIN_MESSAGES
);

// 7. Generate link undangan
$invite = $client->exportInviteLink($chatId, usageLimit: 10);
echo "Link undangan: {$invite['link']}\n";

// 8. Promosikan anggota jadi admin
$client->promoteAdmin($chatId, '@teman1');

// 9. Tambah anggota baru
$client->addChatUser($chatId, '@teman2', fwdLimit: 50);

// 10. Kick anggota (keluarkan sementara, bisa tambah ulang)
$client->kickUser($chatId, '@teman2');

// 11. Unpin pesan
$client->unpinMessage($chatId, $msgId);

// 12. Hapus grup (permanen, hanya creator)
$client->deleteChat($chatId);
// Atau jika koneksi sudah berbeda: $client->deleteChat(InputPeer::chat($chatId));

echo "Selesai.\n";
```

---

## 33. Script Uji Interaktif (xnoxs_tester.php)

`xnoxs_tester.php` adalah script CLI interaktif yang mencakup **seluruh fitur library** dalam satu file. Dirancang untuk pengujian cepat tanpa perlu menulis kode — semua operasi dijalankan lewat menu bernomor, dan saat fitur memerlukan peer hasilnya **ditarik otomatis dari API** sehingga tidak perlu mengetik ID atau username secara manual.

### 33.1 Cara Menjalankan

```bash
TG_API_ID=xxxxx TG_API_HASH=yyyyyyy php xnoxs_tester.php
```

Script otomatis mendeteksi file session pertama di folder `sessions/`. Pastikan sudah login sebelumnya.

### 33.2 Struktur Menu

```
════════════════════════════════════════════════════
  MENU UTAMA — XNOXSPROTO TESTER
════════════════════════════════════════════════════
  [1]  Manajemen Akun
  [2]  Pesan & Chat
  [3]  Media
  [4]  Kontak & Dialog
  [5]  Grup & Channel
  [6]  Bot & Interaksi
  [7]  Update & Event
  [0]  Keluar
════════════════════════════════════════════════════
```

### 33.3 Fitur per Submenu

#### Menu 1 — Manajemen Akun
| Opsi | Method yang diuji |
|------|------------------|
| Info akun saya | `getMe()` |
| Edit nama depan / belakang / bio | `getAccount()->updateProfile()` |
| Edit username | `getAccount()->updateUsername()` |
| Upload foto profil | `getAccount()->uploadProfilePhoto()` |
| Lihat sesi aktif | `getAccount()->getAuthorizations()` |
| Hapus sesi tertentu | `getAccount()->resetAuthorization()` |
| Keluar semua sesi lain | `getAccount()->terminateAllOtherSessions()` |
| Lihat pengaturan privasi | `getAccount()->getPrivacy()` |
| Ubah pengaturan privasi | `getAccount()->setPrivacy()` |

#### Menu 2 — Pesan & Chat
| Opsi | Method yang diuji |
|------|------------------|
| Kirim pesan teks | `sendMessage()` |
| Lihat riwayat chat | `getHistory()` |
| Edit pesan | `editMessage()` |
| Hapus pesan | `deleteMessages()` |
| Forward pesan | `forwardMessages()` |
| Cari pesan dalam chat | `search()` |
| Cari pesan global | `searchGlobal()` |
| Pin / Unpin pesan | `pinMessage()` / `unpinMessage()` |
| Kirim polling / kuis | `sendPoll()` |

#### Menu 3 — Media
| Opsi | Method yang diuji |
|------|------------------|
| Kirim foto | `sendPhoto()` |
| Kirim video | `sendVideo()` |
| Kirim audio / MP3 | `sendAudio()` |
| Kirim dokumen | `sendDocument()` |
| Kirim pesan suara | `sendVoice()` |
| Download media dari riwayat | `downloadMedia()` dengan progress bar |

#### Menu 4 — Kontak & Dialog
| Opsi | Method yang diuji |
|------|------------------|
| Lihat semua dialog (dikelompok tipe) | `getDialogs()` |
| Lihat daftar kontak | `getContacts()` |
| Info lengkap pengguna | `getFullUser()` |
| Info lengkap grup/channel | `getFullChat()` / `getFullChannel()` |

#### Menu 5 — Grup & Channel
| Opsi | Method yang diuji |
|------|------------------|
| Buat grup biasa | `createChat()` |
| Buat supergroup | `createChannel(..., megagroup: true)` |
| Buat channel broadcast | `createChannel(..., megagroup: false)` |
| Gabung channel | `joinChannel()` |
| Keluar channel/supergroup | `leaveChannel()` |
| Undang anggota ke channel | `inviteToChannel()` |
| Tambah anggota ke grup biasa | `addChatUser()` |
| Promosi admin | `promoteAdmin()` |
| Turunkan admin | `demoteAdmin()` |
| Ban / Unban / Kick anggota | `banUser()` / `unbanUser()` / `kickUser()` |
| Export link undangan | `exportInviteLink()` |
| Slow mode | `toggleSlowMode()` |
| Edit judul | `editChatTitle()` |
| Edit deskripsi | `editChatAbout()` |
| Lihat anggota | `getChannelMembers()` |
| Hapus grup/channel | `deleteChat()` |

#### Menu 6 — Bot & Interaksi
| Opsi | Method yang diuji |
|------|------------------|
| Mulai bot dengan `/start` param | `startBot()` |
| Klik tombol inline dari pesan | `clickButton()` |

#### Menu 7 — Update & Event
| Opsi | Method yang diuji |
|------|------------------|
| Poll sekali | `pollOnce()` |
| Listen pesan masuk (filter kata kunci) | `on(new NewMessage(...))` + `runUntilDisconnected()` |
| Listen semua update mentah | `onUpdate()` + `runUntilDisconnected()` |

### 33.4 Mekanisme Pilih Peer Otomatis

Semua operasi yang memerlukan peer tidak meminta input manual. Script menggunakan helper:

```
Pilih tujuan dari:
  [1] Dialog (riwayat chat)
  [2] Kontak
  [0] Batal
```

| Helper | Filter | Digunakan untuk |
|--------|--------|-----------------|
| `pilihGrup()` | `type = 'chat'` saja | Tambah anggota ke grup biasa |
| `pilihChannel()` | `type = 'channel'` saja | Undang ke channel, slow mode, lihat anggota |
| `pilihGrupAtauChannel()` | `type = 'chat'` atau `'channel'` | Promosi, ban, kick, edit judul, hapus |
| `pilihTujuan()` | Dialog atau Kontak (pilihan user) | Semua operasi kirim |

### 33.5 Label Subtype di Daftar

```
  [1] [Grup Biasa  ] Nama Grup (5 anggota)
  [2] [Supergroup  ] Nama Supergroup Besar
  [3] [Channel     ] @nama_channel
  [4] [Bot         ] @mybot
```

### 33.6 File Aset Uji

Script menggunakan file di folder `test_assets/` sebagai nilai default:

| File | Digunakan untuk |
|------|----------------|
| `test_assets/test_photo.jpg` | Uji kirim foto & upload foto profil |
| `test_assets/test_audio.mp3` | Uji kirim audio |
| `test_assets/test_doc.txt` | Uji kirim dokumen |

Bisa diganti dengan path file lain saat diminta — tekan Enter untuk pakai default.

---

*Dokumentasi ini dibuat berdasarkan implementasi nyata XnoxsProto (Layer 214).*  
*Semua method, parameter, dan return value mencerminkan kode yang benar-benar berjalan.*
