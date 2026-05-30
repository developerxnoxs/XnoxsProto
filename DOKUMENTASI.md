# Dokumentasi XnoxsProto — PHP MTProto Library

> Library PHP untuk berkomunikasi langsung dengan server Telegram menggunakan protokol MTProto Layer 214.
> Terinspirasi dari Telethon (Python), dirancang dengan API yang bersih dan mudah digunakan.

---

## Daftar Isi

1. [Persyaratan & Instalasi](#1-persyaratan--instalasi)
2. [Quick Start](#2-quick-start)
3. [TelegramClient — Konstruktor & Konfigurasi](#3-telegramclient--konstruktor--konfigurasi)
4. [Autentikasi](#4-autentikasi)
5. [Mengirim Pesan Teks](#5-mengirim-pesan-teks)
6. [Mengirim Media](#6-mengirim-media)
7. [Mengambil Riwayat & Dialog](#7-mengambil-riwayat--dialog)
8. [Mengelola Pesan (Edit, Hapus, Pin)](#8-mengelola-pesan-edit-hapus-pin)
9. [Mengunduh Media](#9-mengunduh-media)
10. [Event Handler & Update Listener](#10-event-handler--update-listener)
11. [FullMessage & Tombol Inline](#11-fullmessage--tombol-inline)
12. [Manajemen Grup & Channel](#12-manajemen-grup--channel)
13. [Manajemen Admin](#13-manajemen-admin)
14. [Manajemen Anggota (Ban/Mute/Restrict)](#14-manajemen-anggota-banmuterestrict)
15. [Pencarian Pesan](#15-pencarian-pesan)
16. [Info Lengkap User, Grup, Channel](#16-info-lengkap-user-grup-channel)
17. [Pengaturan Akun (Account Module)](#17-pengaturan-akun-account-module)
18. [Privasi Akun](#18-privasi-akun)
19. [Koneksi & Proxy SOCKS5](#19-koneksi--proxy-socks5)
20. [Manajemen Sesi](#20-manajemen-sesi)
21. [Konstanta Admin & Ban](#21-konstanta-admin--ban)
22. [Referensi API Lengkap](#22-referensi-api-lengkap)

---

## 1. Persyaratan & Instalasi

### Prasyarat

- PHP **8.2** atau lebih baru
- Ekstensi wajib: `ext-gmp`, `ext-openssl`, `ext-mbstring`, `ext-json`
- `ext-curl` disarankan (tidak wajib)

Periksa ekstensi yang aktif:

```bash
php -m | grep -E 'gmp|openssl|mbstring|json|curl'
```

### Mendapatkan API ID & Hash

1. Buka [my.telegram.org/apps](https://my.telegram.org/apps)
2. Login dengan nomor Telegram Anda
3. Buat aplikasi baru
4. Catat **App api_id** dan **App api_hash**

---

## 2. Quick Start

### Login Akun Pengguna (Interaktif via STDIN)

```php
<?php
require_once __DIR__ . '/src/autoload.php'; // sesuaikan path autoloader

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(12345, 'api_hash_anda', 'nama_sesi');
$client->start('+62812XXXXXXXX');

$me = $client->getMe();
echo "Login sebagai: {$me['first_name']} (ID: {$me['id']})\n";
```

### Login Bot

```php
$client = new TelegramClient(12345, 'api_hash_anda', 'bot_sesi');
$client->start(botToken: '123456789:AABBcc...');
```

### Kirim Pesan Pertama

```php
$client->sendMessage('@username', 'Halo dari XnoxsProto!');
$client->sendMessage(123456789, 'Kirim via ID numerik');
$client->sendMessage('+62812XXXXXXXX', 'Kirim via nomor HP');
```

---

## 3. TelegramClient — Konstruktor & Konfigurasi

### Konstruktor

```php
new TelegramClient(int $apiId, string $apiHash, string|AbstractSession|null $session = null)
```

| Parameter  | Tipe                              | Keterangan                                                            |
|------------|-----------------------------------|-----------------------------------------------------------------------|
| `$apiId`   | `int`                             | API ID dari my.telegram.org/apps                                      |
| `$apiHash` | `string`                          | API Hash dari my.telegram.org/apps                                    |
| `$session` | `string\|AbstractSession\|null`   | Nama file sesi, objek AbstractSession kustom, atau null               |

**Tipe sesi:**

| Nilai `$session`     | Perilaku                                                        |
|----------------------|-----------------------------------------------------------------|
| `'nama_sesi'`        | FileSession otomatis → disimpan ke `nama_sesi.session`          |
| `AbstractSession`    | Objek sesi kustom (FileSession, MemorySession, dll.)            |
| `null`               | MemorySession — sesi hilang saat script selesai                 |

```php
// String → FileSession otomatis
$client = new TelegramClient(12345, 'hash', 'my_account');
// Disimpan ke: my_account.session

// MemorySession (tidak tersimpan)
$client = new TelegramClient(12345, 'hash', null);

// FileSession eksplisit
use XnoxsProto\Sessions\FileSession;
$client = new TelegramClient(12345, 'hash', new FileSession('sessions/my.session'));
```

### Metode Konfigurasi Koneksi

```php
$client->isConnected();        // bool — status koneksi saat ini
$client->connect();            // void — connect manual (biasanya otomatis via start())
$client->disconnect();         // void — putuskan koneksi
$client->syncUpdateState();    // void — sync state update agar server push notif baru
$client->getLayer();           // int  — layer MTProto aktif (214)
```

---

## 4. Autentikasi

### `start()` — Login Otomatis (Direkomendasikan)

```php
$client->start(
    string    $phone            = '',
    ?callable $codeCallback     = null,
    ?callable $passwordCallback = null,
    string    $botToken         = ''
): void
```

`start()` menangani seluruh alur login secara otomatis:

1. Jika sesi sudah ada & valid → langsung sync update state
2. Jika `$botToken` tidak kosong → login sebagai bot
3. Jika `$phone` → kirim OTP, minta kode, tangani 2FA otomatis

**Login akun pengguna (interaktif STDIN):**

```php
$client->start('+62812XXXXXXXX');
// Otomatis prompt kode OTP di STDIN
// Otomatis prompt password 2FA jika akun dilindungi 2FA
```

**Login dengan callback kustom (non-interaktif):**

```php
$client->start(
    phone:            '+62812XXXXXXXX',
    codeCallback:     fn() => file_get_contents('/tmp/otp_code.txt'),
    passwordCallback: fn() => 'password_2fa_saya'
);
```

**Login bot:**

```php
$client->start(botToken: '123456789:AABBcc...');
```

### `getMe()` — Info Akun Saat Ini

```php
$me = $client->getMe();
// Mengembalikan array:
// [
//   'id'         => int,
//   'first_name' => string,
//   'last_name'  => string|null,
//   'username'   => string|null,
//   'phone'      => string|null,
//   'bot'        => bool,
//   'premium'    => bool,
// ]
echo "Halo, {$me['first_name']}!\n";
```

### Auth Module Manual

Tersedia via `$client->getAuth()` untuk skenario kontrol penuh:

```php
$auth = $client->getAuth();

// Kirim kode OTP
$sentCode = $auth->sendCode('+62812...');
// Returns: ['phone_code_hash' => string, ...]

// Sign in dengan kode
$auth->signIn('+62812...', $sentCode['phone_code_hash'], '12345');

// Verifikasi password 2FA
$auth->checkPassword('password_2fa_saya');

// Login bot
$auth->loginAsBot('BOT_TOKEN');

// Cek status login
$auth->isAuthorized(); // bool

// Logout
$auth->logOut(); // bool
```

---

## 5. Mengirim Pesan Teks

### `sendMessage()`

```php
$client->sendMessage(
    string|int|InputPeer $peer,
    string               $text,
    ?int                 $replyTo = null
): array
```

**Format peer yang didukung:**

| Format                | Contoh                      |
|-----------------------|-----------------------------|
| Username              | `'@durov'`                  |
| Nomor HP              | `'+62812XXXXXXXX'`          |
| ID numerik            | `123456789`                 |
| InputPeer             | `$client->resolvePeer(...)` |

```php
$client->sendMessage('@durov', 'Halo Pavel!');
$client->sendMessage('+62812XXXXXXXX', 'Halo!');
$client->sendMessage(123456789, 'Halo via ID!');
$client->sendMessage('@grupkita', 'Pesan ke grup');

// Balas pesan tertentu
$client->sendMessage('@user', 'Ini balasan kamu', replyTo: 999);
```

**Nilai kembali:**

```php
[
    'sent'       => true,
    'message_id' => int,
    'date'       => int,    // Unix timestamp
    'peer_type'  => string, // 'user'|'chat'|'channel'
    'peer_id'    => int,
]
```

---

## 6. Mengirim Media

### `sendFile()` — Auto-detect Tipe Media

```php
$client->sendFile(
    string|int|InputPeer $peer,
    string               $filePath,
    string               $caption       = '',
    bool                 $forceDocument = false,
    ?int                 $replyTo       = null,
    ?callable            $onProgress    = null
): array
```

Deteksi tipe otomatis dari ekstensi file:

| Ekstensi                   | Dikirim sebagai     |
|----------------------------|---------------------|
| `.jpg`, `.png`, `.webp`    | Foto (inline)       |
| `.mp4`, `.mov`, `.avi`, `.mkv` | Video (player)  |
| `.mp3`, `.ogg`, `.flac`, `.wav` | Audio (player) |
| `.gif`, dan lainnya        | Dokumen             |

```php
$client->sendFile('@user', 'foto.jpg', 'Lihat ini!');
$client->sendFile('@user', 'video.mp4', 'Video keren');

// Paksa sebagai dokumen meski ekstensi gambar
$client->sendFile('@user', 'foto.png', '', forceDocument: true);

// Dengan progress callback: fn(int $part, int $total, int $pct)
$client->sendFile('@user', 'besar.zip', 'Upload...', onProgress: function($part, $total, $pct) {
    echo "Upload: {$pct}%\n";
});
```

### `sendPhoto()` — Kirim Foto

```php
$client->sendPhoto(
    string|int|InputPeer $peer,
    string               $filePath,
    string               $caption    = '',
    ?int                 $replyTo    = null,
    ?callable            $onProgress = null
): array
```

```php
$client->sendPhoto('@user', 'gambar.jpg', 'Caption foto');
```

### `sendVideo()` — Kirim Video

```php
$client->sendVideo(
    string|int|InputPeer $peer,
    string               $filePath,
    string               $caption    = '',
    float                $duration   = 0.0,  // detik (0 = auto via ffprobe)
    int                  $width      = 0,    // 0 = auto
    int                  $height     = 0,    // 0 = auto
    ?int                 $replyTo    = null,
    ?callable            $onProgress = null
): array
```

```php
$client->sendVideo('@user', 'video.mp4', 'Video keren',
    duration: 30.5, width: 1280, height: 720
);
```

### `sendAudio()` — Kirim Audio

```php
$client->sendAudio(
    string|int|InputPeer $peer,
    string               $filePath,
    string               $caption    = '',
    int                  $duration   = 0,   // detik (0 = auto)
    string               $title      = '',  // judul lagu
    string               $performer  = '',  // nama artis
    ?int                 $replyTo    = null,
    ?callable            $onProgress = null
): array
```

```php
$client->sendAudio('@user', 'lagu.mp3', '',
    title: 'Judul Lagu', performer: 'Nama Artis'
);
```

### `sendDocument()` — Kirim Dokumen

```php
$client->sendDocument(
    string|int|InputPeer $peer,
    string               $filePath,
    string               $caption    = '',
    string               $filename   = '',  // nama file di chat (default: nama asli)
    ?int                 $replyTo    = null,
    ?callable            $onProgress = null
): array
```

```php
$client->sendDocument('@user', 'report.pdf', 'Laporan bulanan', 'laporan_juni.pdf');
```

### `sendVoice()` — Kirim Pesan Suara

```php
$client->sendVoice(
    string|int|InputPeer $peer,
    string               $filePath,
    int                  $duration   = 0,
    ?int                 $replyTo    = null,
    ?callable            $onProgress = null
): array
```

```php
$client->sendVoice('@user', 'voice.ogg', duration: 15);
```

### `sendPoll()` — Kirim Polling

```php
$client->sendPoll(
    string|int|InputPeer $peer,
    string               $question,
    array                $answers,
    bool                 $isQuiz         = false,  // mode kuis (satu jawaban benar)
    int                  $correctIndex   = 0,
    string               $solution       = '',     // penjelasan jawaban kuis
    bool                 $multipleChoice = false,  // pilih lebih dari satu
    bool                 $publicVoters   = false,  // tampilkan siapa memilih
    int                  $closePeriod    = 0,      // auto-close setelah N detik
    ?int                 $replyTo        = null
): array
```

```php
// Poll biasa
$client->sendPoll('@group', 'Mana yang terbaik?', ['PHP', 'Python', 'Go']);

// Quiz mode
$client->sendPoll('@group',
    question:      'Ibu kota Indonesia?',
    answers:       ['Surabaya', 'Jakarta', 'Bandung'],
    isQuiz:        true,
    correctIndex:  1,
    solution:      'Jakarta adalah ibu kota Indonesia sejak 1945.'
);

// Pilihan ganda, voter publik, auto-close 5 menit
$client->sendPoll('@group',
    question:       'Pilih teknologi favorit:',
    answers:        ['PHP', 'Python', 'Node.js'],
    multipleChoice: true,
    publicVoters:   true,
    closePeriod:    300
);
```

### `forwardMessages()` — Teruskan Pesan

```php
$client->forwardMessages(
    string|int|InputPeer $to,
    array                $ids,
    string|int|InputPeer $from,
    bool                 $dropAuthor = false  // sembunyikan pengirim asli
): array
```

```php
// Teruskan pesan 101 dan 102 dari @source ke @target
$client->forwardMessages('@target', [101, 102], '@source');

// Sembunyikan pengirim asli
$client->forwardMessages('@target', [101], '@source', dropAuthor: true);
// Returns: ['forwarded' => true, 'ids' => [101, 102]]
```

---

## 7. Mengambil Riwayat & Dialog

### `getDialogs()` — Daftar Dialog

```php
$client->getDialogs(int $limit = 100, bool $allPages = false): array
```

```php
$dialogs = $client->getDialogs(50);
foreach ($dialogs as $d) {
    echo "{$d['type']} — {$d['title']}\n";
}

// Ambil semua dialog (auto-paginate)
$semua = $client->getDialogs(allPages: true);
```

**Struktur tiap elemen:**

```php
[
    'type'         => 'user'|'chat'|'channel',
    'id'           => int,
    'access_hash'  => int,
    'title'        => string,         // nama/judul dialog
    'username'     => string|null,
    'unread_count' => int,
    'top_message'  => int,            // ID pesan terakhir
]
```

### `getHistory()` — Riwayat Pesan

```php
$client->getHistory(
    string|int|InputPeer $peer,
    int                  $limit     = 20,
    int                  $offsetId  = 0,
    int                  $addOffset = 0,
    int                  $maxId     = 0,
    int                  $minId     = 0
): array
```

```php
$messages = $client->getHistory('@user', 50);
foreach ($messages as $msg) {
    $waktu = date('H:i:s', $msg['date']);
    echo "[{$waktu}] [{$msg['id']}] {$msg['text']}\n";
}
```

**Struktur tiap pesan:**

```php
[
    'id'           => int,
    'date'         => int,          // Unix timestamp
    'text'         => string,
    'out'          => bool,         // true jika dikirim oleh kita
    'peer_type'    => 'user'|'chat'|'channel',
    'peer_id'      => int,
    'from_user_id' => int|null,
    'reply_markup' => array|null,   // inline keyboard (lihat §11)
    'media'        => array|null,   // info media jika ada
]
```

### `getContacts()` — Daftar Kontak

```php
$contacts = $client->getContacts();
foreach ($contacts as $c) {
    echo "{$c['first_name']} {$c['last_name']} — @{$c['username']}\n";
}
```

---

## 8. Mengelola Pesan (Edit, Hapus, Pin)

### `editMessage()` — Edit Teks Pesan

```php
$client->editMessage(string|int|InputPeer $peer, int $msgId, string $text): array
```

```php
$client->editMessage('@user', 999, 'Teks yang sudah diedit');
// Returns: ['edited' => true, 'message_id' => 999]
```

### `deleteMessages()` — Hapus Pesan

```php
$client->deleteMessages(
    array                       $ids,
    string|int|InputPeer|null   $peer   = null,
    bool                        $revoke = true   // true = hapus untuk semua pihak
): array
```

```php
// DM / grup biasa — hapus untuk semua pihak
$client->deleteMessages([101, 102, 103]);

// Channel / supergroup — wajib sertakan peer
$client->deleteMessages([101, 102], '@mychannel');

// Returns: ['deleted' => true, 'ids' => [...]]
```

### `pinMessage()` — Pin Pesan

```php
$client->pinMessage(
    string|int|InputPeer $peer,
    int                  $msgId,
    bool                 $silent = false  // true = pin tanpa notifikasi
): array
```

```php
$client->pinMessage('@group', 500);              // pin + notifikasi
$client->pinMessage('@group', 500, silent: true); // pin tanpa notifikasi
// Returns: ['pinned' => true, 'message_id' => 500]
```

### `unpinMessage()` — Unpin Pesan

```php
$client->unpinMessage(string|int|InputPeer $peer, int $msgId): array
```

```php
$client->unpinMessage('@group', 500);
// Returns: ['unpinned' => true, 'message_id' => 500]
```

### `startBot()` — Mulai Bot dengan Parameter

```php
$client->startBot(string|int $bot, string|int|InputPeer $peer, string $startParam = ''): array
```

```php
$client->startBot('@namabot', 'me', 'referral_123');
```

---

## 9. Mengunduh Media

### `downloadMedia()` — Download dari Pesan

```php
$client->downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
```

Mendukung: foto, video, audio, voice, GIF, dokumen, stiker.
DC migration dan file_reference refresh ditangani otomatis.

```php
$messages = $client->getHistory('@channel', 10);
foreach ($messages as $msg) {
    if ($msg['media']) {
        $path = $client->downloadMedia($msg, 'downloads/file');
        echo "Disimpan ke: {$path}\n";
    }
}

// Dengan progress callback: fn(int $received, int $total, int $pct)
$client->downloadMedia($msg, 'video.mp4', function(int $received, int $total, int $pct) {
    echo "Download: {$pct}%\n";
});
```

### `downloadPhoto()` — Download Foto by ID

```php
$client->downloadPhoto(
    int       $photoId,
    int       $accessHash,
    string    $fileRef,
    string    $savePath,
    ?callable $onProgress = null,
    string    $thumbSize  = 'y',   // 'y'=terbesar, 'x', 'm', 's'
    ?int      $dcId       = null   // null = auto dari session
): string
```

### `downloadDocument()` — Download Dokumen by ID

```php
$client->downloadDocument(
    int       $docId,
    int       $accessHash,
    string    $fileRef,
    string    $savePath,
    ?callable $onProgress = null,
    ?int      $dcId       = null,
    int       $totalSize  = 0      // untuk progress %
): string
```

---

## 10. Event Handler & Update Listener

### `on()` — Handler Pesan Baru

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client->on(new NewMessage(), function(NewMessageEvent $event) {
    echo "Pesan: {$event->rawText}\n";
});
```

**Filter NewMessage:**

```php
// Semua pesan (masuk maupun keluar)
$client->on(new NewMessage(), $handler);

// Hanya pesan masuk (dari orang lain)
$client->on(new NewMessage(incoming: true), $handler);

// Hanya pesan keluar (dikirim oleh kita)
$client->on(new NewMessage(outgoing: true), $handler);

// Hanya dari user/peer tertentu
$client->on(new NewMessage(fromUsers: ['@user1', 123456789]), $handler);

// Kombinasi filter
$client->on(new NewMessage(incoming: true, fromUsers: ['@botname']), $handler);
```

### `onUpdate()` — Handler Update Mentah

```php
use XnoxsProto\Events\RawUpdateEvent;

$client->onUpdate(function(RawUpdateEvent $event) {
    switch ($event->type) {
        case 'new_message':
            $msg = $event->message; // FullMessage
            echo "Pesan baru: {$msg->text}\n";
            break;

        case 'edit_message':
            $msg = $event->message;
            echo "Pesan diedit: {$msg->text}\n";
            break;

        case 'delete_messages':
            $ids = $event->messageIds; // int[]
            echo "Dihapus: " . implode(', ', $ids) . "\n";
            break;

        case 'read_history':
            // $event->peerId, $event->maxId, $event->direction ('in'|'out')
            echo "Dibaca sampai ID: {$event->maxId}\n";
            break;

        case 'user_status':
            // $event->userId (int), $event->online (bool), $event->wasOnline (int)
            $status = $event->online ? 'online' : 'offline';
            echo "User {$event->userId} sekarang {$status}\n";
            break;

        case 'pinned_messages':
            // $event->messageIds (int[]), $event->peerId, $event->pinned (bool)
            echo "Pin: " . implode(', ', $event->messageIds) . "\n";
            break;
    }
});
```

### `runUntilDisconnected()` — Event Loop Utama

```php
$client->runUntilDisconnected();
// Blokir sampai koneksi terputus atau $client->disconnect() dipanggil
```

### `pollOnce()` — Poll Satu Kali

```php
$gotUpdate = $client->pollOnce(timeoutSeconds: 1); // bool
```

### `removeHandlers()` — Hapus Semua Handler

```php
$client->removeHandlers();
```

### Properti `NewMessageEvent`

| Properti               | Tipe         | Keterangan                                       |
|------------------------|--------------|--------------------------------------------------|
| `$event->rawText`      | `string`     | Teks pesan mentah                                |
| `$event->message`      | `FullMessage`| Objek pesan lengkap (mendukung `click()`)        |
| `$event->isIncoming`   | `bool`       | `true` jika pesan dari orang lain                |
| `$event->isOutgoing`   | `bool`       | `true` jika pesan dikirim oleh kita              |
| `$event->users`        | `User[]`     | Map user yang hadir dalam update ini             |
| `$event->chats`        | `Chat[]`     | Map chat yang hadir dalam update ini             |
| `$event->originalUpdate` | `array`    | Raw update array                                 |

**Metode `NewMessageEvent`:**

```php
$event->getSender(); // ?User — objek pengirim pesan
$event->getChat();   // ?Chat — objek chat (jika pesan dari grup/channel)
```

### Tipe Update pada `RawUpdateEvent`

| `$event->type`        | Data yang tersedia                                                        |
|-----------------------|---------------------------------------------------------------------------|
| `'new_message'`       | `message` (FullMessage), `users` (array), `chats` (array)                |
| `'edit_message'`      | `message` (FullMessage)                                                   |
| `'delete_messages'`   | `messageIds` (int[]), `channelId` (int\|null)                            |
| `'read_history'`      | `peerId`, `maxId` (int), `direction` (`'in'`\|`'out'`)                   |
| `'pinned_messages'`   | `messageIds` (int[]), `peerId`, `pinned` (bool)                          |
| `'user_status'`       | `userId` (int), `online` (bool), `wasOnline` (int)                       |

---

## 11. FullMessage & Tombol Inline

### Properti `FullMessage`

| Properti              | Tipe          | Keterangan                                        |
|-----------------------|---------------|---------------------------------------------------|
| `$msg->id`            | `int`         | ID pesan                                          |
| `$msg->date`          | `int`         | Unix timestamp                                    |
| `$msg->text`          | `string`      | Isi teks pesan                                    |
| `$msg->out`           | `bool`        | `true` jika pesan dikirim oleh kita               |
| `$msg->type`          | `string`      | `'message'`, `'service'`, atau `'empty'`          |
| `$msg->peerType`      | `string`      | `'user'`, `'chat'`, atau `'channel'`              |
| `$msg->peerId`        | `int`         | ID peer                                           |
| `$msg->fromUserId`    | `int\|null`   | ID user pengirim (jika tersedia)                  |
| `$msg->fromChatId`    | `int\|null`   | ID chat asal (jika diteruskan dari grup)          |
| `$msg->fromChannelId` | `int\|null`   | ID channel asal (jika diteruskan dari channel)    |
| `$msg->replyMarkup`   | `array\|null` | Inline keyboard (struktur lihat di bawah)         |
| `$msg->media`         | `array\|null` | Info media jika ada                               |

### Struktur `replyMarkup`

```php
$markup = $msg->replyMarkup;
// [
//   'rows' => [
//     // Baris 0 — array of tombol
//     [
//       ['type' => 'callback', 'text' => 'Tombol A', 'data' => 'callback_data'],
//       ['type' => 'url',      'text' => 'Buka Link', 'url'  => 'https://...'],
//     ],
//     // Baris 1
//     [
//       ['type' => 'game', 'text' => 'Main Game', 'data' => null],
//     ],
//   ]
// ]
```

### `click()` — Klik Tombol Inline

```php
$msg->click(int|string $row = 0, int $col = 0): ?array
```

Hanya bisa dipanggil dari dalam event handler (membutuhkan client ter-attach).

**Mode berdasarkan posisi (integer):**

```php
$msg->click(0, 0); // Baris pertama, kolom pertama
$msg->click(1, 2); // Baris kedua, kolom ketiga
```

**Mode berdasarkan teks label (string):**

```php
// Exact match (case-sensitive, prioritas pertama)
$msg->click('📖 Bantuan');

// Jika exact match gagal → fallback partial match (case-insensitive)
$msg->click('bantuan'); // cocok dengan '📖 Bantuan'
$msg->click('Bant');    // partial match
```

Urutan pencarian:
1. **Exact match** — teks harus identik persis
2. **Partial match** — `mb_strpos` case-insensitive
3. Jika tidak ditemukan → throw `RuntimeException`

Jika ditemukan lebih dari satu cocok parsial, tombol pertama yang ditemukan diklik.

**Contoh dalam event handler:**

```php
$client->on(new NewMessage(incoming: true), function(NewMessageEvent $event) use ($client) {
    $msg = $event->message;

    if ($msg->replyMarkup) {
        // Klik berdasarkan posisi
        $result = $msg->click(0, 0);

        // Atau klik berdasarkan label teks
        $result = $msg->click('✅ Konfirmasi');

        // Klik partial match
        $result = $msg->click('Lanjut');
    }
});
```

### `getButtonText()` / `getButtonUrl()` — Baca Tombol Tanpa Klik

```php
$text = $msg->getButtonText(int $row = 0, int $col = 0): ?string
$url  = $msg->getButtonUrl(int $row = 0, int $col = 0):  ?string
```

```php
$label = $msg->getButtonText(0, 0); // 'Tombol A'
$link  = $msg->getButtonUrl(0, 1);  // 'https://...' atau null
```

---

## 12. Manajemen Grup & Channel

### `joinChannel()` — Bergabung

```php
$client->joinChannel(string $peer): array
```

Mendukung berbagai format:

```php
$client->joinChannel('@namagroup');
$client->joinChannel('https://t.me/namagroup');
$client->joinChannel('https://t.me/joinchat/HASH'); // link invite
$client->joinChannel('https://t.me/+HASH');          // link invite format baru
// Returns: ['joined' => true, 'peer' => ..., 'via' => ...]
```

### `leaveChannel()` — Keluar

```php
$client->leaveChannel('@namagroup');
// Returns: ['left' => true, 'peer' => '@namagroup']
```

### `inviteToChannel()` — Undang User

```php
$client->inviteToChannel(
    string|int|InputPeer                        $channel,
    string|int|InputPeer|array<string|int|InputPeer> $users
): array
```

```php
// Satu user
$client->inviteToChannel('@supergroup', '@user');

// Beberapa user sekaligus
$client->inviteToChannel('@supergroup', ['@user1', '@user2', 123456789]);
// Returns: ['invited' => true, 'channel_id' => int, 'user_ids' => [...]]
```

### `createChat()` — Buat Grup Biasa (Basic Group)

```php
$client->createChat(string $title, string|int|InputPeer|array $users): array
```

```php
$result = $client->createChat('Nama Grup Baru', ['@user1', '@user2']);
// Returns: ['created' => true, 'title' => '...', 'user_ids' => [...], 'chat_id' => int]
```

### `createChannel()` — Buat Channel atau Supergroup

```php
$client->createChannel(
    string $title,
    string $about     = '',
    bool   $megagroup = false, // false = broadcast channel, true = supergroup
    bool   $forum     = false  // true = aktifkan mode topik/forum (khusus megagroup)
): array
```

```php
// Broadcast channel
$client->createChannel('Nama Channel', 'Deskripsi channel');

// Supergroup
$client->createChannel('Nama Supergroup', '', megagroup: true);

// Forum (supergroup dengan topik)
$client->createChannel('Forum Diskusi', 'Tempat diskusi', megagroup: true, forum: true);
// Returns: ['created' => true, 'title' => '...', 'channel_id' => int, 'access_hash' => int]
```

### `deleteChat()` — Hapus Grup/Channel (Hanya Owner)

```php
$client->deleteChat(string|int|InputPeer $peer): array
// Auto-detect tipe: basic group → messages.deleteChat
//                   channel/supergroup → channels.deleteChannel
// Returns: ['deleted' => true, 'peer_id' => int]
```

### `migrateChat()` — Upgrade Grup ke Supergroup

```php
$result = $client->migrateChat(int $chatId): array
// Setelah migrate, chat_id lama tidak bisa dipakai lagi.
// Returns: ['migrated' => true, 'old_chat_id' => int]
```

### `editChatTitle()` — Ubah Judul

```php
$client->editChatTitle(string|int|InputPeer $peer, string $title): array
// Auto-detect tipe: basic group atau channel/supergroup
// Returns: ['updated' => true, 'peer_id' => int, 'title' => '...']
```

### `editChatAbout()` — Ubah Deskripsi Channel/Supergroup

```php
$client->editChatAbout(string|int|InputPeer $channel, string $about): array
// Tidak berlaku untuk basic group (akan throw RuntimeException)
// Returns: ['updated' => true, 'peer_id' => int, 'about' => '...']
```

### `addChatUser()` — Tambah User ke Basic Group

```php
$client->addChatUser(
    int                  $chatId,
    string|int|InputPeer $user,
    int                  $fwdLimit = 100  // berapa pesan lama yang bisa dilihat user baru (0-100)
): array
// Untuk supergroup/channel gunakan inviteToChannel()
// Returns: ['added' => true, 'chat_id' => int, 'user_id' => int]
```

### `toggleSlowMode()` — Slow Mode Supergroup

```php
$client->toggleSlowMode(string|int|InputPeer $channel, int $seconds): array
// Nilai $seconds yang didukung: 0 (off), 10, 30, 60, 300, 900, 3600

$client->toggleSlowMode('@supergroup', 30);  // 30 detik antar pesan
$client->toggleSlowMode('@supergroup', 0);   // matikan slow mode
```

### `exportInviteLink()` — Buat Link Undangan

```php
$client->exportInviteLink(
    string|int|InputPeer $peer,
    bool                 $revokePermanent = false, // revoke link lama & buat baru
    bool                 $requestNeeded   = false, // join perlu persetujuan admin
    ?int                 $expireDate      = null,  // Unix timestamp kadaluarsa
    ?int                 $usageLimit      = null,  // batas pemakaian link
    string               $title           = ''     // nama/label link
): array
```

```php
// Link biasa
$result = $client->exportInviteLink('@group');
echo $result['link']; // https://t.me/+...

// Link dengan persetujuan admin
$client->exportInviteLink('@group', requestNeeded: true);

// Link dengan batas waktu dan pemakaian
$client->exportInviteLink('@group',
    expireDate:  time() + 86400,
    usageLimit:  100,
    title:       'Link Event Juni'
);

// Revoke link lama dan buat baru
$client->exportInviteLink('@group', revokePermanent: true);
```

**Nilai kembali:**

```php
[
    'link'           => string|null,  // URL link undangan
    'revoked'        => bool,
    'expire_date'    => int|null,
    'usage_limit'    => int|null,
    'request_needed' => bool,
    'title'          => string,
    'peer_id'        => int,
]
```

### `setDefaultPermissions()` — Hak Default Anggota

```php
$client->setDefaultPermissions(string|int|InputPeer $peer, int $bannedRights): array
// Flag di-set = DILARANG, flag tidak di-set = DIIZINKAN
// Gunakan konstanta TelegramClient::BAN_*

// Larang stiker dan GIF untuk semua anggota
$client->setDefaultPermissions('@group',
    TelegramClient::BAN_SEND_STICKERS | TelegramClient::BAN_SEND_GIFS
);

// Izinkan semua (hapus semua restriksi default)
$client->setDefaultPermissions('@group', 0);
```

### `toggleSignatures()` — Tanda Tangan Admin (Channel Broadcast)

```php
$client->toggleSignatures(string|int|InputPeer $channel, bool $enabled): array
// Saat aktif, nama admin tampil di bawah pesan yang diposting
// Hanya untuk broadcast channel (bukan supergroup)

$client->toggleSignatures('@channel', true);  // aktifkan
$client->toggleSignatures('@channel', false); // nonaktifkan
```

### `toggleJoinToSend()` — Wajib Join untuk Mengirim

```php
$client->toggleJoinToSend(string|int|InputPeer $channel, bool $enabled): array
// User harus join dulu sebelum bisa kirim pesan di supergroup

$client->toggleJoinToSend('@supergroup', true);
```

### `toggleJoinRequest()` — Persetujuan Admin untuk Join

```php
$client->toggleJoinRequest(string|int|InputPeer $channel, bool $enabled): array
// Saat aktif, request join harus di-approve admin

$client->toggleJoinRequest('@supergroup', true);  // wajib persetujuan
$client->toggleJoinRequest('@supergroup', false); // langsung join
```

---

## 13. Manajemen Admin

### `promoteAdmin()` — Jadikan Admin

```php
$client->promoteAdmin(
    string|int|InputPeer $channel,
    string|int|InputPeer $user,
    int                  $rights = 0,  // 0 = gunakan hak default
    string               $rank   = ''  // custom title (opsional)
): array
```

Jika `$rights = 0`, digunakan set hak default:
`CHANGE_INFO | DELETE_MESSAGES | BAN_USERS | INVITE_USERS | PIN_MESSAGES | MANAGE_CALL | OTHER`

> **Penting:** `ADMIN_OTHER` harus selalu disertakan agar status admin aktif di supergroup/channel.

```php
// Admin dengan hak default
$client->promoteAdmin('@channel', '@user');

// Admin dengan hak kustom + custom title
$client->promoteAdmin(
    channel: '@channel',
    user:    '@user',
    rights:  TelegramClient::ADMIN_CHANGE_INFO
           | TelegramClient::ADMIN_DELETE_MESSAGES
           | TelegramClient::ADMIN_BAN_USERS
           | TelegramClient::ADMIN_INVITE_USERS
           | TelegramClient::ADMIN_PIN_MESSAGES
           | TelegramClient::ADMIN_OTHER,   // WAJIB agar admin aktif
    rank:    'Moderator'
);
// Returns: ['promoted' => true, 'user_id' => int, 'rights' => int, 'rank' => '...']
```

**Basic group:** menggunakan `messages.editChatAdmin` — `$rank` dan custom rights diabaikan.

### `demoteAdmin()` — Cabut Status Admin

```php
$client->demoteAdmin(string|int|InputPeer $channel, string|int|InputPeer $user): array
// Returns: ['demoted' => true, 'user_id' => int]
```

### Konstanta `ADMIN_*`

| Konstanta                           | Nilai    | Keterangan                                   |
|-------------------------------------|----------|----------------------------------------------|
| `TelegramClient::ADMIN_CHANGE_INFO`     | `0x001`  | Ubah nama, foto, deskripsi                   |
| `TelegramClient::ADMIN_POST_MESSAGES`   | `0x002`  | Kirim pesan di channel broadcast             |
| `TelegramClient::ADMIN_EDIT_MESSAGES`   | `0x004`  | Edit pesan yang sudah dikirim (channel)      |
| `TelegramClient::ADMIN_DELETE_MESSAGES` | `0x008`  | Hapus pesan anggota lain                     |
| `TelegramClient::ADMIN_BAN_USERS`       | `0x010`  | Ban / restrict anggota                       |
| `TelegramClient::ADMIN_INVITE_USERS`    | `0x020`  | Undang anggota baru                          |
| `TelegramClient::ADMIN_PIN_MESSAGES`    | `0x080`  | Pin pesan                                    |
| `TelegramClient::ADMIN_ADD_ADMINS`      | `0x200`  | Jadikan anggota lain sebagai admin           |
| `TelegramClient::ADMIN_ANONYMOUS`       | `0x400`  | Posting anonim atas nama grup                |
| `TelegramClient::ADMIN_MANAGE_CALL`     | `0x800`  | Kelola video call / live stream              |
| `TelegramClient::ADMIN_OTHER`           | `0x1000` | **Wajib** agar status admin aktif            |
| `TelegramClient::ADMIN_MANAGE_TOPICS`   | `0x2000` | Kelola topik di forum                        |

---

## 14. Manajemen Anggota (Ban/Mute/Restrict)

### `banUser()` — Ban User

```php
$client->banUser(
    string|int|InputPeer $channel,
    string|int|InputPeer $user,
    int                  $untilDate = 0  // Unix timestamp kapan ban berakhir; 0 = selamanya
): array
```

```php
// Ban permanen
$client->banUser('@supergroup', '@user');

// Ban sementara (1 hari)
$client->banUser('@supergroup', '@user', time() + 86400);
// Returns: ['banned' => true, 'user_id' => int, 'until' => int]
```

> **Basic group:** tidak mendukung ban sementara. User dikeluarkan secara permanen. Gunakan `inviteToChannel()` untuk mengembalikan.

### `unbanUser()` — Hapus Ban

```php
$client->unbanUser(string|int|InputPeer $channel, string|int|InputPeer $user): array
// Hanya untuk supergroup/channel.
// Basic group tidak punya daftar ban — akan throw RuntimeException.
// Returns: ['unbanned' => true, 'user_id' => int]
```

### `kickUser()` — Keluarkan (Bisa Kembali)

```php
$client->kickUser(string|int|InputPeer $channel, string|int|InputPeer $user): array
// Supergroup/channel: ban lalu langsung unban otomatis → user bisa join kembali
// Basic group: hapus langsung → user bisa diundang kembali
// Returns: ['kicked' => true, 'user_id' => int]
```

### `restrictUser()` — Batasi Hak Kustom

```php
$client->restrictUser(
    string|int|InputPeer $channel,
    string|int|InputPeer $user,
    int                  $bannedFlags,   // kombinasi TelegramClient::BAN_*
    int                  $untilDate = 0  // 0 = selamanya
): array
```

```php
// Larang kirim media dan stiker selamanya
$client->restrictUser('@supergroup', '@user',
    TelegramClient::BAN_SEND_MEDIA | TelegramClient::BAN_SEND_STICKERS
);

// Larang kirim pesan selama 1 jam
$client->restrictUser('@supergroup', '@user',
    TelegramClient::BAN_SEND_MESSAGES,
    time() + 3600
);
// Returns: ['restricted' => true, 'user_id' => int, 'flags' => int, 'until' => int]
```

> **Basic group:** tidak mendukung restriksi parsial. Gunakan `kickUser()`.

### `muteUser()` — Bisukan User

```php
$client->muteUser(
    string|int|InputPeer $channel,
    string|int|InputPeer $user,
    int                  $seconds = 0  // 0 = selamanya; 3600 = 1 jam
): array
```

```php
$client->muteUser('@supergroup', '@user');        // mute selamanya
$client->muteUser('@supergroup', '@user', 3600);  // mute 1 jam
// Returns: ['restricted' => true, 'user_id' => int, 'muted_until' => 'selamanya'|string]
```

Shortcut dari: `restrictUser($channel, $user, TelegramClient::BAN_SEND_MESSAGES, $until)`.

### `readOnlyUser()` — User Hanya Bisa Baca

```php
$client->readOnlyUser(
    string|int|InputPeer $channel,
    string|int|InputPeer $user,
    int                  $seconds = 0
): array
```

Melarang: kirim pesan, media, stiker, GIF, game, inline bot, link, polling, foto, video, audio, dokumen.

```php
$client->readOnlyUser('@supergroup', '@user');           // selamanya
$client->readOnlyUser('@supergroup', '@user', 7200);    // 2 jam
// Returns: ['restricted' => true, 'user_id' => int, 'until' => 'selamanya'|string]
```

### Konstanta `BAN_*`

| Konstanta                           | Nilai      | Keterangan                       |
|-------------------------------------|------------|----------------------------------|
| `TelegramClient::BAN_VIEW_MESSAGES`  | `0x000001` | Ban total (tidak bisa lihat)     |
| `TelegramClient::BAN_SEND_MESSAGES`  | `0x000002` | Larang kirim teks (mute)         |
| `TelegramClient::BAN_SEND_MEDIA`     | `0x000004` | Larang kirim semua media         |
| `TelegramClient::BAN_SEND_STICKERS`  | `0x000008` | Larang kirim stiker              |
| `TelegramClient::BAN_SEND_GIFS`      | `0x000010` | Larang kirim GIF                 |
| `TelegramClient::BAN_SEND_GAMES`     | `0x000020` | Larang main game Telegram        |
| `TelegramClient::BAN_SEND_INLINE`    | `0x000040` | Larang pakai inline bot          |
| `TelegramClient::BAN_EMBED_LINKS`    | `0x000080` | Larang kirim link/URL            |
| `TelegramClient::BAN_SEND_POLLS`     | `0x000100` | Larang buat polling              |
| `TelegramClient::BAN_CHANGE_INFO`    | `0x000400` | Larang ubah info grup            |
| `TelegramClient::BAN_INVITE_USERS`   | `0x008000` | Larang undang anggota            |
| `TelegramClient::BAN_PIN_MESSAGES`   | `0x020000` | Larang pin pesan                 |
| `TelegramClient::BAN_SEND_PHOTOS`    | `0x080000` | Larang kirim foto                |
| `TelegramClient::BAN_SEND_VIDEOS`    | `0x100000` | Larang kirim video               |
| `TelegramClient::BAN_SEND_AUDIOS`    | `0x400000` | Larang kirim audio               |
| `TelegramClient::BAN_SEND_DOCS`      | `0x800000` | Larang kirim dokumen/file        |

---

## 15. Pencarian Pesan

### `search()` — Cari di Chat Tertentu

```php
$client->search(
    string|int|InputPeer $peer,
    string               $query,
    int                  $limit    = 20,
    int                  $offsetId = 0,
    int                  $filter   = MessagesSearchRequest::FILTER_EMPTY
): array
```

```php
$results = $client->search('@group', 'kata kunci', limit: 50);
foreach ($results as $msg) {
    echo "[{$msg['date']}] {$msg['text']}\n";
}
```

### `searchGlobal()` — Cari di Semua Chat

```php
$results = $client->searchGlobal(string $query, int $limit = 20): array
```

```php
$results = $client->searchGlobal('XnoxsProto', limit: 30);
```

---

## 16. Info Lengkap User, Grup, Channel

### `getFullUser()` — Info Lengkap User

```php
$client->getFullUser(string|int|InputPeer $user): array
```

```php
$info = $client->getFullUser('@username');
// Returns:
// [
//   'id'                 => int,
//   'first_name'         => string|null,
//   'last_name'          => string|null,
//   'username'           => string|null,
//   'phone'              => string|null,
//   'bot'                => bool,
//   'premium'            => bool,
//   'is_blocked'         => bool,
//   'about'              => string|null,
//   'common_chats_count' => int,
//   'pinned_msg_id'      => int|null,
// ]
```

### `getFullChat()` — Info Lengkap Basic Group

```php
$info = $client->getFullChat(int $chatId): array
// Returns: ['id', 'title', 'about', 'participants_count', 'type']
```

### `getFullChannel()` — Info Lengkap Channel/Supergroup

```php
$info = $client->getFullChannel(string|int|InputPeer $channel): array
// Returns: ['id', 'title', 'about', 'participants_count', 'username', 'type', 'access_hash', ...]
```

### `getAdminChannels()` — Channel yang Dikelola

```php
$channels = $client->getAdminChannels(int $dialogLimit = 200): array
// Mengembalikan channel/supergroup di mana kita adalah admin
```

### `getChannelMembers()` — Daftar Anggota Channel/Supergroup

```php
$client->getChannelMembers(
    string|int|InputPeer $channel,
    string               $filter = 'all',  // 'all'|'admins'|'banned'|'bots'|'recent'
    int                  $limit  = 200,
    int                  $offset = 0
): array
```

```php
$admins  = $client->getChannelMembers('@supergroup', 'admins');
$members = $client->getChannelMembers('@supergroup', 'all', limit: 500);
```

### `getChatMembers()` — Daftar Anggota Basic Group

```php
$members = $client->getChatMembers(int|string|InputPeer $chat): array
```

---

## 17. Pengaturan Akun (Account Module)

Diakses via `$client->getAccount()`.

### `updateProfile()` — Update Profil

```php
$account = $client->getAccount();

// Ubah salah satu atau semua field (null = tidak diubah)
$result = $account->updateProfile(
    firstName: 'Nama Baru',
    lastName:  'Belakang',
    about:     'Bio baru'
);
// Returns: ['id', 'first_name', 'last_name', 'username', 'phone']

// Hanya ubah nama depan
$account->updateProfile(firstName: 'Nama Baru');
```

### `updateUsername()` — Ubah Username

```php
$account->updateUsername('username_baru');
$account->updateUsername(''); // hapus username
```

### `uploadProfilePhoto()` — Upload Foto Profil

```php
$result = $account->uploadProfilePhoto(string $filePath, ?callable $onProgress = null): array
// $filePath: path ke file JPG atau PNG lokal
// Returns: ['photo_id' => int, 'date' => int]

$account->uploadProfilePhoto('foto_profil.jpg');

// Dengan progress
$account->uploadProfilePhoto('foto.jpg', function(int $part, int $total, int $pct) {
    echo "Upload: {$pct}%\n";
});
```

### `getAuthorizations()` — Daftar Sesi Aktif

```php
$sessions = $account->getAuthorizations();
foreach ($sessions as $s) {
    $current = $s['current'] ? ' [SESI INI]' : '';
    echo "{$s['device_model']} — {$s['app_name']} — {$s['ip']} ({$s['country']}){$current}\n";
}
```

**Field tiap sesi:**

| Field              | Tipe   | Keterangan                                      |
|--------------------|--------|-------------------------------------------------|
| `hash`             | `int`  | Hash sesi (untuk terminasi)                     |
| `current`          | `bool` | `true` jika ini sesi aktif sekarang             |
| `official_app`     | `bool` | `true` jika dibuat via aplikasi resmi           |
| `password_pending` | `bool` | `true` jika menunggu verifikasi 2FA             |
| `device_model`     | `string` | Nama perangkat                                |
| `platform`         | `string` | Sistem operasi (Android, iOS, dll.)           |
| `system_version`   | `string` | Versi OS                                      |
| `api_id`           | `int`  | API ID aplikasi yang digunakan                  |
| `app_name`         | `string` | Nama aplikasi                                 |
| `app_version`      | `string` | Versi aplikasi                                |
| `date_created`     | `int`  | Unix timestamp sesi dibuat                      |
| `date_active`      | `int`  | Unix timestamp terakhir aktif                   |
| `ip`               | `string` | Alamat IP                                     |
| `country`          | `string` | Negara                                        |
| `region`           | `string` | Wilayah/kota                                  |

### `resetAuthorization()` — Logout Sesi Tertentu

```php
$sessions = $account->getAuthorizations();
foreach ($sessions as $s) {
    if (!$s['current']) {
        $ok = $account->resetAuthorization($s['hash']); // bool
    }
}
```

### `terminateAllOtherSessions()` — Logout Semua Sesi Lain

```php
$terminated = $account->terminateAllOtherSessions();
echo "Dilogout: {$terminated} sesi lain\n";
```

---

## 18. Privasi Akun

### `getPrivacy()` — Lihat Pengaturan Privasi

```php
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;

$account = $client->getAccount();
$result  = $account->getPrivacy(AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP);
// Returns: ['rules' => ['allow_all'|'allow_contacts'|'disallow_all'|...]]
```

**Konstanta kunci privasi (`KEY_*`):**

| Konstanta                   | Keterangan                        |
|-----------------------------|-----------------------------------|
| `KEY_STATUS_TIMESTAMP`      | Siapa bisa lihat status "Online"  |
| `KEY_CHAT_INVITE`           | Siapa bisa tambahkan ke grup      |
| `KEY_PHONE_CALL`            | Siapa bisa telepon                |
| `KEY_PHONE_P2P`             | Siapa bisa P2P call               |
| `KEY_FORWARDS`              | Siapa bisa forward pesan kita     |
| `KEY_PROFILE_PHOTO`         | Siapa bisa lihat foto profil      |
| `KEY_PHONE_NUMBER`          | Siapa bisa lihat nomor HP         |
| `KEY_ADDED_BY_PHONE`        | Siapa bisa tambah via nomor HP    |

### `setPrivacy()` — Atur Privasi

```php
use XnoxsProto\TL\Functions\AccountSetPrivacyRequest;

$account->setPrivacy(
    AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP,
    [AccountSetPrivacyRequest::RULE_ALLOW_CONTACTS]
);
```

**Konstanta rule (`RULE_*`):**

| Konstanta              | Keterangan                     |
|------------------------|--------------------------------|
| `RULE_ALLOW_ALL`       | Izinkan semua orang            |
| `RULE_ALLOW_CONTACTS`  | Hanya kontak                   |
| `RULE_DISALLOW_ALL`    | Tidak ada yang bisa            |

---

## 19. Koneksi & Proxy SOCKS5

```php
// Set proxy SOCKS5 sebelum connect (sebelum start())
$client->setProxy('127.0.0.1', 1080);
$client->setProxy('proxy.host', 1080, 'username', 'password'); // dengan autentikasi

// Hapus proxy
$client->clearProxy();
```

### Invoke Request Raw (RPC Langsung)

```php
// Kirim request TL langsung ke server Telegram
$response = $client->invoke($request): array
```

### Resolve Peer

```php
$inputPeer = $client->resolvePeer('@username');
$inputPeer = $client->resolvePeer(123456789);
$inputPeer = $client->resolvePeer('+62812XXXXXXXX');
```

---

## 20. Manajemen Sesi

### FileSession

```php
// Otomatis saat parameter session berupa string
$client = new TelegramClient(ID, HASH, 'my_account');
// File disimpan ke: my_account.session

// Eksplisit
use XnoxsProto\Sessions\FileSession;
$client = new TelegramClient(ID, HASH, new FileSession('sessions/custom.session'));
```

### MemorySession

```php
use XnoxsProto\Sessions\MemorySession;

$client = new TelegramClient(ID, HASH, null);
// atau:
$client = new TelegramClient(ID, HASH, new MemorySession());
// Sesi tidak tersimpan setelah script selesai
```

### Transfer Sesi Antar DC

```php
// Export otorisasi ke DC lain (untuk transfer/migrasi)
$exported = $client->exportAuthorization(int $dcId): array
// DC migration saat download file dari DC lain ditangani otomatis oleh FileDownloader
```

---

## 21. Konstanta Admin & Ban

Semua konstanta tersedia langsung di kelas `TelegramClient` — tidak perlu import kelas lain.

```php
// Admin Rights — gunakan di promoteAdmin()
TelegramClient::ADMIN_CHANGE_INFO       // 0x00001
TelegramClient::ADMIN_POST_MESSAGES     // 0x00002  (channel broadcast saja)
TelegramClient::ADMIN_EDIT_MESSAGES     // 0x00004
TelegramClient::ADMIN_DELETE_MESSAGES   // 0x00008
TelegramClient::ADMIN_BAN_USERS         // 0x00010
TelegramClient::ADMIN_INVITE_USERS      // 0x00020
TelegramClient::ADMIN_PIN_MESSAGES      // 0x00080
TelegramClient::ADMIN_ADD_ADMINS        // 0x00200
TelegramClient::ADMIN_ANONYMOUS         // 0x00400
TelegramClient::ADMIN_MANAGE_CALL       // 0x00800
TelegramClient::ADMIN_OTHER             // 0x01000  ← WAJIB agar admin aktif
TelegramClient::ADMIN_MANAGE_TOPICS     // 0x02000  (forum supergroup)

// Ban/Restrict Rights — gunakan di restrictUser(), setDefaultPermissions()
TelegramClient::BAN_VIEW_MESSAGES   // 0x000001
TelegramClient::BAN_SEND_MESSAGES   // 0x000002
TelegramClient::BAN_SEND_MEDIA      // 0x000004
TelegramClient::BAN_SEND_STICKERS   // 0x000008
TelegramClient::BAN_SEND_GIFS       // 0x000010
TelegramClient::BAN_SEND_GAMES      // 0x000020
TelegramClient::BAN_SEND_INLINE     // 0x000040
TelegramClient::BAN_EMBED_LINKS     // 0x000080
TelegramClient::BAN_SEND_POLLS      // 0x000100
TelegramClient::BAN_CHANGE_INFO     // 0x000400
TelegramClient::BAN_INVITE_USERS    // 0x008000
TelegramClient::BAN_PIN_MESSAGES    // 0x020000
TelegramClient::BAN_SEND_PHOTOS     // 0x080000
TelegramClient::BAN_SEND_VIDEOS     // 0x100000
TelegramClient::BAN_SEND_AUDIOS     // 0x400000
TelegramClient::BAN_SEND_DOCS       // 0x800000
```

---

## 22. Referensi API Lengkap

### TelegramClient — Ringkasan Semua Metode Publik

#### Koneksi & Inisialisasi

| Metode                                                    | Return   | Keterangan                               |
|-----------------------------------------------------------|----------|------------------------------------------|
| `start($phone, $codeCallback, $passCallback, $botToken)`  | `void`   | Login all-in-one                         |
| `connect(?int $dcId, bool $isReconnect)`                  | `void`   | Connect manual                           |
| `disconnect()`                                            | `void`   | Putuskan koneksi                         |
| `isConnected()`                                           | `bool`   | Status koneksi                           |
| `syncUpdateState()`                                       | `void`   | Sync state update dengan server          |
| `getLayer()`                                              | `int`    | Layer MTProto aktif (214)                |
| `getMe()`                                                 | `array`  | Info akun saat ini                       |

#### Pesan

| Metode                                                           | Return  | Keterangan                     |
|------------------------------------------------------------------|---------|--------------------------------|
| `sendMessage($peer, $text, ?$replyTo)`                           | `array` | Kirim pesan teks               |
| `sendFile($peer, $path, $caption, $forceDoc, $replyTo, $prog)`   | `array` | Kirim file (auto-detect tipe)  |
| `sendPhoto($peer, $path, $caption, $replyTo, $prog)`             | `array` | Kirim foto                     |
| `sendVideo($peer, $path, $caption, $dur, $w, $h, $replyTo, $prog)` | `array` | Kirim video                  |
| `sendAudio($peer, $path, $caption, $dur, $title, $perf, $replyTo, $prog)` | `array` | Kirim audio        |
| `sendDocument($peer, $path, $caption, $filename, $replyTo, $prog)` | `array` | Kirim dokumen                |
| `sendVoice($peer, $path, $duration, $replyTo, $prog)`            | `array` | Kirim pesan suara              |
| `sendPoll($peer, $question, $answers, ...)`                      | `array` | Kirim polling                  |
| `forwardMessages($to, $ids, $from, $dropAuthor)`                 | `array` | Teruskan pesan                 |
| `editMessage($peer, $msgId, $text)`                              | `array` | Edit pesan                     |
| `deleteMessages($ids, $peer, $revoke)`                           | `array` | Hapus pesan                    |
| `pinMessage($peer, $msgId, $silent)`                             | `array` | Pin pesan                      |
| `unpinMessage($peer, $msgId)`                                    | `array` | Unpin pesan                    |
| `startBot($bot, $peer, $startParam)`                             | `array` | Start bot dengan parameter     |

#### Riwayat & Dialog

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `getDialogs($limit, $allPages)`                    | `array` | Daftar semua dialog                      |
| `getHistory($peer, $limit, $offsetId, ...)`        | `array` | Riwayat pesan                            |
| `getContacts()`                                    | `array` | Daftar kontak                            |

#### Download Media

| Metode                                              | Return   | Keterangan                               |
|-----------------------------------------------------|----------|------------------------------------------|
| `downloadMedia($message, $savePath, $progress)`     | `string` | Download dari pesan (auto-detect tipe)   |
| `downloadPhoto($id, $hash, $ref, $path, ...)`        | `string` | Download foto by ID                      |
| `downloadDocument($id, $hash, $ref, $path, ...)`     | `string` | Download dokumen by ID                   |

#### Pencarian

| Metode                                              | Return  | Keterangan                               |
|-----------------------------------------------------|---------|------------------------------------------|
| `search($peer, $query, $limit, $offsetId, $filter)` | `array` | Cari pesan di chat tertentu              |
| `searchGlobal($query, $limit)`                      | `array` | Cari pesan di semua chat                 |

#### Info Lengkap

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `getFullUser($user)`                               | `array` | Info lengkap user                        |
| `getFullChat($chatId)`                             | `array` | Info lengkap basic group                 |
| `getFullChannel($channel)`                         | `array` | Info lengkap channel/supergroup          |
| `getAdminChannels($dialogLimit)`                   | `array` | Channel di mana kita adalah admin        |
| `getChannelMembers($channel, $filter, $limit, $offset)` | `array` | Anggota channel/supergroup          |
| `getChatMembers($chat)`                            | `array` | Anggota basic group                      |

#### Manajemen Grup & Channel

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `joinChannel($peer)`                               | `array` | Bergabung ke channel/supergroup          |
| `leaveChannel($peer)`                              | `array` | Keluar dari channel/supergroup           |
| `inviteToChannel($channel, $users)`                | `array` | Undang user ke channel/supergroup        |
| `createChat($title, $users)`                       | `array` | Buat basic group baru                    |
| `createChannel($title, $about, $mega, $forum)`     | `array` | Buat channel/supergroup baru             |
| `deleteChat($peer)`                                | `array` | Hapus grup/channel (hanya owner)         |
| `migrateChat($chatId)`                             | `array` | Upgrade basic group ke supergroup        |
| `editChatTitle($peer, $title)`                     | `array` | Ubah judul                               |
| `editChatAbout($channel, $about)`                  | `array` | Ubah deskripsi channel/supergroup        |
| `addChatUser($chatId, $user, $fwdLimit)`            | `array` | Tambah user ke basic group               |
| `toggleSlowMode($channel, $seconds)`               | `array` | Aktif/nonaktif slow mode                 |
| `exportInviteLink($peer, ...)`                     | `array` | Buat link undangan                       |
| `setDefaultPermissions($peer, $bannedRights)`      | `array` | Atur hak default anggota                 |
| `toggleSignatures($channel, $enabled)`             | `array` | Tanda tangan admin di channel broadcast  |
| `toggleJoinToSend($channel, $enabled)`             | `array` | Wajib join sebelum kirim pesan           |
| `toggleJoinRequest($channel, $enabled)`            | `array` | Persetujuan admin untuk join             |

#### Manajemen Admin

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `promoteAdmin($channel, $user, $rights, $rank)`    | `array` | Jadikan admin                            |
| `demoteAdmin($channel, $user)`                     | `array` | Cabut status admin                       |

#### Manajemen Anggota

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `banUser($channel, $user, $untilDate)`             | `array` | Ban user                                 |
| `unbanUser($channel, $user)`                       | `array` | Hapus ban                                |
| `kickUser($channel, $user)`                        | `array` | Keluarkan (bisa kembali)                 |
| `restrictUser($channel, $user, $flags, $until)`    | `array` | Batasi hak user dengan flag kustom       |
| `muteUser($channel, $user, $seconds)`              | `array` | Bisukan user                             |
| `readOnlyUser($channel, $user, $seconds)`          | `array` | User hanya bisa baca                     |

#### Event & Loop

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `on(NewMessage $filter, callable $handler)`        | `void`  | Daftarkan handler pesan baru             |
| `onUpdate(callable $handler)`                      | `void`  | Daftarkan handler update mentah          |
| `runUntilDisconnected()`                           | `void`  | Jalankan event loop utama                |
| `pollOnce(int $timeoutSeconds)`                    | `bool`  | Poll update satu kali                    |
| `removeHandlers()`                                 | `void`  | Hapus semua handler                      |

#### Koneksi & Konfigurasi

| Metode                                             | Return      | Keterangan                               |
|----------------------------------------------------|-------------|------------------------------------------|
| `setProxy($host, $port, $user, $pass)`             | `void`      | Set proxy SOCKS5                         |
| `clearProxy()`                                     | `void`      | Hapus proxy                              |
| `resolvePeer($peer)`                               | `InputPeer` | Resolve peer ke InputPeer                |
| `invoke($request)`                                 | `array`     | Invoke RPC request langsung              |
| `exportAuthorization($dcId)`                       | `array`     | Export otorisasi (migrasi DC)            |

#### Getter Modul

| Metode             | Return            | Keterangan                          |
|--------------------|-------------------|-------------------------------------|
| `getSession()`     | `AbstractSession` | Objek sesi aktif                    |
| `getAuth()`        | `Auth`            | Modul autentikasi                   |
| `getMessages()`    | `Messages`        | Modul pesan                         |
| `getAccount()`     | `Account`         | Modul akun                          |
| `getDownloader()`  | `FileDownloader`  | Modul download                      |
| `getSender()`      | `MTProtoSender`   | Sender MTProto (raw)                |
| `getApiId()`       | `int`             | API ID                              |
| `getApiHash()`     | `string`          | API Hash                            |

### Account — Metode Publik

| Metode                                             | Return  | Keterangan                               |
|----------------------------------------------------|---------|------------------------------------------|
| `updateProfile($firstName, $lastName, $about)`     | `array` | Update nama / bio                        |
| `updateUsername($username)`                        | `array` | Ubah username (@handle)                  |
| `uploadProfilePhoto($filePath, $onProgress)`       | `array` | Upload & set foto profil                 |
| `getAuthorizations()`                              | `array` | Daftar semua sesi aktif                  |
| `resetAuthorization($hash)`                        | `bool`  | Logout sesi tertentu                     |
| `terminateAllOtherSessions()`                      | `int`   | Logout semua sesi lain                   |
| `getPrivacy($key)`                                 | `array` | Baca pengaturan privasi                  |
| `setPrivacy($key, $rules)`                         | `bool`  | Atur pengaturan privasi                  |

### Auth — Metode Publik

| Metode                                                        | Return  | Keterangan                     |
|---------------------------------------------------------------|---------|--------------------------------|
| `sendCode($phoneNumber)`                                      | `array` | Kirim kode OTP                 |
| `signIn($phone, $phoneCodeHash, $phoneCode)`                  | `array` | Sign in dengan kode OTP        |
| `checkPassword($password)`                                    | `array` | Verifikasi password 2FA        |
| `getPasswordInfo()`                                           | `array` | Info pengaturan 2FA            |
| `loginAsBot($botToken)`                                       | `array` | Login sebagai bot              |
| `isAuthorized()`                                              | `bool`  | Cek status login               |
| `logOut()`                                                    | `bool`  | Logout akun                    |

---

## Contoh Lengkap

### Bot Pesan Masuk dengan Klik Tombol

```php
<?php
require_once __DIR__ . '/src/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client = new TelegramClient(API_ID, API_HASH, 'bot_sesi');
$client->start(botToken: 'BOT_TOKEN');

$client->on(new NewMessage(incoming: true), function(NewMessageEvent $event) use ($client) {
    $msg  = $event->message;
    $text = $event->rawText;

    if ($text === '/start') {
        $client->sendMessage($msg->peerId, 'Halo! Selamat datang.');
        return;
    }

    // Jika pesan berisi tombol inline, klik berdasarkan label
    if ($msg->replyMarkup) {
        try {
            $result = $msg->click('✅ Lanjut');
        } catch (\Exception $e) {
            // Tombol tidak ditemukan atau tidak bisa diklik
        }
    }
});

$client->runUntilDisconnected();
```

### Listener Update Mentah

```php
use XnoxsProto\Events\RawUpdateEvent;

$client->onUpdate(function(RawUpdateEvent $event) {
    if ($event->type === 'user_status') {
        $online = $event->online ? 'online' : 'offline';
        echo "User {$event->userId} sekarang {$online}\n";
    }

    if ($event->type === 'delete_messages') {
        echo "Pesan dihapus: " . implode(', ', $event->messageIds) . "\n";
    }

    if ($event->type === 'edit_message') {
        echo "Pesan diedit: {$event->message->text}\n";
    }
});

$client->runUntilDisconnected();
```

### Otomasi Admin & Moderasi

```php
// Promote admin dengan hak kustom
$client->promoteAdmin('@supergroup', '@user',
    TelegramClient::ADMIN_DELETE_MESSAGES
    | TelegramClient::ADMIN_BAN_USERS
    | TelegramClient::ADMIN_PIN_MESSAGES
    | TelegramClient::ADMIN_OTHER,  // wajib!
    'Moderator'
);

// Mute user 1 jam
$client->muteUser('@supergroup', '@user', 3600);

// Read-only selamanya
$client->readOnlyUser('@supergroup', '@user');

// Ban sementara 1 hari
$client->banUser('@supergroup', '@user', time() + 86400);

// Larang hanya stiker dan GIF
$client->restrictUser('@supergroup', '@user',
    TelegramClient::BAN_SEND_STICKERS | TelegramClient::BAN_SEND_GIFS
);

// Demote admin
$client->demoteAdmin('@supergroup', '@user');
```

### Manajemen Sesi Aktif

```php
$account = $client->getAccount();

// Tampilkan semua sesi
$sessions = $account->getAuthorizations();
foreach ($sessions as $s) {
    $mark = $s['current'] ? ' ← SESI INI' : '';
    echo "[{$s['device_model']}] {$s['app_name']} — {$s['ip']} ({$s['country']}){$mark}\n";
}

// Logout semua sesi lain
$n = $account->terminateAllOtherSessions();
echo "Dilogout: {$n} sesi\n";
```

### Kirim Poll Kuis

```php
$client->sendPoll('@group',
    question:      'PHP singkatan dari?',
    answers:       ['Personal Home Page', 'PHP: Hypertext Preprocessor', 'Python Hypertext'],
    isQuiz:        true,
    correctIndex:  1,
    solution:      'PHP adalah rekursif singkatan dari "PHP: Hypertext Preprocessor".'
);
```
