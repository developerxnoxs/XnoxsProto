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
22. [Daftar Anggota Channel / Grup (getChannelMembers)](#22-daftar-anggota-channel--grup-getchannelmembers)
23. [Manajemen Akun (Account Module)](#23-manajemen-akun-account-module)
24. [Download Media (FileDownloader)](#24-download-media-filedownloader)
25. [Edit & Hapus Pesan](#25-edit--hapus-pesan)
26. [Proxy SOCKS5](#26-proxy-socks5)
27. [Resolve Peer & Username](#27-resolve-peer--username)
28. [Status Koneksi & Info](#28-status-koneksi--info)
29. [Raw Update Handler (onUpdate)](#29-raw-update-handler-onupdate)
30. [Catatan Kompatibilitas Layer 214](#30-catatan-kompatibilitas-layer-214)
31. [Pengaturan Privasi (Account Privacy)](#31-pengaturan-privasi-account-privacy)

---

## 1. Persiapan & Instalasi

### Prasyarat

- PHP **8.2+**
- Extension: `gmp`, `openssl`, `bcmath`

### Instalasi via Composer

```bash
composer require xnoxs/proto
```

### Dapatkan API ID & API Hash

1. Buka [https://my.telegram.org/apps](https://my.telegram.org/apps)
2. Login dengan nomor telepon Telegram kamu
3. Buat aplikasi baru → catat **API ID** dan **API Hash**

### Struktur Dasar

```php
<?php
require_once 'vendor/autoload.php';

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
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(123456, 'abc123def456', 'my_session');
$client->connect();

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
    'verified'   => false,             // true jika akun terverifikasi Telegram
    'premium'    => true,              // true jika akun Telegram Premium
]
```

**Dengan callback kode kustom** (untuk aplikasi non-interaktif):

```php
$client->start('+6281234567890', function () {
    // Ambil kode dari input, database, atau sumber lain
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

Gunakan `start(botToken: ...)` untuk login sebagai bot menggunakan token dari @BotFather:

```php
$client = TelegramClient::create(API_ID, API_HASH, 'my_bot');
$client->start(botToken: '123456789:ABCDefGhIJKlmNOPqrSTUVwxYZ');

$me = $client->getMe();
echo "Login sebagai bot: @" . $me['username'] . "\n";
```

Atau gunakan method manual:

```php
$client->connect();
$result = $client->getAuth()->loginAsBot('123456789:ABCDefGhIJKlmNOPqrSTUVwxYZ');

echo "Bot ID   : " . $result['user']['id'] . "\n";
echo "Username : @" . $result['user']['username'] . "\n";
```

> **Catatan:** Bot token tidak memerlukan nomor telepon. DC migration otomatis juga ditangani oleh library.

### 2.2 Login Manual (Langkah per Langkah)

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(123456, 'abc123def456', 'my_session');
$client->connect();

$auth = $client->getAuth();

// Langkah 1: Kirim kode verifikasi ke nomor telepon
$sentCode = $auth->sendCode('+6281234567890');

echo "Kode dikirim ke: " . $sentCode['phone_number'] . "\n";
echo "Tipe: " . $sentCode['type'] . "\n"; // 'app' atau 'sms'

// Langkah 2: Masukkan kode yang diterima
echo "Masukkan kode: ";
$code = trim(fgets(STDIN));

// Langkah 3: Sign in
$result = $auth->signIn(
    '+6281234567890',
    $sentCode['phone_code_hash'],   // hash dari langkah 1
    $code
);

echo "Berhasil login!\n";
echo "ID    : " . $result['user']['id'] . "\n";
echo "Nama  : " . $result['user']['first_name'] . "\n";
echo "Phone : " . $result['user']['phone'] . "\n";
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

> **Catatan:** `logOut()` menghapus auth key dan status login dari session lokal. Session file akan dikosongkan, sehingga login ulang diperlukan saat koneksi berikutnya.

### 2.5 DC Migration Otomatis

Library secara otomatis menangani perpindahan DC (Data Center) Telegram. Jika server mengembalikan error `PHONE_MIGRATE_X` atau `USER_MIGRATE_X`, library akan otomatis reconnect ke DC yang benar tanpa intervensi manual.

### 2.6 Cek Info 2FA (Tanpa Login)

Cek apakah akun memiliki Two-Step Verification aktif:

```php
$auth = $client->getAuth();

$info = $auth->getPasswordInfo();

echo "Punya password: " . ($info['has_password'] ? 'Ya' : 'Tidak') . "\n";
echo "Hint          : " . ($info['hint'] ?? '-') . "\n";
echo "Ada recovery  : " . ($info['has_recovery'] ? 'Ya' : 'Tidak') . "\n";
```

**Return value:**
```php
[
    'has_password' => true,      // true jika 2FA aktif
    'hint'         => 'Kucing',  // hint password (string kosong jika tidak ada)
    'has_recovery' => false,     // true jika recovery email sudah disetel
]
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

// Cara 3: Factory shortcut
$client = TelegramClient::create($apiId, $apiHash, 'akun_saya');
```

**Format file session:**

File session disimpan dalam format **binary terenkripsi** (bukan JSON). Format ini menggunakan AES-256-CBC untuk enkripsi dan HMAC-SHA256 untuk integritas data. Key turunan bersifat **machine-local** (hostname + path file), sehingga file session tidak bisa dipindahkan antar-mesin atau antar-path.

```
[4]   Magic:        "XNXS"
[1]   Version:      0x01
[1]   Flags:        0x01 (AES-256-CBC encrypted)
[16]  IV:           random per save
[4]   Payload len:  uint32 LE
[N]   Payload:      AES-256-CBC(data, key, iv)
[32]  HMAC:         HMAC-SHA256 atas semua bytes di atas
```

> **Migrasi otomatis:** Jika file session lama masih dalam format JSON (dari versi library sebelumnya), secara otomatis akan di-migrasi ke format binary saat pertama kali dibuka.

> **Non-portable:** File session tidak bisa dipindahkan ke mesin lain atau path berbeda. Ini adalah desain yang disengaja untuk keamanan, mirip dengan SQLite session Telethon.

### 3.1.1 Auto-Session berbasis Nomor Telepon

Saat menggunakan `TelegramClient::create()` (atau `new TelegramClient` dengan session null) dan memanggil `start()` dengan nomor telepon, library **otomatis membuat FileSession** di folder `sessions/`:

```php
// Session null (default) → start() akan auto-buat sessions/session_628xxx.json
$client = TelegramClient::create(API_ID, API_HASH);
$client->start('+6281234567890');
// Session tersimpan di: sessions/session_6281234567890.json
```

**Konfigurasi direktori session:**

```php
// Ubah direktori tempat auto-session disimpan (panggil sebelum start())
TelegramClient::setSessionsDir('/var/lib/myapp/sessions');

$client = TelegramClient::create(API_ID, API_HASH);
$client->start('+6281234567890');
// Session tersimpan di: /var/lib/myapp/sessions/session_6281234567890.session
```

> `setSessionsDir()` bersifat statis dan berlaku global untuk semua client dalam satu proses. Default: `getcwd()/sessions`.

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

// Dapatkan informasi DC aktif
$session->getDC();
// return: ['dc_id' => 2, 'server_address' => '149.154.167.51', 'port' => 443]

// Simpan session secara manual (FileSession auto-save setiap perubahan)
$session->save();

// Hapus semua data session (termasuk file jika FileSession)
$session->delete();
```

### 3.4 Multi-Akun

Gunakan session berbeda untuk setiap akun:

```php
$client1 = new TelegramClient($apiId, $apiHash, 'akun_pertama');
$client2 = new TelegramClient($apiId, $apiHash, 'akun_kedua');

$client1->connect();
$client2->connect();

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

> **Catatan:** Hasil diurutkan berdasarkan nama alfabet. Jika `getContacts()` mengembalikan array kosong, berarti daftar kontak belum berubah sejak pemanggilan terakhir (server mengembalikan `contacts.contactsNotModified`).

---

## 5. Join & Leave Channel

### 5.1 Join Channel

**Via username:**
```php
// Dengan @username
$result = $client->joinChannel('@nama_channel');

// Dengan link t.me
$result = $client->joinChannel('t.me/nama_channel');

echo $result['joined'] ? "Berhasil join!\n" : "Gagal\n";
// $result['peer'] berisi identifier yang digunakan
```

**Via invite link (private channel):**
```php
// Link invite t.me/joinchat/HASH
$result = $client->joinChannel('https://t.me/joinchat/AbCdEfGhIjKlMn');

// Link invite format baru t.me/+HASH
$result = $client->joinChannel('https://t.me/+AbCdEfGhIjKlMn');

echo $result['joined'] ? "Berhasil join!\n" : "Gagal\n";
// $result['via'] berisi 'invite_link' untuk link private
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
// $result['peer'] berisi identifier channel
```

**Return value:**
```php
['left' => true, 'peer' => '@nama_channel']
```

> **Catatan:** `joinChannel()` dan `leaveChannel()` hanya bekerja untuk **channel** dan **supergroup**. Untuk grup biasa (chat), gunakan mekanisme berbeda. Library akan melempar `RuntimeException` jika peer bukan channel/supergroup.

---

## 6. Kirim Pesan

### 6.1 Kirim ke Username

```php
$result = $client->sendMessage('@username_tujuan', 'Halo dari XnoxsProto!');

echo "Pesan terkirim!\n";
echo "Message ID : " . $result['message_id'] . "\n";
echo "Waktu      : " . date('Y-m-d H:i:s', $result['date']) . "\n";
```

### 6.2 Kirim ke Nomor Telepon

```php
$result = $client->sendMessage('+6281234567890', 'Halo!');
```

### 6.3 Kirim ke Saved Messages (Pesan Tersimpan)

```php
$result = $client->sendMessage('me', 'Catatan untuk diri sendiri');
```

### 6.4 Kirim ke Group atau Channel (via ID)

```php
// Kirim ke grup (integer ID positif)
$result = $client->sendMessage(123456789, 'Halo grup!');

// Kirim ke supergroup/channel (integer ID, bisa negatif)
$result = $client->sendMessage(-100123456789, 'Pengumuman!');
```

### 6.5 Kirim dengan Reply (Balas Pesan)

```php
$msgId = 42; // ID pesan yang ingin dibalas

$result = $client->sendMessage('@username', 'Ini balasan!', replyTo: $msgId);
```

### 6.6 Kirim dengan InputPeer Langsung (Low-level)

```php
use XnoxsProto\TL\Types\InputPeer;

// Ke user tertentu (butuh access_hash)
$peer   = InputPeer::user(123456789, 987654321);
$result = $client->sendMessage($peer, 'Pesan ke user');

// Ke grup biasa (hanya butuh ID)
$peer   = InputPeer::chat(123456789);
$result = $client->sendMessage($peer, 'Pesan ke grup');

// Ke channel/supergroup (butuh access_hash)
$peer   = InputPeer::channel(123456789, 987654321);
$result = $client->sendMessage($peer, 'Pesan ke channel');

// Ke Saved Messages
$peer   = InputPeer::self();
$result = $client->sendMessage($peer, 'Ke saved messages');
```

**Return value `sendMessage()`:**
```php
[
    'sent'       => true,
    'message_id' => 12345,         // ID pesan yang baru dikirim
    'date'       => 1700000000,    // Unix timestamp
    'pts'        => 6789,          // Point-in-time sequence (untuk sync)
    'text'       => 'Isi pesan',
]
```

---

## 7. Kirim Media (Foto, Video, Audio, File)

Library mendukung upload dan pengiriman berbagai jenis media langsung dari file lokal. Upload dilakukan secara chunked (512 KB per chunk) sesuai protokol MTProto, dan mendukung file hingga ukuran besar (big file mode otomatis untuk file ≥ 10 MB).

### 7.1 Cara Tercepat: `sendFile()` dengan Auto-Detect

`sendFile()` otomatis mendeteksi tipe media berdasarkan MIME type dan memilih cara pengiriman yang tepat.

```php
// JPG/PNG/WebP → dikirim sebagai FOTO (tampil inline)
$result = $client->sendFile('@username', '/path/foto.jpg', caption: 'Foto keren!');

// MP4/MOV/AVI  → dikirim sebagai VIDEO (player inline)
$result = $client->sendFile('@username', '/path/video.mp4', caption: 'Video nih');

// MP3/OGG/FLAC → dikirim sebagai AUDIO (player audio)
$result = $client->sendFile('@username', '/path/lagu.mp3', caption: 'Dengerin ini');

// PDF/ZIP/APK  → dikirim sebagai DOKUMEN (ikon file)
$result = $client->sendFile('@username', '/path/laporan.pdf', caption: 'Laporan bulan ini');

echo "Terkirim! ID: " . $result['message_id'] . "\n";
echo "Tipe: " . $result['type'] . "\n";     // 'photo', 'video', 'audio', 'document'
echo "MIME: " . $result['mime'] . "\n";     // 'image/jpeg', 'video/mp4', dll.
echo "File: " . $result['filename'] . "\n"; // nama file
```

**Paksa kirim sebagai dokumen** (bypass auto-detect):
```php
// PNG ini akan dikirim sebagai file dokumen, bukan foto inline
$result = $client->sendFile('@username', '/path/gambar.png', forceDocument: true);
```

**Return value `sendFile()`:**
```php
[
    'sent'       => true,
    'message_id' => 12345,
    'date'       => 1700000000,
    'caption'    => 'Caption teks',
    'type'       => 'photo',          // 'photo' | 'video' | 'audio' | 'document'
    'mime'       => 'image/jpeg',
    'filename'   => 'foto.jpg',
]
```

### 7.2 Kirim Foto

```php
// Paling sederhana
$result = $client->sendPhoto('@username', '/path/foto.jpg');

// Dengan caption
$result = $client->sendPhoto('@username', '/path/foto.jpg', caption: 'Ini foto saya!');

// Dengan reply ke pesan tertentu
$result = $client->sendPhoto('@username', '/path/foto.jpg',
    caption: 'Balasan foto',
    replyTo: 42   // ID pesan yang dibalas
);

// Dengan progress upload
$result = $client->sendPhoto('@username', '/path/foto-besar.jpg',
    caption: 'Upload foto...',
    onProgress: function (int $part, int $total, int $pct) {
        echo "Upload: $pct% ($part/$total chunk)\n";
    }
);

echo "Foto terkirim! Message ID: " . $result['message_id'] . "\n";
```

**Format yang didukung sebagai foto:** `jpg`, `jpeg`, `png`, `webp`

### 7.3 Kirim Video

```php
// Sederhana
$result = $client->sendVideo('@username', '/path/video.mp4');

// Dengan caption dan metadata
$result = $client->sendVideo('@username', '/path/video.mp4',
    caption:    'Video tutorial',
    duration:   120.5,   // detik (0 = auto-detect via ffprobe jika tersedia)
    width:      1920,    // piksel (0 = auto-detect)
    height:     1080,
);

// Dengan progress
$result = $client->sendVideo('@username', '/path/video.mp4',
    caption:    'Uploading...',
    onProgress: fn($p, $t, $pct) => print("Video upload: $pct%\r")
);
```

**Format yang didukung sebagai video:** `mp4`, `mov`, `avi`, `mkv`, `webm`, `flv`

> **Catatan:** Jika `ffprobe` tersedia di sistem, library akan otomatis membaca durasi, lebar, dan tinggi frame. Tanpa `ffprobe`, nilai-nilai ini akan 0 (tetap bisa dikirim, hanya tidak ada metadata).

### 7.4 Kirim Audio

```php
// Sederhana
$result = $client->sendAudio('@username', '/path/lagu.mp3');

// Dengan metadata lengkap
$result = $client->sendAudio('@username', '/path/lagu.mp3',
    caption:   'Dengerin nih!',
    duration:  237,          // detik
    title:     'Bohemian Rhapsody',
    performer: 'Queen',
);

// Dengan progress
$result = $client->sendAudio('@username', '/path/podcast.ogg',
    caption:    'Episode 42',
    onProgress: fn($p, $t, $pct) => print("Audio upload: $pct%\r")
);
```

**Format yang didukung sebagai audio:** `mp3`, `ogg`, `oga`, `flac`, `wav`, `m4a`, `aac`, `opus`

> **Catatan:** Jika `ffprobe` tersedia, library akan otomatis membaca durasi, title, dan performer dari metadata/ID3 tag file.

### 7.5 Kirim Dokumen / File

Kirim file apa pun sebagai dokumen (ditampilkan dengan ikon file, bukan inline).

```php
// File PDF
$result = $client->sendDocument('@username', '/path/laporan.pdf', caption: 'Laporan Q4');

// File ZIP
$result = $client->sendDocument('@username', '/path/project.zip', caption: 'Source code');

// File APK
$result = $client->sendDocument('@username', '/path/app.apk', caption: 'App v2.0');

// Dengan nama file kustom (yang terlihat di chat)
$result = $client->sendDocument('@username', '/path/file_123abc.pdf',
    caption:  'Dokumen resmi',
    filename: 'Laporan-Keuangan-2024.pdf'   // nama yang terlihat di chat
);

// Dengan progress
$result = $client->sendDocument('@username', '/path/besar.zip',
    caption:    'File besar',
    onProgress: function (int $part, int $total, int $pct) {
        echo "\rProgress: $pct% ($part/$total)";
    }
);
```

### 7.6 Kirim ke Berbagai Tujuan

Semua method sendPhoto/sendVideo/sendAudio/sendDocument/sendFile menerima peer dalam format yang sama:

```php
// Ke username
$client->sendPhoto('@username', '/foto.jpg');

// Ke nomor telepon
$client->sendPhoto('+6281234567890', '/foto.jpg');

// Ke Saved Messages
$client->sendPhoto('me', '/foto.jpg');

// Ke grup (ID numerik)
$client->sendPhoto(-100123456789, '/foto.jpg');

// Ke channel
$client->sendPhoto('@nama_channel', '/foto.jpg');

// Dengan InputPeer langsung (low-level)
use XnoxsProto\TL\Types\InputPeer;
$peer = InputPeer::channel(123456789, 987654321);
$client->sendPhoto($peer, '/foto.jpg', 'Postingan baru');
```

### 7.7 Progress Upload

Semua method menerima parameter `onProgress` untuk memantau progress upload, sangat berguna untuk file besar.

```php
$client->sendFile('@username', '/path/file-besar.zip',
    caption:    'File 500 MB',
    onProgress: function (int $part, int $total, int $percent) {
        // Hapus baris sebelumnya di terminal
        echo "\r  Upload: [{$percent}%] chunk {$part}/{$total}";
        if ($percent === 100) echo "\n";
    }
);
```

### 7.8 Low-level: Manual Upload + Send

Untuk skenario khusus (misalnya upload sekali, kirim ke banyak tempat), kamu bisa pisahkan proses upload dan pengiriman:

```php
use XnoxsProto\Upload\FileUploader;
use XnoxsProto\TL\Types\InputMedia;
use XnoxsProto\TL\Types\InputPeer;

$uploader = new FileUploader($client);

// Upload file sekali
$inputFile = $uploader->upload('/path/foto.jpg');

// Buat InputMedia dari hasil upload
$media = InputMedia::photo($inputFile);

// Kirim ke beberapa peer dari satu upload yang sama
$peers = [
    InputPeer::self(),
    $client->resolvePeer('@teman1'),
    $client->resolvePeer('@teman2'),
];

foreach ($peers as $peer) {
    $client->getMessages()->sendMedia($peer, $media, 'Broadcast foto!');
}
```

### 7.9 Kirim Voice Note

Voice note tampil sebagai pesan suara (waveform) di Telegram, bukan sebagai file audio biasa.

```php
// Kirim voice note sederhana
$result = $client->sendVoice('@username', '/path/suara.ogg');

// Dengan durasi eksplisit
$result = $client->sendVoice('@username', '/path/suara.ogg', duration: 15);

// Dengan reply dan progress
$result = $client->sendVoice('@username', '/path/suara.ogg',
    duration:   30,
    replyTo:    42,
    onProgress: fn($p, $t, $pct) => print("Voice upload: $pct%\r")
);
```

Format yang direkomendasikan: `.ogg` (Opus codec). Format lain (`.mp3`, `.wav`) juga diterima tetapi mungkin tidak tampil sebagai waveform di semua klien.

### 7.10 Tabel Dukungan Format File

| Format | Ekstensi | Cara Kirim | Keterangan |
|--------|----------|------------|------------|
| **Foto** | jpg, jpeg, png, webp | Inline photo | Tampil langsung di chat |
| **GIF** | gif | Dokumen | Tetap animated jika dikirim sebagai dokumen |
| **Video** | mp4, mov, avi, mkv, webm | Video player | Putar inline |
| **Audio** | mp3, ogg, flac, wav, m4a, aac | Audio player | Tampil sebagai pesan suara/musik |
| **Voice** | ogg (opus) | Voice note | Tampil sebagai waveform suara |
| **PDF** | pdf | Dokumen | Preview tersedia di Telegram |
| **Arsip** | zip, rar, 7z, tar, gz | Dokumen | — |
| **Office** | doc, docx, xls, xlsx, ppt, pptx | Dokumen | — |
| **Kode** | txt, csv, json, xml, html | Dokumen | — |
| **Lainnya** | \* | Dokumen | Semua ekstensi lain otomatis jadi dokumen |

> **Batas ukuran:** File < 10 MB menggunakan `upload.saveFilePart` (small). File ≥ 10 MB menggunakan `upload.saveBigFilePart` (big file mode) — keduanya otomatis dipilih oleh library.

---

## 8. Interaksi Tombol Inline (Click Button)  

### 8.1 Click Button via Event Handler (Paling Mudah)

Saat menerima pesan dengan inline keyboard di dalam event handler, gunakan `$event->message->click()`:

```php
use XnoxsProto\Events\NewMessage;

$client->on(new NewMessage(incoming: true), function ($event) use ($client) {
    $msg = $event->message;

    // Cek apakah pesan punya tombol inline
    if ($msg->replyMarkup !== null && !empty($msg->replyMarkup['rows'])) {
        $rows = $msg->replyMarkup['rows'];

        // Tampilkan semua tombol
        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $button) {
                echo "Tombol [{$rowIdx}][{$colIdx}]: " . $button['text'] . "\n";

                // Tampilkan URL jika ada
                if (isset($button['url'])) {
                    echo "  URL: " . $button['url'] . "\n";
                }
            }
        }

        // Click tombol pertama (baris 0, kolom 0)
        $result = $msg->click(0, 0);
        echo "Tombol diklik!\n";

        // Click tombol kedua di baris pertama (baris 0, kolom 1)
        // $result = $msg->click(0, 1);

        // Click tombol di baris kedua, kolom pertama (baris 1, kolom 0)
        // $result = $msg->click(1, 0);
    }
});

$client->runUntilDisconnected();
```

### 8.2 Click Button via Get History

Ambil pesan dari history lalu klik tombolnya:

```php
$messages = $client->getHistory('@nama_bot', limit: 5);

foreach ($messages as $msg) {
    if (!empty($msg['reply_markup']['rows'])) {
        echo "Pesan ID " . $msg['id'] . " punya tombol:\n";

        $rows = $msg['reply_markup']['rows'];
        foreach ($rows as $ri => $row) {
            foreach ($row as $ci => $btn) {
                echo "  [{$ri}][{$ci}] " . $btn['text'];
                if (isset($btn['url'])) echo " → " . $btn['url'];
                echo "\n";
            }
        }
    }
}
```

> **Catatan:** Untuk mengklik tombol dari history, gunakan `$client->clickButton()` secara langsung (lihat 7.3). Method `click()` hanya tersedia langsung pada object `FullMessage` yang diterima dari event handler.

### 8.3 Click Button via `clickButton()` (Low-level)

```php
use XnoxsProto\TL\Types\InputPeer;

// Siapkan peer (channel/user/grup tempat pesan berada)
$peer  = InputPeer::channel(123456789, 987654321);
$msgId = 42;        // ID pesan yang berisi tombol
$data  = 'payload'; // Data callback button (lihat dari replyMarkup['rows'][x][y]['data'])

$result = $client->clickButton($peer, $msgId, $data);
// return: ['clicked' => true, 'constructor' => '0x...']
```

### 8.4 Baca URL Tombol Tanpa Klik

```php
// Dari FullMessage object (di event handler):
$url  = $event->message->getButtonUrl(0, 0);  // row=0, col=0
$text = $event->message->getButtonText(0, 0);

echo "Teks  : $text\n";
echo "URL   : $url\n";
```

### 8.5 Struktur `replyMarkup`

```php
// Contoh struktur reply_markup yang dikembalikan:
[
    'rows' => [
        // Baris 0
        [
            // Kolom 0
            ['text' => 'Tombol 1', 'data' => 'btn_1', 'type' => 'callback'],
            // Kolom 1
            ['text' => 'Tombol 2', 'url' => 'https://example.com', 'type' => 'url'],
        ],
        // Baris 1
        [
            ['text' => 'Tombol 3', 'data' => 'btn_3', 'type' => 'callback'],
        ],
    ]
]
```

**Tipe tombol yang tersedia:**
- `callback` — tombol callback, klik dikirim ke bot sebagai `getBotCallbackAnswer`
- `url` — tombol link, membuka URL
- `game` — tombol game inline

---

## 9. Get History

Ambil riwayat pesan dari sebuah chat, grup, atau channel.

### 9.1 Dasar

```php
// Ambil 20 pesan terakhir dari sebuah user
$messages = $client->getHistory('@username', limit: 20);

foreach ($messages as $msg) {
    $waktu = date('d/m/Y H:i', $msg['date']);
    $arah  = $msg['out'] ? '→ (kita kirim)' : '← (diterima)';
    echo "[$waktu] $arah {$msg['text']}\n";
}
```

### 9.2 Dari Berbagai Jenis Peer

```php
// Dari username
$messages = $client->getHistory('@durov', limit: 10);

// Dari link t.me
$messages = $client->getHistory('t.me/telegram', limit: 10);

// Dari nomor telepon
$messages = $client->getHistory('+6281234567890', limit: 10);

// Dari user ID (int)
$messages = $client->getHistory(123456789, limit: 10);

// Dari Saved Messages
$messages = $client->getHistory('me', limit: 10);

// Dari grup (ID negatif)
$messages = $client->getHistory(-100123456789, limit: 10);
```

### 9.3 Dengan Pagination & Filter

```php
// Ambil 50 pesan, mulai dari pesan ID tertentu (ke bawah)
$messages = $client->getHistory('@username',
    limit:      50,
    offsetId:   1000,    // mulai dari pesan ID 1000 ke bawah
    offsetDate: 0,
    addOffset:  0,
    maxId:      0,
    minId:      0
);

// Ambil pesan sebelum tanggal tertentu
$messages = $client->getHistory('@username',
    limit:      20,
    offsetDate: strtotime('2024-01-01'),  // sebelum 1 Januari 2024
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
    'date'         => 1700000000,        // Unix timestamp
    'text'         => 'Isi pesan',
    'out'          => false,              // true = kita yang kirim
    'from'         => 'Budi Santoso',    // nama pengirim (jika tersedia)
    'from_id'      => 123456789,         // user ID pengirim
    'type'         => 'message',         // 'message', 'service', atau 'empty'
    'reply_markup' => [                  // null jika tidak ada tombol
        'rows' => [
            [['text' => 'Tombol', 'data' => 'btn', 'type' => 'callback']],
        ]
    ],
]
```

---

## 10. Get Dialog

Ambil daftar semua percakapan (DM, grup, channel) yang ada di akun.

### 10.1 Dasar

```php
$dialogs = $client->getDialogs(limit: 50);

foreach ($dialogs as $dialog) {
    $tipe  = strtoupper($dialog['type']); // USER, CHAT, atau CHANNEL
    $nama  = $dialog['title'];
    $unread = $dialog['unread'];
    $terakhir = date('d/m H:i', $dialog['top_date']);

    echo "[$tipe] $nama\n";
    echo "  Pesan terakhir : {$dialog['top_message']}\n";
    echo "  Waktu          : $terakhir\n";
    echo "  Belum dibaca   : $unread\n";

    if ($dialog['pinned']) echo "  📌 Dipinned\n";
    echo "\n";
}
```

### 10.2 Ambil Semua Dialog (Semua Halaman)

```php
// allPages: true akan terus mengambil sampai semua dialog didapat
$dialogs = $client->getDialogs(limit: 500, allPages: true);

echo "Total dialog: " . count($dialogs) . "\n";
```

### 10.3 Filter berdasarkan Tipe

```php
$dialogs = $client->getDialogs(limit: 100);

$users    = array_filter($dialogs, fn($d) => $d['type'] === 'user');
$chats    = array_filter($dialogs, fn($d) => $d['type'] === 'chat');
$channels = array_filter($dialogs, fn($d) => $d['type'] === 'channel' && $d['is_channel']);
$groups   = array_filter($dialogs, fn($d) => $d['type'] === 'channel' && $d['is_supergroup']);

echo "DM      : " . count($users) . "\n";
echo "Grup    : " . count($chats) + count($groups) . "\n";
echo "Channel : " . count($channels) . "\n";
```

### 10.4 Filter Dialog yang Belum Dibaca

```php
$dialogs = $client->getDialogs(limit: 100);

$unreadDialogs = array_filter($dialogs, fn($d) => $d['unread'] > 0);

foreach ($unreadDialogs as $dialog) {
    echo "{$dialog['title']} — {$dialog['unread']} pesan belum dibaca\n";
}
```

**Signature:**
```php
getDialogs(int $limit = 100, bool $allPages = false): array
// $limit    — jumlah maksimum dialog per halaman (1–100)
// $allPages — true = ambil semua halaman sampai habis (bisa lambat jika dialog banyak)
```

**Return value — array of:**
```php
// Tipe: user (DM)
[
    'type'           => 'user',
    'id'             => 123456789,
    'access_hash'    => 987654321,
    'title'          => 'Budi Santoso',      // nama lengkap
    'username'       => 'budisantoso',        // null jika tidak ada
    'phone'          => '+6281234567890',
    'bot'            => false,
    'unread'         => 3,                    // jumlah pesan belum dibaca
    'pinned'         => false,
    'top_message'    => 'Isi pesan terakhir', // preview
    'top_message_id' => 999,
    'top_date'       => 1700000000,
    'top_out'        => false,                // true = kita yang kirim terakhir
    'is_channel'     => false,
    'is_supergroup'  => false,
]

// Tipe: chat (grup biasa)
[
    'type'           => 'chat',
    'id'             => 123456789,
    'access_hash'    => null,
    'title'          => 'Nama Grup',
    'username'       => null,
    'members'        => 50,
    'creator'        => false,   // true jika kamu adalah pembuat grup
    'unread'         => 0,
    'pinned'         => true,
    'top_message'    => 'Pesan terakhir di grup',
    'top_message_id' => 500,
    'top_date'       => 1700000000,
    'top_out'        => false,
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
    'members'        => 10000,
    'creator'        => false,   // true jika kamu adalah pembuat channel/supergroup
    'unread'         => 5,
    'pinned'         => false,
    'top_message'    => 'Postingan terbaru',
    'top_message_id' => 200,
    'top_date'       => 1700000000,
    'top_out'        => false,
    'is_channel'     => true,   // true = broadcast channel
    'is_supergroup'  => false,  // true = supergroup (grup besar)
]
```

---

## 11. Forward Message

Forward (teruskan) satu atau beberapa pesan ke peer lain.

### 11.1 Forward Satu Pesan

```php
// Forward pesan ID 42 dari @channel_asal ke @channel_tujuan
$result = $client->forwardMessages(
    to:   '@channel_tujuan',
    ids:  [42],               // array ID pesan yang akan diforward
    from: '@channel_asal'
);

echo $result['forwarded'] ? "Berhasil diforward!\n" : "Gagal\n";
// $result['ids'] berisi array ID yang diforward
```

### 11.2 Forward Beberapa Pesan Sekaligus

```php
// Forward pesan ID 10, 11, 12, 15 sekaligus
$result = $client->forwardMessages(
    to:   '@tujuan',
    ids:  [10, 11, 12, 15],
    from: '@asal'
);

echo "Diforward " . count($result['ids']) . " pesan\n";
```

### 11.3 Forward Tanpa Atribusi (Anonymous Forward)

Menyembunyikan nama pengirim asli dari pesan yang diforward:

```php
$result = $client->forwardMessages(
    to:         '@tujuan',
    ids:        [42],
    from:       '@asal',
    dropAuthor: true    // true = sembunyikan pengirim asli
);
```

### 11.4 Forward dari DM ke Channel

```php
// Forward pesan dari DM user tertentu ke channel
$result = $client->forwardMessages(
    to:   '@channel_tujuan',
    ids:  [99],
    from: '+6281234567890'    // nomor telepon user asal
);
```

### 11.5 Forward dengan InputPeer Langsung (Low-level)

```php
use XnoxsProto\TL\Types\InputPeer;

$toPeer   = InputPeer::channel(111111, 222222);
$fromPeer = InputPeer::channel(333333, 444444);

$result = $client->forwardMessages($toPeer, [42, 43], $fromPeer);
```

**Return value:**
```php
[
    'forwarded' => true,
    'ids'       => [42, 43],   // ID pesan yang berhasil diforward
]
```

---

## 12. Event Handler (Real-time)

Pantau pesan masuk secara real-time menggunakan event handler. Ini adalah cara untuk membuat bot atau skrip yang merespons pesan secara otomatis.

### 12.1 Handler Dasar

```php
use XnoxsProto\Events\NewMessage;

$client->on(new NewMessage(incoming: true), function ($event) use ($client) {
    echo "Pesan baru dari: " . $event->message->fromUserId . "\n";
    echo "Isi: " . $event->rawText . "\n";
});

// Mulai event loop (blocking — script tidak akan berhenti sampai Ctrl+C)
$client->runUntilDisconnected();
```

### 12.2 Filter Pesan Masuk/Keluar

```php
use XnoxsProto\Events\NewMessage;

// Hanya pesan masuk (dari orang lain)
$client->on(new NewMessage(incoming: true), function ($event) {
    echo "Pesan masuk: " . $event->rawText . "\n";
});

// Hanya pesan keluar (yang kita kirim)
$client->on(new NewMessage(incoming: false), function ($event) {
    echo "Pesan keluar: " . $event->rawText . "\n";
});

// Semua pesan (masuk dan keluar)
$client->on(new NewMessage(), function ($event) {
    $arah = $event->isIncoming ? "←" : "→";
    echo "$arah " . $event->rawText . "\n";
});
```

### 12.3 Filter dari Peer Tertentu

```php
use XnoxsProto\Events\NewMessage;

// Hanya dari bot/user tertentu
$client->on(new NewMessage(fromUsers: '@nama_bot'), function ($event) use ($client) {
    echo "Pesan dari bot: " . $event->rawText . "\n";

    // Balas pesannya
    $client->sendMessage('@nama_bot', 'Diterima!');
});

// Dari beberapa peer sekaligus
$client->on(new NewMessage(fromUsers: ['@bot1', '@bot2', 123456789]), function ($event) {
    echo "Pesan dari salah satu target: " . $event->rawText . "\n";
});
```

### 12.4 Filter berdasarkan Kata Kunci

```php
use XnoxsProto\Events\NewMessage;

// Hanya pesan yang mengandung kata "halo"
$client->on(new NewMessage(pattern: 'halo'), function ($event) {
    echo "Ada yang bilang halo!\n";
});
```

### 12.5 Kombinasi Filter

```php
use XnoxsProto\Events\NewMessage;

// Pesan masuk dari @mybot yang mengandung "berhasil"
$client->on(
    new NewMessage(fromUsers: '@mybot', incoming: true, pattern: 'berhasil'),
    function ($event) use ($client) {
        echo "Bot mengatakan berhasil!\n";
        // Klik tombol jika ada
        if ($event->message->replyMarkup !== null) {
            $event->message->click(0, 0);
        }
    }
);

$client->runUntilDisconnected();
```

### 12.6 Akses Informasi Sender

```php
use XnoxsProto\Events\NewMessage;

$client->on(new NewMessage(incoming: true), function ($event) {
    // Informasi pengirim
    $sender = $event->getSender(); // User object atau null
    if ($sender !== null) {
        echo "Pengirim: " . $sender->getDisplayName() . "\n";
        echo "ID: " . $sender->id . "\n";
    }

    // Informasi grup (jika pesan dari grup/channel)
    $chat = $event->getChat(); // Chat object atau null
    if ($chat !== null) {
        echo "Di grup: " . $chat->getDisplayName() . "\n";
    }

    // Data mentah pesan
    echo "Message ID   : " . $event->message->id . "\n";
    echo "Peer type    : " . $event->message->peerType . "\n";   // 'user'|'chat'|'channel'
    echo "Peer ID      : " . $event->message->peerId . "\n";
    echo "Waktu        : " . date('H:i:s', $event->message->date) . "\n";
    echo "Keluar/Masuk : " . ($event->isOutgoing ? 'keluar' : 'masuk') . "\n";
});
```

### 12.7 Poll Manual (Non-blocking)

Jika tidak ingin menggunakan `runUntilDisconnected()`, kamu bisa polling manual:

```php
// Cek satu update tanpa blocking (tunggu maks 1 detik)
$client->pollOnce();

// Polling dengan timeout kustom (tunggu maks 5 detik per cycle)
$client->pollOnce(5);

// Polling dalam loop kustom
while (true) {
    $client->pollOnce(1); // tunggu maks 1 detik per iterasi
    // lakukan pekerjaan lain di sini...
}
```

**Signature:**
```php
pollOnce(int $timeoutSeconds = 1): void
// $timeoutSeconds — berapa detik maksimum menunggu update dari server
```

### 12.8 Stop Event Loop

```php
// Dari luar loop (misalnya setelah N pesan diterima):
$count = 0;
$client->on(new NewMessage(incoming: true), function ($event) use ($client, &$count) {
    $count++;
    echo "Pesan ke-$count: " . $event->rawText . "\n";

    if ($count >= 5) {
        $client->disconnect(); // stop loop setelah 5 pesan
    }
});

$client->runUntilDisconnected();
```

---

## 13. Referensi Lengkap

### TelegramClient — Semua Method

| Method | Deskripsi | Section |
|--------|-----------|---------|
| `create()` | Factory shortcut buat client baru | 1 |
| `setSessionsDir()` | Set direktori penyimpanan auto-session (static) | 3 |
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
| `sendVoice()` | Kirim voice note | 7.9, 16 |
| `sendPoll()` | Buat jajak pendapat / kuis | 16 |
| `pinMessage()` | Pin pesan | 17 |
| `unpinMessage()` | Unpin pesan | 17 |
| `promoteAdmin()` | Jadikan user sebagai admin (supergroup/channel/grup biasa) | 18 |
| `demoteAdmin()` | Cabut status admin | 18 |
| `banUser()` | Ban user dari grup/supergroup/channel | 18 |
| `unbanUser()` | Unban user (supergroup/channel) | 18 |
| `kickUser()` | Kick user dari grup/supergroup/channel | 18 |
| `muteUser()` | Mute user — larang kirim pesan | 18 |
| `readOnlyUser()` | Read-only — larang semua jenis konten | 18 |
| `restrictUser()` | Restrict dengan flag kustom (supergroup/channel) | 18 |
| `inviteToChannel()` | Undang user ke supergroup/channel | 18 |
| `search()` | Cari pesan di dalam chat | 19 |
| `searchGlobal()` | Cari pesan di semua chat | 19 |
| `getFullUser()` | Info lengkap user (bio, common chats) | 20 |
| `getFullChat()` | Info lengkap basic group | 20 |
| `getFullChannel()` | Info lengkap channel/supergroup | 20 |
| `getAdminChannels()` | Daftar channel di mana kita adalah admin | 21 |
| `getChannelMembers()` | Daftar anggota channel, supergroup, atau grup biasa | 22 |
| `downloadMedia()` | Download media dari pesan history | 24 |
| `downloadDocument()` | Download dokumen by ID | 24 |
| `downloadPhoto()` | Download foto by ID | 24 |
| `joinChannel()` | Join channel atau supergroup | 5 |
| `leaveChannel()` | Leave channel atau supergroup | 5 |
| `getHistory()` | Ambil riwayat pesan | 9 |
| `getDialogs()` | Ambil daftar dialog | 10 |
| `getContacts()` | Ambil daftar kontak | 4 |
| `clickButton()` | Klik tombol inline keyboard | 8 |
| `startBot()` | Start bot dengan parameter | 14 |
| `resolvePeer()` | Resolve peer ke InputPeer | 27 |
| `on()` | Daftarkan event handler pesan baru | 12 |
| `onUpdate()` | Daftarkan raw update handler | 29 |
| `removeHandlers()` | Hapus semua event handler | 12 |
| `runUntilDisconnected()` | Jalankan event loop (blocking) | 12 |
| `pollOnce()` | Poll satu update (non-blocking) | 12 |
| `invoke()` | Kirim TL request mentah | 13 |
| `getSession()` | Akses objek session | 3 |
| `getAuth()` | Akses Auth module | 2 |
| `getMessages()` | Akses Messages module | 6 |
| `getAccount()` | Akses Account module | 23 |
| `getDownloader()` | Akses FileDownloader module | 24 |

### Format Peer yang Didukung

Hampir semua method yang menerima `peer` mendukung format berikut:

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
use XnoxsProto\Exceptions\RPCException;

try {
    $client->sendMessage('@username', 'Halo');
} catch (\RuntimeException $e) {
    // Format: "[ERROR_CODE] ERROR_MESSAGE"
    echo "Error: " . $e->getMessage() . "\n";

    // Contoh error umum dari Telegram:
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

## Contoh Lengkap: Script All-in-One

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;

define('API_ID',   123456);
define('API_HASH', 'abc123def456');
define('PHONE',    '+6281234567890');

// Inisialisasi client dengan session persisten
$client = new TelegramClient(API_ID, API_HASH, 'akun_utama');
$client->connect();

// Login (skip jika session sudah ada)
$client->start(PHONE);

// Info akun
$me = $client->getMe();
echo "=== Login sebagai: {$me['first_name']} (ID: {$me['id']}) ===\n\n";

// Ambil dialog
$dialogs = $client->getDialogs(limit: 10);
echo "10 Dialog Terbaru:\n";
foreach ($dialogs as $d) {
    echo "  [{$d['type']}] {$d['title']} — {$d['unread']} unread\n";
}
echo "\n";

// Ambil kontak
$contacts = $client->getContacts();
echo "Kontak (" . count($contacts) . " orang):\n";
foreach (array_slice($contacts, 0, 5) as $c) {
    echo "  {$c['display']} (@{$c['username']})\n";
}
echo "\n";

// Kirim pesan ke Saved Messages
$result = $client->sendMessage('me', 'Test dari XnoxsProto — ' . date('H:i:s'));
echo "Pesan terkirim ke Saved Messages. ID: {$result['message_id']}\n\n";

// Ambil history dari Saved Messages
$history = $client->getHistory('me', limit: 3);
echo "3 Pesan Terakhir di Saved Messages:\n";
foreach ($history as $msg) {
    echo "  [{$msg['id']}] " . date('H:i', $msg['date']) . " — {$msg['text']}\n";
}
echo "\n";

// Event handler untuk pesan masuk
$client->on(new NewMessage(incoming: true), function ($event) use ($client) {
    $from = $event->message->fromUserId ?? 'unknown';
    echo "[UPDATE] Pesan baru dari $from: {$event->rawText}\n";

    // Auto-reply
    // $client->sendMessage($event->message->peerId, 'Auto-reply: diterima!');

    // Klik tombol jika ada
    if ($event->message->replyMarkup !== null) {
        echo "  → Ada tombol inline, mengklik tombol pertama...\n";
        try {
            $event->message->click(0, 0);
        } catch (\Exception $e) {
            echo "  → Gagal klik: " . $e->getMessage() . "\n";
        }
    }
});

echo "Menunggu pesan baru... (Ctrl+C untuk berhenti)\n";
$client->runUntilDisconnected();
```

---

*Dokumentasi ini dibuat berdasarkan implementasi nyata XnoxsProto versi 1.1.0 (Layer 214).*  
*Semua method, parameter, dan return value mencerminkan kode yang benar-benar berjalan.*

---

## 14. startBot() — Mulai & Interaksi dengan Bot

`startBot()` digunakan untuk membuka percakapan dengan bot sekaligus mengirimkan parameter `/start`. Ini setara dengan menekan tombol **START** di aplikasi Telegram atau mengirim `/start <parameter>`.

### 14.1 Start Bot Tanpa Parameter

```php
// Sama seperti menekan tombol START di Telegram
$result = $client->startBot('@nama_bot', 'me');

echo $result['started'] ? "Bot berhasil distart!\n" : "Gagal\n";
```

**Signature lengkap:**
```php
startBot(
    string|int           $bot,         // username atau ID bot
    string|int|InputPeer $peer,        // peer tempat start dikirim (biasanya 'me' atau sama dengan bot)
    string               $startParam = '' // parameter /start (opsional)
): array
```

### 14.2 Start Bot dengan Parameter (Deep Link)

`startParam` digunakan saat kamu membuka bot lewat link seperti `t.me/nama_bot?start=PARAMETER`. Ini biasanya dipakai untuk referral, verifikasi, atau membuka menu tertentu di bot.

```php
// Setara dengan membuka link: t.me/nama_bot?start=REF123
$result = $client->startBot('@nama_bot', 'me', startParam: 'REF123');

echo "Start param: " . $result['start_param'] . "\n";
```

### 14.3 Start Bot di Grup

Kamu bisa start bot dalam konteks grup tertentu (misalnya bot yang perlu diaktifkan per-grup):

```php
// Start bot dalam konteks grup
$result = $client->startBot('@nama_bot', '@nama_grup', startParam: 'activate');
```

### 14.4 Start Bot dengan ID (tanpa username)

Jika kamu punya ID bot (misalnya dari `getContacts()` atau `getDialogs()`):

```php
$botId = 123456789; // ID numerik bot

$result = $client->startBot($botId, 'me');
```

### 14.5 Alur Lengkap: Start Bot → Tunggu Respons → Klik Tombol

Pola umum berinteraksi dengan bot: start → tunggu balasan → klik tombol:

```php
use XnoxsProto\Events\NewMessage;

// 1. Start bot
$client->startBot('@nama_bot', 'me', startParam: '');
echo "Bot distart, menunggu respons...\n";

// 2. Tangkap respons pertama dari bot
$client->on(new NewMessage(fromUsers: '@nama_bot', incoming: true), function ($event) use ($client) {
    echo "Bot menjawab: " . $event->rawText . "\n";

    // 3. Klik tombol jika ada
    if ($event->message->replyMarkup !== null) {
        $rows = $event->message->replyMarkup['rows'];
        echo "Tombol tersedia:\n";
        foreach ($rows as $ri => $row) {
            foreach ($row as $ci => $btn) {
                echo "  [{$ri}][{$ci}] " . $btn['text'] . "\n";
            }
        }

        // Klik tombol pertama
        try {
            $result = $event->message->click(0, 0);
            echo "Tombol diklik!\n";
        } catch (\Exception $e) {
            echo "Gagal klik: " . $e->getMessage() . "\n";
        }
    }

    // Stop setelah respons pertama
    $client->disconnect();
});

$client->runUntilDisconnected();
```

**Return value `startBot()`:**
```php
[
    'started'      => true,
    'start_param'  => 'REF123',  // parameter yang dikirim (string kosong jika tidak ada)
]
```

> **Catatan penting:**
> - `$bot` harus berupa **bot** Telegram (akun dengan `bot: true`). Jika bukan bot, Telegram akan mengembalikan error.
> - `$peer` biasanya diisi `'me'` (artinya percakapan dengan bot di DM kamu sendiri).
> - Library secara otomatis men-resolve username bot ke `user_id` + `access_hash` sebelum mengirim request.
> - Jika bot belum pernah dihubungi, `startBot()` sekaligus membuka percakapan baru.

---

## 15. Skenario Automation Lengkap

Berikut adalah contoh skrip nyata untuk berbagai skenario automation yang umum digunakan.

---

### Skenario A: Auto-Join Channel + Kirim Laporan

Secara otomatis join daftar channel, lalu kirim laporan hasilnya ke Saved Messages.

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(123456, 'abc123def456', 'auto_join');
$client->connect();
$client->start('+6281234567890');

$channelList = [
    '@telegram',
    '@durov',
    'https://t.me/+AbCdEfGhIjKlMn',  // private via invite link
];

$berhasil = [];
$gagal    = [];

foreach ($channelList as $channel) {
    try {
        $result = $client->joinChannel($channel);
        $berhasil[] = $channel;
        echo "✓ Joined: $channel\n";
        sleep(2); // Jeda 2 detik antar join untuk hindari flood
    } catch (\Exception $e) {
        $gagal[] = "$channel: " . $e->getMessage();
        echo "✗ Gagal: $channel — " . $e->getMessage() . "\n";
    }
}

// Kirim laporan ke Saved Messages
$laporan  = "Laporan Auto-Join — " . date('d/m/Y H:i') . "\n\n";
$laporan .= "✓ Berhasil (" . count($berhasil) . "):\n";
foreach ($berhasil as $c) $laporan .= "  • $c\n";
$laporan .= "\n✗ Gagal (" . count($gagal) . "):\n";
foreach ($gagal as $c) $laporan .= "  • $c\n";

$client->sendMessage('me', $laporan);
echo "\nLaporan dikirim ke Saved Messages.\n";
```

---

### Skenario B: Bot Clicker — Auto-Start Bot & Klik Semua Tombol

Otomatis start bot, ambil semua tombol dari balasannya, lalu klik satu per satu.

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;

$client = new TelegramClient(123456, 'abc123def456', 'bot_clicker');
$client->connect();
$client->start('+6281234567890');

$TARGET_BOT  = '@nama_bot';
$klikSelesai = false;

// Daftarkan handler SEBELUM start bot
$client->on(
    new NewMessage(fromUsers: $TARGET_BOT, incoming: true),
    function ($event) use ($client, $TARGET_BOT, &$klikSelesai) {
        if ($klikSelesai) return;

        echo "Bot menjawab: " . $event->rawText . "\n";

        if (empty($event->message->replyMarkup['rows'])) {
            echo "Tidak ada tombol di pesan ini.\n";
            return;
        }

        $rows = $event->message->replyMarkup['rows'];
        echo "Ditemukan " . count($rows) . " baris tombol.\n\n";

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
                    sleep(1); // Jeda antar klik
                } catch (\Exception $e) {
                    echo "  → Gagal: " . $e->getMessage() . "\n";
                }
            }
        }

        $klikSelesai = true;
        $client->disconnect();
    }
);

// Start bot setelah handler terdaftar
echo "Memulai bot $TARGET_BOT...\n";
$client->startBot($TARGET_BOT, 'me');

$client->runUntilDisconnected();
echo "Selesai.\n";
```

---

### Skenario C: Monitor & Forward Pesan dari Channel ke Channel Lain

Pantau pesan baru di channel sumber, lalu forward otomatis ke channel tujuan.

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;

$client = new TelegramClient(123456, 'abc123def456', 'forwarder');
$client->connect();
$client->start('+6281234567890');

$SUMBER  = '@channel_sumber';
$TUJUAN  = '@channel_tujuan';
$counter = 0;

$client->on(
    new NewMessage(fromUsers: $SUMBER, incoming: true),
    function ($event) use ($client, $SUMBER, $TUJUAN, &$counter) {
        $msgId = $event->message->id;
        $teks  = $event->rawText;

        echo "Pesan baru dari $SUMBER (ID: $msgId): " . substr($teks, 0, 50) . "...\n";

        try {
            $result = $client->forwardMessages(
                to:   $TUJUAN,
                ids:  [$msgId],
                from: $SUMBER
            );
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

### Skenario D: Scraper Dialog + History + Export ke JSON

Ambil semua dialog, lalu untuk setiap dialog ambil 10 pesan terakhir dan simpan ke file JSON.

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(123456, 'abc123def456', 'scraper');
$client->connect();
$client->start('+6281234567890');

echo "Mengambil daftar dialog...\n";
$dialogs = $client->getDialogs(limit: 20);
echo "Ditemukan " . count($dialogs) . " dialog.\n\n";

$export = [];

foreach ($dialogs as $dialog) {
    $nama = $dialog['title'];
    $tipe = $dialog['type'];
    $id   = $dialog['id'];

    echo "Mengambil history dari: $nama ($tipe ID:$id)...\n";

    try {
        // Gunakan ID numerik langsung
        $peer = match ($tipe) {
            'user'    => $dialog['id'],
            'chat'    => $dialog['id'],
            'channel' => $dialog['id'],
        };

        $messages = $client->getHistory($peer, limit: 10);

        $export[] = [
            'dialog'   => $dialog,
            'messages' => $messages,
        ];

        echo "  → " . count($messages) . " pesan diambil\n";
        sleep(1); // Jeda untuk hindari flood limit
    } catch (\Exception $e) {
        echo "  → Gagal: " . $e->getMessage() . "\n";
    }
}

// Simpan ke file JSON
$filename = 'export_' . date('Ymd_His') . '.json';
file_put_contents($filename, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nData disimpan ke: $filename\n";
```

---

### Skenario E: Auto-Reply Bot Sederhana

Balas setiap pesan masuk secara otomatis berdasarkan kata kunci.

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;

$client = new TelegramClient(123456, 'abc123def456', 'auto_reply');
$client->connect();
$client->start('+6281234567890');

// Daftar balasan otomatis berdasarkan kata kunci
$balasan = [
    'halo'    => 'Halo juga! Ada yang bisa dibantu?',
    'hai'     => 'Hai! 👋',
    'help'    => "Perintah tersedia:\n/start — mulai\n/info — info akun\n/stop — berhenti",
    'info'    => null, // ditangani khusus di bawah
];

$client->on(new NewMessage(incoming: true), function ($event) use ($client, $balasan) {
    $teks    = strtolower(trim($event->rawText));
    $peerId  = $event->message->peerId;
    $peerType = $event->message->peerType;

    // Tentukan peer untuk membalas
    $replyPeer = match ($peerType) {
        'user'    => $event->message->fromUserId ?? $peerId,
        'chat'    => $peerId,
        'channel' => $peerId,
        default   => $peerId,
    };

    // Cek kata kunci
    foreach ($balasan as $keyword => $reply) {
        if (str_contains($teks, $keyword)) {
            if ($keyword === 'info') {
                try {
                    $me = $client->getMe();
                    $reply = "Info akun:\nNama: {$me['first_name']}\nID: {$me['id']}\nUsername: @{$me['username']}";
                } catch (\Exception $e) {
                    $reply = "Gagal ambil info: " . $e->getMessage();
                }
            }

            try {
                $client->sendMessage(
                    $replyPeer,
                    $reply,
                    replyTo: $event->message->id  // reply ke pesan aslinya
                );
                echo "Auto-reply terkirim ke peer $replyPeer: $reply\n";
            } catch (\Exception $e) {
                echo "Gagal reply: " . $e->getMessage() . "\n";
            }

            return; // Hanya satu balasan per pesan
        }
    }
});

$me = $client->getMe();
echo "Auto-reply aktif sebagai: {$me['first_name']} (Ctrl+C untuk berhenti)\n";
$client->runUntilDisconnected();
```

---

### Skenario F: Cek Kontak + Kirim Pesan Massal

Ambil semua kontak, lalu kirim pesan broadcast satu per satu dengan jeda.

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\TL\Types\InputPeer;

$client = new TelegramClient(123456, 'abc123def456', 'broadcaster');
$client->connect();
$client->start('+6281234567890');

$pesanBroadcast = "Halo! Ini adalah pesan otomatis dari XnoxsProto.";

$contacts = $client->getContacts();
echo "Mengirim ke " . count($contacts) . " kontak...\n\n";

$berhasil = 0;
$gagal    = 0;

foreach ($contacts as $contact) {
    // Skip bot
    if ($contact['bot']) continue;

    $nama = $contact['display'];

    try {
        // Gunakan InputPeer::user() dengan access_hash yang tersedia
        $peer = InputPeer::user($contact['id'], $contact['access_hash']);
        $client->sendMessage($peer, "Halo {$contact['first_name']}! $pesanBroadcast");

        $berhasil++;
        echo "✓ Terkirim ke $nama (ID: {$contact['id']})\n";
        sleep(3); // Jeda 3 detik antar pesan — PENTING untuk hindari ban
    } catch (\Exception $e) {
        $gagal++;
        echo "✗ Gagal ke $nama: " . $e->getMessage() . "\n";
    }
}

echo "\nSelesai! Berhasil: $berhasil | Gagal: $gagal\n";

// Kirim ringkasan ke Saved Messages
$client->sendMessage('me', "Broadcast selesai.\nBerhasil: $berhasil\nGagal: $gagal");
```

> **Peringatan:** Mengirim pesan massal terlalu cepat bisa menyebabkan akun terkena **FLOOD_WAIT** atau bahkan **banned** oleh Telegram. Selalu tambahkan `sleep()` yang cukup (minimal 2–5 detik per pesan) dan hindari mengirim ke terlalu banyak user yang tidak mengenal kamu dalam waktu singkat.

---

### Tips & Best Practice

| Situasi | Saran |
|---------|-------|
| Loop kirim pesan | Minimal `sleep(2)` antar pesan |
| Join banyak channel | Minimal `sleep(3)` antar join |
| Error `FLOOD_WAIT_X` | Tunggu `X` detik sebelum retry |
| Error `AUTH_KEY_UNREGISTERED` | Hapus file `.session` dan login ulang |
| Error `PEER_ID_INVALID` | Gunakan `@username` atau resolve peer dulu lewat `getDialogs()` |
| Session expired | `FileSession::delete()` lalu `connect()` + `start()` ulang |
| Multi-akun | Buat client terpisah dengan nama session berbeda |
| Jangan hardcode kredensial | Gunakan `.env` atau `getenv()` untuk `API_ID` dan `API_HASH` |

```php
// Contoh aman: baca kredensial dari environment variable
$apiId   = (int) getenv('TG_API_ID');
$apiHash = getenv('TG_API_HASH');
$phone   = getenv('TG_PHONE');

$client = new TelegramClient($apiId, $apiHash, 'session');
$client->connect();
$client->start($phone);
```

---

## 16. Kirim Voice Note & Poll

### 16.1 Voice Note

Voice note tampil sebagai pesan suara (waveform) di Telegram, bukan sebagai file audio biasa.

```php
// Kirim voice note sederhana
$result = $client->sendVoice('@username', '/path/suara.ogg');

// Dengan durasi eksplisit
$result = $client->sendVoice('@username', '/path/suara.ogg', duration: 15);

// Dengan reply dan progress
$result = $client->sendVoice('@username', '/path/suara.ogg',
    duration:   30,
    replyTo:    42,
    onProgress: fn($p, $t, $pct) => print("Voice upload: $pct%\r")
);
```

Format yang direkomendasikan: `.ogg` (Opus codec). Format lain (`.mp3`, `.wav`) juga diterima tetapi mungkin tidak tampil sebagai waveform di semua klien Telegram.

### 16.2 Poll (Jajak Pendapat)

```php
// Poll biasa
$result = $client->sendPoll('@username', 'Bahasa favorit?', ['PHP', 'Python', 'Go']);

// Quiz mode — satu jawaban benar
$result = $client->sendPoll('@group', 'Modal PHP?', ['8.0', '8.2', '8.4'],
    isQuiz:       true,
    correctIndex: 1,        // indeks jawaban benar (0-based)
    solution:     'PHP 8.2 adalah versi LTS yang direkomendasikan'
);

// Multiple choice + tampilkan voter
$result = $client->sendPoll('@channel', 'Pilih framework:', ['Laravel', 'Symfony', 'Slim'],
    multipleChoice: true,
    publicVoters:   true,
    closePeriod:    3600    // otomatis tutup setelah 1 jam (detik)
);

// Poll dengan reply ke pesan tertentu
$result = $client->sendPoll('@group', 'Setuju?', ['Ya', 'Tidak'],
    replyTo: 42
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
    int                  $closePeriod    = 0,     // 0 = tidak ada batas waktu
    ?int                 $replyTo        = null
): array
```

**Return value:**
```php
['sent' => true, 'message_id' => 12345, 'date' => 1700000000, 'type' => 'poll']
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

> **Catatan:** Pin/unpin di channel dan supergroup membutuhkan hak admin `PIN_MESSAGES`. Di DM (antara dua pengguna), keduanya bisa saling pin pesan masing-masing.

---

## 18. Manajemen Admin & Ban

Library mendukung operasi admin di **tiga jenis grup** — semuanya pakai method yang sama, tidak perlu kode berbeda.

| Operasi | Supergroup / Channel | Grup Biasa |
|---------|---------------------|------------|
| Jadikan admin | ✅ (bisa set hak & judul) | ✅ (hanya on/off) |
| Cabut admin | ✅ | ✅ |
| Kick user | ✅ | ✅ |
| Ban user | ✅ (bisa sementara) | ✅ (permanen) |
| Unban user | ✅ | ❌ tidak ada ban list |
| Mute / Restrict | ✅ | ❌ — gunakan kickUser |

> Library otomatis mendeteksi tipe grup. Kamu tidak perlu menulis kode kondisi sendiri.

---

### 18.0 Setup Awal

```php
use XnoxsProto\Client\TelegramClient;

$client = TelegramClient::create(API_ID, 'API_HASH', 'session');
$client->connect();
$client->start('+6281234567890');
```

**Peer yang bisa digunakan** di semua method admin:
- `'@username'` — username Telegram
- `'-1001234567890'` — ID grup (dengan minus dan 100 di depan)
- angka integer — User ID atau Chat ID

---

### 18.1 Jadikan Admin

```php
// Supergroup / Channel — promosi dengan semua hak dasar
$result = $client->promoteAdmin('@supergroup', '@user');

// Supergroup / Channel — promosi dengan hak tertentu + judul kustom
// Konstanta ADMIN_* tersedia langsung, tidak perlu import class lain
$result = $client->promoteAdmin('@supergroup', '@user',
    rights: TelegramClient::ADMIN_DELETE_MESSAGES
          | TelegramClient::ADMIN_BAN_USERS
          | TelegramClient::ADMIN_PIN_MESSAGES
          | TelegramClient::ADMIN_OTHER,   // WAJIB agar status admin aktif
    rank: 'Moderator'
);

// Grup biasa — hanya bisa on/off, tanpa hak kustom atau judul
$result = $client->promoteAdmin('-1005225589449', '@user');
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

> Hak-hak di atas hanya berlaku di supergroup/channel. Di grup biasa, admin hanya bisa menghapus pesan dan kick anggota.

---

### 18.2 Cabut Status Admin

```php
// Berlaku untuk supergroup, channel, dan grup biasa
$result = $client->demoteAdmin('@supergroup', '@user');
// Returns: ['demoted' => true, 'user_id' => 123456]
```

---

### 18.3 Mute User (Cara Paling Mudah)

Larang user kirim pesan, tapi masih bisa membaca chat. Hanya berlaku untuk supergroup/channel.

```php
// Mute selamanya
$client->muteUser('@supergroup', '@spammer');

// Mute sementara — parameter kedua: durasi dalam detik
$client->muteUser('@supergroup', '@user', 3600);   // 1 jam
$client->muteUser('@supergroup', '@user', 86400);  // 1 hari
$client->muteUser('@supergroup', '@user', 604800); // 1 minggu
```

**Return value:**
```php
['restricted' => true, 'user_id' => 123456, 'muted_until' => 'selamanya']
['restricted' => true, 'user_id' => 123456, 'muted_until' => '2026-06-01 10:00:00']
```

---

### 18.4 Read-Only User (Cara Paling Mudah)

Larang user mengirim semua jenis konten (pesan, foto, video, stiker, link, dll). Hanya berlaku untuk supergroup/channel.

```php
// Read-only selamanya
$client->readOnlyUser('@supergroup', '@user');

// Read-only sementara
$client->readOnlyUser('@supergroup', '@user', 86400); // 1 hari
```

---

### 18.5 Kick User

User yang di-kick bisa bergabung kembali via link undangan. Berlaku untuk semua jenis grup.

```php
// Supergroup / Channel
$result = $client->kickUser('@supergroup', '@user');

// Grup biasa
$result = $client->kickUser('-1005225589449', '@user');

// Returns: ['kicked' => true, 'user_id' => 123456]
```

---

### 18.6 Ban User

```php
// Ban permanen — user tidak bisa bergabung kembali kecuali di-unban
$client->banUser('@supergroup', '@spammer');

// Ban sementara — otomatis unban setelah waktu habis
$client->banUser('@supergroup', '@user', untilDate: time() + 86400); // 1 hari

// Returns: ['banned' => true, 'user_id' => 123456, 'until' => 0]
```

> **Grup biasa:** `banUser()` langsung mengeluarkan user (tidak ada ban list). Gunakan `inviteToChannel()` untuk menambahkan kembali.

---

### 18.7 Unban User

```php
// Hanya berlaku untuk supergroup dan channel
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

// Returns: ['restricted' => true, 'user_id' => int, 'flags' => int, 'until' => int]
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

### 18.9 Hapus Semua Restriksi

```php
// Hapus mute / restrict — user kembali ke hak normal
$client->unbanUser('@supergroup', '@user');
```

---

### 18.10 Undang User ke Supergroup / Channel

```php
// Undang satu user (perlu user ada di kontak atau pernah berinteraksi)
$result = $client->inviteToChannel('@supergroup', ['@user1', '@user2']);
// Returns: ['invited' => true, 'count' => 2]
```

> Jika muncul error `USER_NOT_MUTUAL_CONTACT`, artinya user tersebut membatasi siapa yang bisa menambahkan mereka ke grup. Ini batasan Telegram, bukan bug library.

---

### Contoh Lengkap — Bot Moderasi Otomatis

```php
use XnoxsProto\Client\TelegramClient;

$client = TelegramClient::create(API_ID, 'API_HASH', 'session');
$client->connect();
$client->start('+6281234567890');

$group = '@mygroup';

// Jadikan @user1 sebagai moderator
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

$client->disconnect();
```

---

## 19. Cari Pesan (Search)

### 19.1 Cari di Chat Tertentu

```php
// Cari teks di dalam satu chat
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

$voices = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_VOICE);

$music = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_MUSIC);

$gifs = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_GIF);

$links = $client->search('@supergroup', '', limit: 50,
    filter: MessagesSearchRequest::FILTER_URL);
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

**Signature lengkap:**
```php
search(
    string|int|InputPeer $peer,
    string               $query,
    int                  $limit    = 20,
    int                  $offsetId = 0,
    int                  $filter   = MessagesSearchRequest::FILTER_EMPTY
): array

searchGlobal(string $query, int $limit = 20): array
```

Keduanya return format yang sama seperti `getHistory()`.

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
$chatId = $result['chat_id'];  // dari createChat()
$info   = $client->getFullChat($chatId);

echo "ID     : {$info['id']}\n";
echo "Judul  : {$info['title']}\n";
echo "Tentang: {$info['about']}\n";
echo "Anggota: {$info['participants_count']}\n";
echo "Tipe   : {$info['type']}\n";  // selalu 'chat' untuk basic group
```

**Signature:**
```php
getFullChat(int $chatId): array
```

**Return value:**
```php
[
    'id'                 => 5016290987,    // ID bare MTProto (positif)
    'title'              => 'Nama Grup',   // bisa kosong ('') jika parsed saat grup baru dibuat
    'about'              => '',            // selalu ada (string kosong jika belum diisi)
    'participants_count' => 2,             // termasuk creator
    'type'               => 'chat',        // selalu 'chat' untuk basic group
]
```

> **Catatan `title`:** Nilai `title` mungkin berupa string kosong (`''`) jika `getFullChat()` dipanggil segera setelah `createChat()`. Hal ini karena field judul diambil dari vektor `chats` di respons server — jika server tidak menyertakannya (race condition atau cache kosong), `title` akan `null` atau `''`. Untuk mendapatkan judul yang pasti, simpan `$result['title']` dari `createChat()` atau ambil dari `getDialogs()`.

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

**Signature:**
```php
getAdminChannels(int $dialogLimit = 200): array
// $dialogLimit — jumlah dialog yang di-scan untuk mencari channel yang di-admin
```

**Return value — array of:**
```php
[
    'id'            => 123456789,
    'access_hash'   => 987654321,
    'title'         => 'Nama Channel',
    'username'      => 'nama_channel',  // null jika private
    'members'       => 5000,
    'is_supergroup' => false,
    'is_channel'    => true,
    'role'          => 'creator',       // 'creator' atau 'admin'
]
```

> **Cara kerja:** Mengambil dialog, memfilter channel/supergroup, lalu memanggil `channels.getParticipants(filter=admins)` untuk masing-masing guna memverifikasi apakah user ID aktif ada di daftar admin.

---

## 22. Daftar Anggota Channel / Grup (getChannelMembers)

Ambil daftar anggota dari channel broadcast, supergroup, **maupun grup biasa (basic group)**. Method ini otomatis mendeteksi tipe peer dan menggunakan API yang tepat di balik layar.

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

**Contoh output:**
```
👑 Budi Santoso (ID: 123456789) — creator
🛡️ Ani Rahayu (ID: 987654321) — admin
👤 Citra Dewi (ID: 111222333) — member
```

### 22.3 Filter Anggota (Supergroup & Channel saja)

```php
// Hanya admin dan creator
$admins = $client->getChannelMembers('@supergroup', filter: 'admins');

// Hanya bot
$bots = $client->getChannelMembers('@supergroup', filter: 'bots');

// Anggota yang di-ban
$banned = $client->getChannelMembers('@supergroup', filter: 'banned');

// Anggota terbaru (default)
$recent = $client->getChannelMembers('@supergroup', filter: 'recent');
```

> **Catatan:** Filter `'recent'` hanya tersedia di supergroup, bukan channel broadcast. Untuk channel broadcast gunakan `'admins'` atau `'bots'`. Untuk grup biasa, semua filter diabaikan — seluruh anggota selalu dikembalikan.

### 22.4 Pagination (Supergroup & Channel saja)

```php
$page1 = $client->getChannelMembers('@supergroup', offset: 0,   limit: 100);
$page2 = $client->getChannelMembers('@supergroup', offset: 100, limit: 100);
```

> Untuk grup biasa, pagination tidak berlaku — semua anggota dikembalikan dalam satu panggilan.

### 22.5 Contoh: Tampilkan Anggota Grup Biasa

```php
// Bisa pakai ID numerik grup biasa
$members = $client->getChannelMembers(123456789);

echo "Total anggota: " . count($members) . "\n";
foreach ($members as $m) {
    $role = $m['role']; // 'creator' | 'admin' | 'member'
    printf("  [%-7s]  ID:%-12d  %s\n", $role, $m['user_id'], $m['display']);
}
```

### 22.6 Signature Lengkap

```php
getChannelMembers(
    string|int|InputPeer $channel,          // username, ID, atau InputPeer
    string               $filter = 'recent', // 'recent' | 'admins' | 'bots' | 'banned'
    int                  $offset = 0,        // diabaikan untuk grup biasa
    int                  $limit  = 100       // maks 200; diabaikan untuk grup biasa
): array
```

### 22.7 Return Value

Return value sama untuk semua tipe peer — array dari:

```php
[
    'user_id'     => 123456789,
    'username'    => 'budisantoso',   // null jika tidak punya username
    'first_name'  => 'Budi',
    'last_name'   => 'Santoso',
    'display'     => 'Budi Santoso',  // nama lengkap untuk display
    'phone'       => null,            // biasanya null (privasi)
    'bot'         => false,
    'role'        => 'member',        // 'creator' | 'admin' | 'member' | 'banned' | 'left'
    'rank'        => null,            // custom title admin (misal "Moderator"), null jika tidak ada
    'date'        => 1700000000,      // unix timestamp bergabung (0 untuk creator di grup biasa)
    'access_hash' => 987654321,       // 0 untuk anggota grup biasa
]
```

**Nilai `role` yang mungkin:**

| Nilai | Deskripsi |
|-------|-----------|
| `'creator'` | Pemilik/pembuat grup atau channel |
| `'admin'` | Admin yang ditunjuk |
| `'member'` | Anggota biasa |
| `'banned'` | Pengguna yang di-ban (hanya muncul dengan `filter='banned'`) |
| `'left'` | Pengguna yang sudah keluar (jarang dikembalikan) |

---

## 23. Manajemen Akun (Account Module)

Semua method di bawah diakses via `$client->getAccount()`.

### 23.1 Update Profil

Ubah nama depan, nama belakang, atau bio akun. Parameter yang tidak diisi (`null`) tidak akan diubah.

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
// Ganti username
$result = $account->updateUsername('username_baru');

// Hapus username (kosongkan)
$result = $account->updateUsername('');
```

> **Catatan:** Username harus unik di seluruh Telegram. Jika sudah dipakai orang lain, akan muncul error `USERNAME_OCCUPIED`.

### 23.3 Upload Foto Profil

```php
// Upload foto profil baru
$result = $account->uploadProfilePhoto('/path/foto.jpg');

echo "Foto ID: {$result['photo_id']}\n";
echo "Tanggal: " . date('d/m/Y', $result['date']) . "\n";

// Dengan progress upload
$result = $account->uploadProfilePhoto('/path/foto-hd.jpg',
    onProgress: fn($p, $t, $pct) => print("Upload: $pct%\r")
);
```

**Return value:**
```php
['photo_id' => 987654321, 'date' => 1700000000]
```

### 23.4 Lihat Semua Sesi Aktif

Ambil daftar semua perangkat/sesi yang sedang login ke akun ini.

```php
$sessions = $account->getAuthorizations();

foreach ($sessions as $sesi) {
    $aktif   = $sesi['current'] ? ' ← SESI INI' : '';
    $resmi   = $sesi['official_app'] ? '[Resmi]' : '[Third-party]';
    echo "$resmi {$sesi['app_name']} v{$sesi['app_version']}{$aktif}\n";
    echo "  Perangkat : {$sesi['device_model']} — {$sesi['platform']} {$sesi['system_version']}\n";
    echo "  Login dari: {$sesi['country']} ({$sesi['ip']})\n";
    echo "  Terakhir  : " . date('d/m/Y H:i', $sesi['date_active']) . "\n\n";
}
```

**Return value — array of:**
```php
[
    'hash'             => 1234567890,        // ID unik sesi (dipakai untuk terminate)
    'current'          => true,              // true = sesi yang sedang aktif
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

### 23.5 Terminate Sesi Tertentu

```php
// Ambil daftar sesi dulu
$sessions = $account->getAuthorizations();

// Terminate sesi yang bukan sesi aktif
foreach ($sessions as $sesi) {
    if (!$sesi['current']) {
        $berhasil = $account->resetAuthorization($sesi['hash']);
        echo $berhasil
            ? "Sesi {$sesi['device_model']} diterminasi\n"
            : "Gagal terminate\n";
    }
}
```

### 23.6 Terminate Semua Sesi Lain

Hapus semua sesi kecuali yang sedang aktif sekarang (berguna untuk "logout semua perangkat lain").

```php
$jumlah = $account->terminateAllOtherSessions();
echo "Berhasil menutup $jumlah sesi lain.\n";
```

### 23.7 Pengaturan Privasi

Kontrol siapa yang bisa melihat status online, nomor telepon, foto profil, dan lainnya.

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;

// Lihat aturan privasi untuk "kapan terakhir online"
$privasi = $account->getPrivacy(AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP);
echo "Status online: " . implode(', ', $privasi['rules']) . "\n";

// Set status online hanya bisa dilihat kontak
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP,
    ['allow_contacts']
);

// Set foto profil bisa dilihat semua orang
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_PROFILE_PHOTO,
    ['allow_all']
);

// Sembunyikan nomor telepon dari semua orang
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_PHONE_NUMBER,
    ['disallow_all']
);
```

**Konstanta `KEY_*`** (key privasi yang tersedia):

| Konstanta | Mengatur |
|-----------|----------|
| `KEY_STATUS_TIMESTAMP` | Kapan terakhir online |
| `KEY_CHAT_INVITE` | Siapa bisa undang ke grup |
| `KEY_PHONE_CALL` | Siapa bisa telepon |
| `KEY_PHONE_P2P` | P2P call (langsung/via server) |
| `KEY_FORWARDS` | Atribusi saat pesan di-forward |
| `KEY_PROFILE_PHOTO` | Foto profil |
| `KEY_PHONE_NUMBER` | Nomor telepon |
| `KEY_ADDED_BY_PHONE` | Siapa bisa tambahkan via nomor |
| `KEY_VOICE_MESSAGES` | Siapa bisa kirim voice note ke kamu |
| `KEY_ABOUT` | Bio/deskripsi profil |
| `KEY_BIRTHDAY` | Tanggal ulang tahun |

**Nilai rules yang valid:**
- `'allow_all'` — semua orang
- `'allow_contacts'` — hanya kontak
- `'disallow_all'` — tidak ada seorang pun

---

## 24. Download Media (FileDownloader)

Diakses via `$client->getDownloader()` atau langsung via shortcut di `$client`.

### 24.1 Download dari Pesan History (Paling Mudah)

Cara termudah: ambil pesan lewat `getHistory()`, lalu langsung download medianya. Library otomatis mendeteksi tipe media (foto, video, audio, dokumen, voice, gif).

```php
// Ambil 10 pesan terakhir
$messages = $client->getHistory('@channel', limit: 10);

foreach ($messages as $msg) {
    if ($msg['type'] === 'media') {
        $savePath = '/tmp/' . ($msg['filename'] ?? 'media_' . $msg['id']);
        $path = $client->downloadMedia($msg, $savePath);
        echo "Downloaded: $path\n";
    }
}
```

**Dengan progress:**
```php
$path = $client->downloadMedia($msg, '/tmp/file.mp4',
    onProgress: function (int $part, int $total, int $pct) {
        echo "\rDownload: $pct% ($part/$total)";
        if ($pct === 100) echo "\n";
    }
);
```

**Return value:** path file yang disimpan (string).

### 24.2 Download via FileDownloader Module

```php
$dl = $client->getDownloader();

// Download dari pesan (sama seperti $client->downloadMedia)
$path = $dl->downloadMedia($message, '/tmp/foto.jpg');

// Download foto berdasarkan ID eksplisit
$path = $dl->downloadPhoto(
    photoId:    123456789,
    accessHash: 987654321,
    fileRef:    'base64fileref...',
    savePath:   '/tmp/foto.jpg',
    thumbSize:  'y'   // 'y' = resolusi tertinggi (default)
);

// Download dokumen/video/audio berdasarkan ID eksplisit
$path = $dl->downloadDocument(
    docId:      123456789,
    accessHash: 987654321,
    fileRef:    'base64fileref...',
    savePath:   '/tmp/video.mp4'
);
```

### 24.3 Download ke Memori (Tanpa Menyimpan ke Disk)

Untuk file kecil yang langsung diproses, tanpa perlu membuat file sementara.

```php
$dl = $client->getDownloader();

// Download dokumen ke memori
$binaryData = $dl->downloadToMemory(
    docId:      123456789,
    accessHash: 987654321,
    fileRef:    'base64fileref...'
);
echo "Ukuran: " . strlen($binaryData) . " bytes\n";

// Download foto ke memori
$binaryData = $dl->downloadPhotoToMemory(
    photoId:    123456789,
    accessHash: 987654321,
    fileRef:    'base64fileref...',
    thumbSize:  'y'
);

// Langsung proses, misalnya OCR atau analisis gambar
imagecreatefromstring($binaryData);
```

### 24.4 Ambil Info Media dari Pesan

Saat menggunakan `getHistory()`, field media tersedia dalam dict pesan:

```php
$messages = $client->getHistory('@channel', limit: 20);

foreach ($messages as $msg) {
    if (empty($msg['media'])) continue;

    $media = $msg['media'];
    $tipe  = $media['type'] ?? 'unknown';

    switch ($tipe) {
        case 'photo':
            echo "Foto — ID: {$media['photo_id']}\n";
            $path = $client->downloadMedia($msg, "/tmp/foto_{$msg['id']}.jpg");
            break;

        case 'document':
        case 'video':
        case 'audio':
        case 'voice':
            $nama = $media['filename'] ?? "file_{$msg['id']}";
            $mime = $media['mime'] ?? 'application/octet-stream';
            echo "$tipe — $nama ($mime)\n";
            $path = $client->downloadMedia($msg, "/tmp/$nama");
            break;
    }
}
```

### 24.5 Signature Lengkap

```php
// Shortcut di TelegramClient (memanggil FileDownloader secara internal):
$client->downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
$client->downloadDocument(int $docId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null): string
$client->downloadPhoto(int $photoId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null, string $thumbSize = 'y'): string

// Langsung via FileDownloader:
$dl = $client->getDownloader();
$dl->downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
$dl->downloadPhoto(int $photoId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null, string $thumbSize = 'y'): string
$dl->downloadDocument(int $docId, int $accessHash, string $fileRef, string $savePath, ?callable $onProgress = null): string
$dl->downloadToMemory(int $docId, int $accessHash, string $fileRef): string
$dl->downloadPhotoToMemory(int $photoId, int $accessHash, string $fileRef, string $thumbSize = 'y'): string
```

> **Chunk size download:** 1 MB per request. Untuk file besar, progress callback sangat disarankan.

---

## 25. Edit & Hapus Pesan

### 25.1 Edit Pesan

Ubah teks pesan yang sudah dikirim. Hanya bisa mengedit pesan milik sendiri (atau pesan apapun jika akun adalah admin channel).

```php
// Edit pesan di DM / grup
$result = $client->editMessage('@username', $msgId, 'Teks yang sudah diedit');
// Returns: ['edited' => true, 'message_id' => int]

// Edit pesan di channel (perlu hak admin edit messages)
$result = $client->editMessage('@channel', $msgId, 'Pengumuman diperbarui!');

// Menggunakan ID numerik
$result = $client->editMessage(123456789, $msgId, 'Teks baru');
```

**Signature:**
```php
editMessage(
    string|int|InputPeer $peer,
    int                  $msgId,
    string               $text
): array
// Returns: ['edited' => true, 'message_id' => int]
```

> **Batas waktu edit:** Telegram membatasi edit pesan hingga 48 jam setelah dikirim (untuk akun biasa). Channel tidak ada batas waktu jika kamu admin.

### 25.2 Hapus Pesan

```php
// Hapus satu atau beberapa pesan di DM / grup biasa
// (peer = null untuk DM dan basic group)
$result = $client->deleteMessages([$msgId1, $msgId2]);
// Returns: ['deleted' => true, 'ids' => [int, ...]]

// Hapus pesan di channel / supergroup (peer wajib diisi)
$result = $client->deleteMessages([$msgId1, $msgId2], peer: '@channel');

// Hapus hanya dari sisi sendiri (tidak menghapus untuk pihak lain)
$result = $client->deleteMessages([$msgId], revoke: false);
```

**Signature:**
```php
deleteMessages(
    array                          $ids,
    string|int|InputPeer|null      $peer   = null,  // wajib untuk channel/supergroup
    bool                           $revoke = true    // true = hapus untuk semua pihak
): array
// Returns: ['deleted' => true, 'ids' => array]
```

> **Catatan:** `$revoke = true` menghapus pesan untuk semua orang (default). `$revoke = false` hanya menghapus dari sisi kamu. Untuk channel/supergroup, `$peer` harus diisi karena ID pesan bersifat lokal per channel.

### 25.3 Contoh: Edit lalu Hapus Setelah Delay

```php
// Kirim pesan
$sent = $client->sendMessage('@username', 'Pesan sementara...');
$msgId = $sent['message_id'];

sleep(5);

// Edit setelah 5 detik
$client->editMessage('@username', $msgId, 'Pesan sudah diperbarui!');

sleep(10);

// Hapus setelah 10 detik lagi
$client->deleteMessages([$msgId], peer: '@username');
echo "Selesai\n";
```

---

## 26. Proxy SOCKS5

Routing semua traffic MTProto melalui proxy SOCKS5. Harus diset sebelum memanggil `connect()`.

### 26.1 Set Proxy

```php
$client = TelegramClient::create($apiId, $apiHash, 'session');

// Proxy tanpa autentikasi
$client->setProxy('127.0.0.1', 1080);

// Proxy dengan username & password
$client->setProxy('proxy.example.com', 1080, 'user', 'pass');

// Setelah set proxy, baru connect
$client->connect();
$client->start('+6281234567890');
```

### 26.2 Hapus Proxy

```php
// Hapus proxy (kembali ke koneksi langsung)
$client->clearProxy();

// Perlu reconnect untuk menerapkan perubahan
$client->disconnect();
$client->connect();
```

**Signature:**
```php
setProxy(string $host, int $port, ?string $user = null, ?string $pass = null): void
clearProxy(): void
```

> **Catatan:** Proxy berlaku untuk semua koneksi TCP termasuk DC migration. Jika proxy tidak tersedia, koneksi akan gagal dengan error socket.

---

## 27. Resolve Peer & Username

### 27.1 resolvePeer() — Ubah Peer ke InputPeer

Mengubah berbagai format peer (username, nomor telepon, ID, dsb.) menjadi `InputPeer` yang siap digunakan di API call. Hasilnya di-cache otomatis.

```php
// Dari username
$peer = $client->resolvePeer('@durov');
// Returns: InputPeer object

// Dari nomor telepon
$peer = $client->resolvePeer('+6281234567890');

// Dari user ID
$peer = $client->resolvePeer(123456789);

// Saved Messages
$peer = $client->resolvePeer('me');

// Dari link t.me
$peer = $client->resolvePeer('t.me/telegram');

// Gunakan hasilnya di method lain
$client->sendMessage($peer, 'Halo!');
$client->getHistory($peer, limit: 10);
```

**Signature:**
```php
resolvePeer(string|int $peer): InputPeer
// Throws RuntimeException jika peer tidak ditemukan
```

Format peer yang didukung:

| Format | Contoh | Keterangan |
|--------|--------|------------|
| `@username` | `'@durov'` | Username dengan tanda @ |
| `username` | `'durov'` | Username tanpa tanda @ (juga valid) |
| `+phone` | `'+6281234567890'` | Nomor telepon internasional |
| `int` | `123456789` | User/chat/channel ID |
| `'me'`/`'self'` | `'me'` | Saved Messages sendiri |
| `t.me/...` | `'t.me/telegram'` | Link t.me langsung |

### 27.2 Messages.resolveUsername() — Cari Info User/Channel by Username

Cari informasi dasar tentang user atau channel berdasarkan username-nya.

```php
$messages = $client->getMessages();

$info = $messages->resolveUsername('telegram');

echo "Tipe     : {$info['type']}\n";       // 'user' | 'chat' | 'channel'
echo "ID       : {$info['id']}\n";
echo "Username : @{$info['username']}\n";
echo "Judul    : {$info['title']}\n";
echo "Bot      : " . ($info['bot'] ? 'Ya' : 'Tidak') . "\n";
```

**Return value:**
```php
[
    'type'        => 'channel',    // 'user' | 'chat' | 'channel'
    'id'          => 123456789,
    'access_hash' => 987654321,
    'title'       => 'Telegram',
    'username'    => 'telegram',
    'bot'         => false,
]
```

**Signature:**
```php
$client->getMessages()->resolveUsername(string $username): array
// $username tanpa '@'
// Throws RuntimeException jika username tidak ditemukan
```

### 27.3 InputPeer — Membuat Secara Manual (Low-level)

Jika kamu sudah punya ID dan access_hash, bisa membuat InputPeer langsung tanpa API call:

```php
use XnoxsProto\TL\Types\InputPeer;

$peer = InputPeer::user(123456789, 987654321);      // user biasa
$peer = InputPeer::chat(123456789);                  // basic group (tidak perlu access_hash)
$peer = InputPeer::channel(123456789, 987654321);    // channel/supergroup
$peer = InputPeer::self();                           // Saved Messages

// Pakai di method manapun
$client->sendMessage($peer, 'Halo!');
```

---

## 28. Status Koneksi & Info

### 28.1 Cek Status Koneksi

```php
if ($client->isConnected()) {
    echo "Terkoneksi ke Telegram\n";
} else {
    echo "Tidak terkoneksi\n";
}
```

### 28.2 Dapatkan API Layer

API layer adalah versi protokol Telegram yang dinegosiasikan dengan server.

```php
$layer = $client->getLayer();
echo "API Layer: $layer\n";   // Contoh output: API Layer: 214
```

### 28.3 Disconnect & Reconnect

```php
// Putus koneksi
$client->disconnect();

// Sambung kembali (session tetap ada — tidak perlu login ulang)
$client->connect();
echo "Berhasil reconnect\n";
```

### 28.4 Reconnect ke DC Tertentu

```php
// Konek ke DC 5 (Singapore)
$client->connect(dcId: 5);

// Konek ke DC 2 (Amsterdam) — default
$client->connect(dcId: 2);

// Konek ke DC manapun (auto-pilih dari session)
$client->connect();
```

**DC yang tersedia:**

| DC ID | IP | Lokasi |
|-------|----|--------|
| 1 | 149.154.175.53 | Miami, USA |
| 2 | 149.154.167.51 | Amsterdam (default) |
| 3 | 149.154.175.100 | Miami, USA |
| 4 | 149.154.167.91 | Amsterdam |
| 5 | 91.108.56.130 | Singapore |

### 28.5 Signature

```php
$client->connect(?int $dcId = null, bool $isReconnect = false): void
$client->disconnect(): void
$client->isConnected(): bool
$client->getLayer(): int
```

---

## 29. Raw Update Handler (onUpdate)

Selain `on(NewMessage)` yang sudah dibahas di section 12, library juga mendukung raw update handler yang menangkap **semua jenis update** dari Telegram — bukan hanya pesan baru.

### 29.1 Mendaftarkan Raw Handler

```php
use XnoxsProto\Events\RawUpdateEvent;

$client->onUpdate(function (RawUpdateEvent $event) {
    echo "Update tipe: {$event->type}\n";
});

$client->runUntilDisconnected();
```

### 29.2 Semua Tipe Update

```php
$client->onUpdate(function (RawUpdateEvent $event) use ($client) {
    switch ($event->type) {

        case 'new_message':
            // Pesan baru diterima
            $msg = $event->message;  // objek FullMessage
            echo "[NEW] {$msg->peerType}#{$msg->peerId} — {$msg->text}\n";
            break;

        case 'edit_message':
            // Pesan yang sudah ada diedit
            $msg = $event->message;  // objek FullMessage
            echo "[EDIT] Pesan ID {$msg->id} diedit: {$msg->text}\n";
            break;

        case 'delete_messages':
            // Satu atau lebih pesan dihapus
            $ids = $event->messageIds;  // int[] — array ID yang dihapus
            echo "[DELETE] Pesan dihapus: " . implode(', ', $ids) . "\n";
            break;

        case 'read_history':
            // Riwayat pesan ditandai sudah dibaca oleh pihak lain
            echo "[READ] Riwayat dibaca\n";
            break;

        case 'pinned_messages':
            // Pesan di-pin atau di-unpin
            echo "[PIN] Ada perubahan pesan yang di-pin\n";
            break;

        case 'user_status':
            // Status online/offline user berubah
            $userId = $event->userId;  // int
            $online = $event->online;  // bool
            $status = $online ? 'online' : 'offline';
            echo "[STATUS] User #$userId sekarang $status\n";
            break;
    }
});
```

### 29.3 Field RawUpdateEvent

Field diakses langsung sebagai properti (`$event->namaField`) melalui magic `__get`:

```php
$event->type        // string — tipe update (lihat tabel di bawah)

// Tersedia untuk: new_message, edit_message
$event->message     // FullMessage

// Tersedia untuk: delete_messages, pinned_messages
$event->messageIds  // int[]

// Tersedia untuk: delete_messages
$event->channelId   // ?int — null untuk DM/group biasa

// Tersedia untuk: read_history
$event->direction   // 'in' | 'out'
$event->maxId       // int
$event->peerId      // mixed

// Tersedia untuk: pinned_messages
$event->pinned      // bool — true = di-pin, false = di-unpin

// Tersedia untuk: user_status
$event->userId      // int
$event->online      // bool
$event->wasOnline   // int — unix timestamp terakhir online
```

**Semua nilai `$event->type`:**

| Tipe | Deskripsi | Field Tambahan |
|------|-----------|----------------|
| `new_message` | Pesan baru diterima | `$event->message` |
| `edit_message` | Pesan yang ada diedit | `$event->message` |
| `delete_messages` | Pesan dihapus | `$event->messageIds`, `$event->channelId` |
| `read_history` | Riwayat dibaca oleh peer | `$event->direction`, `$event->maxId` |
| `pinned_messages` | Perubahan pesan yang di-pin | `$event->messageIds`, `$event->pinned` |
| `user_status` | Status online user berubah | `$event->userId`, `$event->online`, `$event->wasOnline` |

### 29.4 Field FullMessage (untuk new_message & edit_message)

```php
$msg = $event->message;

$msg->id          // int    — ID pesan
$msg->text        // string — teks pesan
$msg->out         // bool   — true jika pesan kita yang kirim
$msg->date        // int    — unix timestamp
$msg->peerId      // int    — ID chat/user/channel tempat pesan berada
$msg->peerType    // string — 'user' | 'chat' | 'channel'
$msg->fromUserId  // ?int   — ID pengirim (null jika anonim/channel)
$msg->replyMarkup // ?array — inline keyboard (null jika tidak ada)

// Methods:
$msg->respond(string $text)           // balas di chat yang sama
$msg->click(int $row, int $col)       // klik tombol inline keyboard
$msg->getButtonText(int $row, int $col): ?string
$msg->getButtonUrl(int $row, int $col): ?string
```

### 29.5 Gabungkan onUpdate dan on() Bersamaan

Kamu bisa daftarkan keduanya sekaligus — keduanya berjalan bersama dalam event loop yang sama:

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\RawUpdateEvent;

// Handler spesifik pesan baru (lebih mudah dengan filter)
$client->on(new NewMessage(incoming: true, pattern: '/start'), function ($event) {
    echo "Ada yang /start!\n";
    $event->message->respond('Halo! Apa kabar?');
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

---

*Dokumentasi ini dibuat berdasarkan implementasi nyata XnoxsProto versi 1.1.0 (Layer 214).*  
*Semua method, parameter, dan return value mencerminkan kode yang benar-benar berjalan.*

---

## 30. Catatan Kompatibilitas Layer 214

Versi 1.1.0 memperbarui parser TL untuk menyesuaikan perubahan konstruktor di **API Layer 214**. Jika kamu menggunakan library sebelum versi ini dan mengalami masalah "anggota tidak ditemukan" atau parsing error, pastikan sudah menggunakan versi terbaru.

### 30.1 Perubahan Konstruktor TL (Layer 148 → 214)

Tabel berikut mencantumkan constructor ID yang berubah antara layer lama dan Layer 214 yang kini digunakan library:

| Konstruktor | ID Lama | ID Baru (Layer 214) | Perubahan Utama |
|-------------|---------|---------------------|-----------------|
| `channels.channelParticipants` | `0xf0173fe9` | `0x9ab0feaf` | — |
| `channelParticipant` | `0x1bd54456` | `0xcb397619` | Tambah `flags` + `subscription_until_date` |
| `channelParticipantSelf` | `0xa9478a1a` | `0x4f607bef` | `inviter_id` sekarang selalu ada |
| `channelParticipantBanned` | `0xd5f0ad91` | `0x6df8014e` | — |
| `chatFull` | `0x4dbdc099` | `0x2633421b` | `flags2` dipindah ke **akhir** struct |
| `chatParticipants` | `0x3f460fed` | `0x3cbc93f8` | `chat_id`: `int` → `long` |
| `chatParticipantsForbidden` | `0x8763d3d7` | `0x8763d3e1` | — |
| `chatParticipant` | `0xc8d7493e` | `0xc02d4007` | — |
| `chatParticipantAdmin` | `0xe2d6e436` | `0xa0933f5b` | — |
| `chatParticipantCreator` | `0xda13538a` | `0xe46bcee4` | Hapus `flags`+`rank`; hanya `user_id: long` |

### 30.2 Dampak & Gejala

Jika constructor ID tidak sesuai layer yang digunakan server, library akan gagal mem-parse respons dan `getChannelMembers()` mengembalikan array kosong meski anggota sebenarnya ada. Semua constructor di atas sudah diperbarui di versi 1.1.0.

### 30.3 Perubahan `interactive_login.php` v4.0

| Fitur | Sebelumnya (v3.x) | Sekarang (v4.0) |
|-------|-------------------|-----------------|
| Menu `[12]` | Hanya channel/supergroup | + Otomatis deteksi grup biasa |
| Ikon role anggota | Tidak ada | 👑 creator · 🛡️ admin · 👤 member |
| Limit anggota di picker | 100 | 200 |
| Label menu | "Daftar anggota channel" | "Daftar anggota (channel / supergroup / grup biasa)" |

---

## 31. Pengaturan Privasi (Account Privacy)

> **Lihat juga:** Section 23.7 untuk contoh penggunaan singkat dalam konteks Account module.

Kontrol penuh atas siapa yang bisa melihat informasi profilmu di Telegram menggunakan `Account::getPrivacy()` dan `Account::setPrivacy()`.

### 31.1 Membaca Pengaturan Privasi

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;

$account = $client->getAccount();

// Cek pengaturan status online ("kapan terakhir online")
$privasi = $account->getPrivacy(AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP);
echo "Status online: " . implode(', ', $privasi['rules']) . "\n";

// Cek pengaturan foto profil
$privasi = $account->getPrivacy(AccountGetPrivacyRequest::KEY_PROFILE_PHOTO);
echo "Foto profil: " . implode(', ', $privasi['rules']) . "\n";
```

**Return value `getPrivacy()`:**
```php
[
    'rules' => ['allow_all'],   // atau 'allow_contacts', 'disallow_all'
]
```

### 31.2 Mengubah Pengaturan Privasi

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;

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

// Tanggal ulang tahun hanya untuk kontak
$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_BIRTHDAY,
    ['allow_contacts']
);
```

**Signature:**
```php
$account->getPrivacy(int $key): array
$account->setPrivacy(int $key, array $rules): void

// $key   — salah satu konstanta KEY_* dari AccountGetPrivacyRequest
// $rules — array berisi satu rule string: 'allow_all', 'allow_contacts', atau 'disallow_all'
```

### 31.3 Referensi Lengkap Key & Rules

**Key privasi yang tersedia** (gunakan dari `AccountGetPrivacyRequest::KEY_*`):

| Konstanta | Hex | Mengatur |
|-----------|-----|----------|
| `KEY_STATUS_TIMESTAMP` | `0x4f96cb18` | Kapan terakhir online |
| `KEY_CHAT_INVITE` | `0xbdfb0426` | Siapa bisa undang ke grup |
| `KEY_PHONE_CALL` | `0xfabadc5f` | Siapa bisa menelepon |
| `KEY_PHONE_P2P` | `0xdb9e70d2` | P2P call (langsung/via server) |
| `KEY_FORWARDS` | `0xa4dd4c08` | Atribusi saat pesan di-forward |
| `KEY_PROFILE_PHOTO` | `0x5719bacc` | Foto profil |
| `KEY_PHONE_NUMBER` | `0x0352dafa` | Nomor telepon |
| `KEY_ADDED_BY_PHONE` | `0xd1219bdd` | Siapa bisa tambahkan via nomor |
| `KEY_VOICE_MESSAGES` | `0xaee69d68` | Siapa bisa kirim voice note ke kamu |
| `KEY_ABOUT` | `0x3823cc40` | Bio/deskripsi profil |
| `KEY_BIRTHDAY` | `0xd65a11cc` | Tanggal ulang tahun |

**Rules yang valid:**

| Rule string | Artinya |
|-------------|---------|
| `'allow_all'` | Semua orang |
| `'allow_contacts'` | Hanya kontak |
| `'disallow_all'` | Tidak ada seorang pun |

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

---

### 32.1 Membuat Grup Biasa (Basic Group)

```php
// Buat grup baru dengan beberapa anggota
$result = $client->createChat('Nama Grup Kita', ['@user1', '@user2', 123456789]);

$chatId = $result['chat_id'];  // ID grup yang baru dibuat — simpan ini!
echo $result['title'];         // 'Nama Grup Kita'
echo count($result['user_ids']); // jumlah anggota yang diundang
```

**Signature:**
```php
createChat(string $title, string|int|InputPeer|array $users): array
```

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `$title` | string | Judul grup (1–255 karakter) |
| `$users` | string\|int\|InputPeer\|array | Satu user atau array of user yang diundang |

**Return:**
```php
[
    'created'  => true,
    'title'    => 'Nama Grup Kita',
    'user_ids' => [123456789],   // ID user yang diundang
    'chat_id'  => 5016290987,    // ID grup baru — gunakan untuk semua operasi berikutnya
]
```

> **Penting:** Selalu simpan `$result['chat_id']` setelah `createChat()`. Nilai ini adalah ID bare MTProto yang dipakai oleh `deleteChat()`, `addChatUser()`, `getFullChat()`, dan method lainnya.
>
> Minimal satu user harus diundang saat pembuatan. Untuk grup dengan potensi lebih dari 200 anggota, gunakan `createChannel()` dengan `megagroup=true` (supergroup).

**Batasan Basic Group vs Supergroup:**

| Fitur | Basic Group | Supergroup |
|-------|-------------|------------|
| Maks. anggota | 200 | Tidak terbatas |
| Deskripsi (`editChatAbout`) | ❌ | ✅ |
| Restrict parsial per user | ❌ | ✅ |
| Username publik | ❌ | ✅ |
| Slow mode | ❌ | ✅ |
| Ban list | ❌ | ✅ |

---

### 32.2 Membuat Supergroup atau Channel Broadcast

```php
// Buat supergroup (bisa publik, bisa ribuan anggota)
$sg = $client->createChannel('Nama Supergroup', 'Deskripsi opsional', megagroup: true);

// Buat channel broadcast (hanya admin yang bisa kirim pesan)
$ch = $client->createChannel('Nama Channel', 'Deskripsi channel', megagroup: false);

// Buat supergroup dengan mode forum/topik
$forum = $client->createChannel('Forum Diskusi', 'Topik bebas', megagroup: true, forum: true);
```

**Signature:**
```php
createChannel(
    string $title,
    string $about     = '',
    bool   $megagroup = false,
    bool   $forum     = false
): array
```

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `$title` | string | Judul channel/supergroup |
| `$about` | string | Deskripsi (opsional) |
| `$megagroup` | bool | `true` = supergroup, `false` = broadcast channel |
| `$forum` | bool | `true` = aktifkan mode topik (hanya untuk megagroup) |

**Return:**
```php
['created' => true, 'title' => '...', 'about' => '...', 'megagroup' => true, 'forum' => false]
```

---

### 32.3 Menghapus Grup / Channel / Supergroup

```php
// Hapus basic group — gunakan chat_id dari createChat() (nilai positif MTProto)
$chatId = $result['chat_id'];  // dari createChat()
$result = $client->deleteChat($chatId);

// Atau gunakan InputPeer::chat() jika peerCache sudah tidak aktif (koneksi baru)
use XnoxsProto\TL\Types\InputPeer;
$result = $client->deleteChat(InputPeer::chat(5016290987));

// Hapus channel/supergroup (deteksi otomatis via peerCache)
$result = $client->deleteChat('@channelku');

echo $result['deleted'];  // true
echo $result['peer_id'];  // ID peer yang dihapus
```

**Signature:**
```php
deleteChat(string|int|InputPeer $peer): array
```

**Return:**
```php
['deleted' => true, 'peer_id' => 5016290987]
```

> Method ini mendeteksi tipe peer secara otomatis:
> - Basic group → `messages.deleteChat` (Layer 214: `#5bd0ee50`, `chat_id:long`)
> - Channel/supergroup → `channels.deleteChannel`
>
> Hanya bisa dilakukan oleh **creator/owner**. Operasi ini **permanen** dan tidak bisa dibatalkan.
>
> **Catatan format ID:** Nilai `chat_id` dari `createChat()['chat_id']` adalah ID bare MTProto (bilangan positif). Jangan gunakan format Bot API (negatif seperti `-123456789`) — gunakan nilai langsung dari `createChat()` atau bungkus dengan `InputPeer::chat($id)` untuk menghindari salah deteksi tipe.

---

**Kapan pakai `InputPeer::chat()` secara eksplisit?**

Jika `deleteChat()` dipanggil di koneksi/sesi yang berbeda dari saat `createChat()` (sehingga `peerCache` tidak memiliki entri untuk `chat_id` ini), library tidak bisa menentukan tipe peer secara otomatis. Dalam kasus ini, teruskan `InputPeer::chat($id)` agar library tahu ini adalah basic group:

```php
use XnoxsProto\TL\Types\InputPeer;

$client->deleteChat(InputPeer::chat(5016290987));  // eksplisit → messages.deleteChat
$client->deleteChat(5016290987);                   // hanya aman jika createChat() dipanggil di sesi yang sama
```

---

### 32.4 Upgrade Basic Group ke Supergroup

```php
// Upgrade group biasa menjadi supergroup
$result = $client->migrateChat(123456789); // chat_id tanpa prefix

echo $result['migrated'];      // true
echo $result['old_chat_id'];   // ID lama yang sudah tidak berlaku

// Setelah migrasi, temukan supergroup baru lewat getDialogs()
$dialogs = $client->getDialogs(50);
// Supergroup baru akan tampil dengan is_supergroup = true
```

**Signature:**
```php
migrateChat(int $chatId): array
```

> Setelah migrasi, `chat_id` lama tidak bisa dipakai lagi. Semua anggota otomatis dipindahkan ke supergroup baru. Riwayat pesan terbawa.

---

### 32.5 Mengubah Judul Grup / Channel / Supergroup

```php
// Ubah judul — tipe peer dideteksi otomatis
$result = $client->editChatTitle($chatId, 'Nama Baru Grup');       // basic group (int)
$result = $client->editChatTitle('@channelku', 'Judul Baru');      // channel via username
$result = $client->editChatTitle(-100123456789, 'Judul Baru');     // channel via ID Bot API

echo $result['updated'];  // true
echo $result['peer_id'];  // ID peer yang diubah
echo $result['title'];    // 'Nama Baru Grup'
```

**Signature:**
```php
editChatTitle(string|int|InputPeer $peer, string $title): array
```

**Return:**
```php
['updated' => true, 'peer_id' => 5016290987, 'title' => 'Nama Baru Grup']
```

| Tipe peer | TL yang dipakai |
|-----------|-----------------|
| Basic group | `messages.editChatTitle` |
| Channel/supergroup | `channels.editTitle` |

---

### 32.6 Mengubah Deskripsi Channel / Supergroup

```php
// Ubah deskripsi/bio channel atau supergroup
$result = $client->editChatAbout('@channelku', 'Deskripsi baru yang lebih menarik');
$result = $client->editChatAbout('@supergroup', ''); // kosongkan deskripsi

echo $result['updated'];  // true
echo $result['about'];    // deskripsi baru
```

**Signature:**
```php
editChatAbout(string|int|InputPeer $channel, string $about): array
```

**Return:**
```php
['updated' => true, 'peer_id' => 123456789, 'about' => 'Deskripsi baru']
```

> **Basic group tidak didukung.** Memanggil `editChatAbout()` pada basic group akan melempar `RuntimeException`:
>
> ```php
> // AKAN THROW — basic group tidak punya deskripsi
> $client->editChatAbout($chatId, 'tentang grup');
> // RuntimeException: editChatAbout: basic group tidak memiliki deskripsi.
> //                   Gunakan migrateChat() untuk upgrade ke supergroup terlebih dahulu.
> ```
>
> Solusi: upgrade dulu ke supergroup dengan `migrateChat($chatId)`, lalu panggil `editChatAbout()`.

---

### 32.7 Tambah User ke Basic Group

```php
// Tambah satu user ke basic group
$result = $client->addChatUser(123456789, '@username');

// Dengan fwd_limit: user baru bisa lihat 50 pesan terakhir
$result = $client->addChatUser(123456789, 987654321, fwdLimit: 50);

echo $result['added'];    // true
echo $result['user_id'];  // ID user yang ditambahkan
```

**Signature:**
```php
addChatUser(
    int                  $chatId,
    string|int|InputPeer $user,
    int                  $fwdLimit = 100
): array
```

| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `$chatId` | int | ID basic group (dari `createChat()['chat_id']`) |
| `$user` | string\|int\|InputPeer | User yang akan ditambahkan (`@username`, ID, atau InputPeer) |
| `$fwdLimit` | int | Berapa pesan terakhir yang bisa dilihat user baru (0–100) |

**Return:**
```php
['added' => true, 'chat_id' => 123456789, 'user_id' => 987654321]
```

> Untuk **supergroup/channel**, gunakan `inviteToChannel()` (lihat Section 18.4).
>
> User yang sebelumnya di-kick via `kickUser()` atau `banUser()` bisa ditambahkan kembali dengan `addChatUser()`. Basic group tidak memiliki ban list permanen — kick hanya mencegah user dari dalam grup, bukan dari ditambah ulang.

---

### 32.8 Slow Mode (Batasi Frekuensi Kirim Pesan)

```php
// Aktifkan slow mode 30 detik (anggota hanya bisa kirim pesan tiap 30 detik)
$result = $client->toggleSlowMode('@supergroup', 30);

// Nonaktifkan slow mode
$result = $client->toggleSlowMode('@supergroup', 0);

echo $result['slow_mode_enabled'];  // true / false
echo $result['slow_mode_seconds'];  // 30
```

**Signature:**
```php
toggleSlowMode(string|int|InputPeer $channel, int $seconds): array
```

**Nilai `$seconds` yang valid:**

| Nilai | Keterangan |
|-------|------------|
| `0` | Nonaktifkan slow mode |
| `10` | 10 detik |
| `30` | 30 detik |
| `60` | 1 menit |
| `300` | 5 menit |
| `900` | 15 menit |
| `3600` | 1 jam |

> Hanya berlaku untuk **supergroup**. Channel broadcast tidak mendukung slow mode.

---

### 32.9 Generate Link Undangan

```php
// Generate link undangan baru (satu kali pakai jika ada link sebelumnya)
$result = $client->exportInviteLink('@grupku');
echo $result['link'];  // 'https://t.me/+AbCdEfGhIjK'

// Revoke link lama dan buat link baru
$result = $client->exportInviteLink('@grupku', revokePermanent: true);

// Link dengan batas waktu (kadaluarsa 24 jam dari sekarang)
$result = $client->exportInviteLink('@grupku', expireDate: time() + 86400);

// Link dengan batas pemakaian 50 kali
$result = $client->exportInviteLink('@grupku', usageLimit: 50);

// Link dengan approval admin (join request)
$result = $client->exportInviteLink('@grupku', requestNeeded: true, title: 'Link VIP');

echo $result['link'];           // URL link undangan
echo $result['expire_date'];    // Unix timestamp kadaluarsa (null = selamanya)
echo $result['usage_limit'];    // Batas pemakaian (null = unlimited)
echo $result['request_needed']; // bool — apakah perlu approval admin
```

**Signature:**
```php
exportInviteLink(
    string|int|InputPeer $peer,
    bool                 $revokePermanent = false,
    bool                 $requestNeeded   = false,
    ?int                 $expireDate      = null,
    ?int                 $usageLimit      = null,
    string               $title           = ''
): array
```

**Return:**
```php
[
    'link'           => 'https://t.me/+AbCdEfGhIjK',
    'revoked'        => false,
    'expire_date'    => null,       // atau Unix timestamp
    'usage_limit'    => null,       // atau int
    'request_needed' => false,
    'title'          => '',
    'peer_id'        => 123456789,
]
```

---

### 32.10 Default Permission Anggota

Atur apa yang boleh dan tidak boleh dilakukan anggota secara default. Berlaku untuk **basic group maupun supergroup**.

```php
use XnoxsProto\TL\Functions\MessagesEditChatDefaultBannedRightsRequest as Perms;

// Larang anggota kirim stiker dan GIF — berlaku untuk basic group maupun supergroup
$result = $client->setDefaultPermissions(
    $chatId,                                          // basic group (int dari createChat)
    Perms::BAN_SEND_STICKERS | Perms::BAN_SEND_GIFS
);

// Larang anggota kirim pesan sama sekali (read-only group)
$result = $client->setDefaultPermissions(
    '@supergroup',
    Perms::BAN_ALL_SEND
);

// Kembalikan semua izin ke default (izinkan semua)
$result = $client->setDefaultPermissions($chatId, 0);

echo $result['updated'];        // true
echo $result['banned_rights'];  // bitmask flag yang dilarang (0 = semua diizinkan)
```

**Signature:**
```php
setDefaultPermissions(
    string|int|InputPeer $peer,
    int                  $bannedRights
): array
```

**Konstanta larangan yang tersedia:**

```php
use XnoxsProto\TL\Functions\MessagesEditChatDefaultBannedRightsRequest as Perms;

Perms::BAN_SEND_MESSAGES  // Larang kirim pesan teks (mute semua)
Perms::BAN_SEND_MEDIA     // Larang kirim semua media
Perms::BAN_SEND_STICKERS  // Larang kirim stiker
Perms::BAN_SEND_GIFS      // Larang kirim GIF
Perms::BAN_SEND_GAMES     // Larang main game Telegram
Perms::BAN_SEND_INLINE    // Larang pakai inline bot
Perms::BAN_EMBED_LINKS    // Larang kirim link
Perms::BAN_SEND_POLLS     // Larang buat polling
Perms::BAN_CHANGE_INFO    // Larang ubah info grup
Perms::BAN_INVITE_USERS   // Larang undang anggota baru
Perms::BAN_PIN_MESSAGES   // Larang pin pesan
Perms::BAN_MANAGE_TOPICS  // Larang kelola topik (forum)
Perms::BAN_SEND_PHOTOS    // Larang kirim foto
Perms::BAN_SEND_VIDEOS    // Larang kirim video
Perms::BAN_SEND_AUDIOS    // Larang kirim audio
Perms::BAN_SEND_DOCS      // Larang kirim dokumen
Perms::BAN_ALL_SEND       // Semua larangan kirim (read-only)
```

---

### 32.11 Tanda Tangan Admin di Channel (Signatures)

```php
// Aktifkan: setiap postingan tampilkan nama admin yang memposting
$client->toggleSignatures('@channelku', true);

// Nonaktifkan: postingan atas nama channel (anonim)
$client->toggleSignatures('@channelku', false);
```

**Signature:**
```php
toggleSignatures(string|int|InputPeer $channel, bool $enabled): array
```

**Return:**
```php
['updated' => true, 'channel_id' => 123, 'signatures_enabled' => true]
```

> Hanya berlaku untuk **channel broadcast**. Supergroup tidak mendukung fitur ini.

---

### 32.12 Wajib Join Sebelum Kirim Pesan

```php
// Aktifkan: user harus join grup dulu sebelum bisa kirim pesan
$client->toggleJoinToSend('@supergroup', true);

// Nonaktifkan: user bisa kirim pesan tanpa join terlebih dahulu
$client->toggleJoinToSend('@supergroup', false);
```

**Signature:**
```php
toggleJoinToSend(string|int|InputPeer $channel, bool $enabled): array
```

**Return:**
```php
['updated' => true, 'channel_id' => 123, 'join_to_send' => true]
```

---

### 32.13 Wajib Persetujuan Admin untuk Join

```php
// Aktifkan: semua permintaan join harus disetujui admin
$client->toggleJoinRequest('@supergroup', true);

// Nonaktifkan: siapa saja langsung bisa join (jika publik)
$client->toggleJoinRequest('@supergroup', false);
```

**Signature:**
```php
toggleJoinRequest(string|int|InputPeer $channel, bool $enabled): array
```

**Return:**
```php
['updated' => true, 'channel_id' => 123, 'join_request' => true]
```

---

### 32.14 Contoh Lengkap: Setup Supergroup Baru

```php
// 1. Buat supergroup baru
$client->createChannel('Tim Alpha', 'Grup internal Tim Alpha', megagroup: true);

// 2. Temukan ID supergroup yang baru dibuat
$dialogs = $client->getDialogs(10);
$newGroup = null;
foreach ($dialogs as $d) {
    if ($d['title'] === 'Tim Alpha' && ($d['is_supergroup'] ?? false)) {
        $newGroup = $d;
        break;
    }
}

if (!$newGroup) {
    die("Supergroup tidak ditemukan\n");
}

$groupId   = $newGroup['id'];
$groupHash = $newGroup['access_hash'];

echo "Supergroup dibuat: ID=$groupId\n";

// 3. Invite anggota
$client->inviteToChannel($groupId, ['@user1', '@user2', '@user3']);

// 4. Set slow mode 30 detik
$client->toggleSlowMode($groupId, 30);

// 5. Larang anggota ubah info grup
use XnoxsProto\TL\Functions\MessagesEditChatDefaultBannedRightsRequest as Perms;
$client->setDefaultPermissions($groupId, Perms::BAN_CHANGE_INFO | Perms::BAN_PIN_MESSAGES);

// 6. Generate link undangan dengan batas 100 orang
$invite = $client->exportInviteLink($groupId, usageLimit: 100);
echo "Link undangan: " . $invite['link'] . "\n";

// 7. Wajib persetujuan admin untuk join
$client->toggleJoinRequest($groupId, true);

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
| `editChatAbout($channel, $about)` | Ubah deskripsi | Channel/supergroup saja |
| `exportInviteLink($peer, ...)` | Generate link undangan | Semua |
| `setDefaultPermissions($peer, $flags)` | Default permission anggota | Basic group & supergroup |
| `toggleSlowMode($channel, $seconds)` | Slow mode | Supergroup |
| `toggleSignatures($channel, $enabled)` | Tanda tangan admin | Channel broadcast |
| `toggleJoinToSend($channel, $enabled)` | Wajib join sebelum kirim | Supergroup |
| `toggleJoinRequest($channel, $enabled)` | Persetujuan admin untuk join | Channel/supergroup |

**Manajemen Anggota**

| Method | Kegunaan | Berlaku untuk basic group? |
|--------|----------|---------------------------|
| `addChatUser($chatId, $user, $fwdLimit)` | Tambah anggota | ✅ Khusus basic group |
| `inviteToChannel($channel, $users)` | Undang anggota | ❌ Channel/supergroup saja |
| `kickUser($peer, $user)` | Keluarkan user (bisa join kembali) | ✅ |
| `banUser($peer, $user, $untilDate)` | Keluarkan user permanen (basic group = sama dengan kick) | ✅ |
| `unbanUser($peer, $user)` | Hapus ban | ❌ Throw di basic group |
| `restrictUser($peer, $user, $flags)` | Batasi hak user parsial (mute, dll) | ❌ Throw di basic group |
| `promoteAdmin($peer, $user, $rights, $rank)` | Jadikan admin | ✅ (rank & custom rights diabaikan) |
| `demoteAdmin($peer, $user)` | Cabut admin | ✅ |
| `pinMessage($peer, $msgId)` | Pin pesan | ✅ |
| `unpinMessage($peer, $msgId)` | Unpin pesan | ✅ |

**Perbedaan `kickUser` vs `banUser` di basic group:**
- Keduanya menggunakan `messages.deleteChatUser` di balik layar — secara teknis identik
- User yang di-kick/ban bisa ditambahkan kembali dengan `addChatUser()`
- Basic group **tidak memiliki ban list** — tidak ada cara untuk mencegah user bergabung kembali setelah di-kick

> **Lihat juga:**
> - Section 18 — Admin, ban, kick, restrict (detail lengkap)
> - Section 21 — Daftar channel di mana kamu admin
> - Section 22 — Daftar anggota channel/grup

---

### 32.16 Contoh Lengkap: Siklus Hidup Basic Group

```php
use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;
use XnoxsProto\TL\Functions\MessagesEditChatDefaultBannedRightsRequest as Perms;
use XnoxsProto\TL\Types\InputPeer;

$client = new TelegramClient($apiId, $apiHash, new FileSession('sesi.session'));
$client->connect();

// ── 1. BUAT GRUP ───────────────────────────────────────────────────────────
$created = $client->createChat('Tim Proyek Alpha', '@teman1');
$chatId  = $created['chat_id'];    // simpan ID ini!
echo "Grup dibuat: ID=$chatId\n";

// ── 2. BACA INFO AWAL ──────────────────────────────────────────────────────
$info = $client->getFullChat($chatId);
echo "Anggota: {$info['participants_count']}\n";

// ── 3. KIRIM PESAN SAMBUTAN ────────────────────────────────────────────────
$msg = $client->sendMessage($chatId, 'Selamat datang di Tim Proyek Alpha!');
$msgId = $msg['message_id'];

// ── 4. PIN PESAN SAMBUTAN ──────────────────────────────────────────────────
$client->pinMessage($chatId, $msgId, silent: true);

// ── 5. UBAH JUDUL ─────────────────────────────────────────────────────────
$client->editChatTitle($chatId, 'Tim Alpha — Final');

// ── 6. ATUR IZIN DEFAULT (larang ubah info grup) ───────────────────────────
$client->setDefaultPermissions($chatId, Perms::BAN_CHANGE_INFO | Perms::BAN_PIN_MESSAGES);

// ── 7. GENERATE LINK UNDANGAN ─────────────────────────────────────────────
$invite = $client->exportInviteLink($chatId, usageLimit: 10);
echo "Link undangan: {$invite['link']}\n";

// ── 8. PROMOSIKAN ANGGOTA JADI ADMIN ─────────────────────────────────────
$client->promoteAdmin($chatId, '@teman1');

// ── 9. TAMBAH ANGGOTA BARU ────────────────────────────────────────────────
$client->addChatUser($chatId, '@teman2', fwdLimit: 50);

// ── 10. KICK ANGGOTA (keluarkan sementara, bisa tambah ulang) ─────────────
$client->kickUser($chatId, '@teman2');

// ── 11. UNPIN PESAN ──────────────────────────────────────────────────────
$client->unpinMessage($chatId, $msgId);

// ── 12. HAPUS GRUP (permanen, hanya creator) ─────────────────────────────
// Gunakan chat_id langsung dari sesi yang sama
$client->deleteChat($chatId);

// Atau — jika koneksi sudah berbeda dari saat createChat() — pakai InputPeer::chat()
// $client->deleteChat(InputPeer::chat($chatId));

echo "Selesai.\n";
$client->disconnect();
```
