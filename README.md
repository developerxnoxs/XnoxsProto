# XnoxsProto

> Implementasi murni PHP dari protokol MTProto Telegram — tanpa wrapper, tanpa klien API pihak ketiga, berkomunikasi langsung dengan server Telegram.

![PHP](https://img.shields.io/badge/PHP-8.2+-blue?logo=php)
![Layer](https://img.shields.io/badge/MTProto_Layer-214-blue)
![Lisensi](https://img.shields.io/badge/Lisensi-MIT-green)
![Status](https://img.shields.io/badge/Status-Aktif_Dikembangkan-orange)

---

## Tentang Proyek

XnoxsProto adalah library PHP yang mengimplementasikan protokol [MTProto Telegram](https://core.telegram.org/mtproto) dari nol. Library ini menangani segalanya mulai dari pertukaran kunci DH dan enkripsi AES-IGE hingga panggilan API tingkat tinggi — semuanya dalam PHP murni, tanpa dependensi Composer selain ekstensi bawaan PHP.

**Target pengguna:** skrip otomasi, bot, klien pengguna, dan tooling Telegram yang ditulis dalam PHP.

> **Status pengembangan:** Fitur inti sudah berfungsi dan telah diuji langsung terhadap server Telegram nyata (Layer 214). Library ini masih aktif dikembangkan. API dapat berubah antar versi.

---

## Persyaratan

- PHP **8.2** atau lebih baru
- Ekstensi wajib: `ext-gmp`, `ext-openssl`, `ext-mbstring`, `ext-json`
- `ext-curl` disarankan (tidak wajib)

---

## Instalasi

```bash
git clone https://github.com/yourusername/xnoxsproto.git
cd xnoxsproto
composer install
```

Dapatkan kredensial API di **https://my.telegram.org/apps**

---

## Mulai Cepat

### Login akun pengguna

```php
<?php
require_once __DIR__ . '/src/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = new TelegramClient(API_ID, 'API_HASH', 'nama_sesi');

// Pertama kali: meminta kode OTP via STDIN
// Jika ada 2FA: meminta password otomatis
$client->start('+628123456789');

$me = $client->getMe();
echo "Login sebagai: {$me['first_name']} (ID: {$me['id']})\n";

$client->sendMessage('@username', 'Halo dari XnoxsProto!');
```

### Login bot

```php
$client = new TelegramClient(API_ID, 'API_HASH', 'bot_sesi');
$client->start(botToken: '123456789:AABBcc...');
```

### Koneksi ulang dengan sesi tersimpan

```php
// Sesi sudah ada — tidak perlu login ulang
$client = new TelegramClient(API_ID, 'API_HASH', 'nama_sesi');
$client->start('+628123456789'); // langsung sync, tanpa OTP
$client->sendMessage('me', 'Masih tersambung.');
```

---

## Ringkasan Fitur

### Lapisan Protokol
| Fitur | Status |
|-------|--------|
| MTProto Layer 214 (terbaru) | ✅ |
| Transport TCP Abridged | ✅ |
| Pertukaran kunci DH penuh (Authenticator) | ✅ |
| Enkripsi / dekripsi AES-IGE | ✅ |
| Pertukaran kunci RSA | ✅ |
| SRP (Secure Remote Password, 2FA) | ✅ |
| Deteksi layer otomatis saat connect | ✅ |
| Migrasi datacenter otomatis | ✅ |
| Auto-retry `bad_server_salt` | ✅ |
| Penanganan multi-pesan `msg_container` | ✅ |
| Respons terkompresi gzip | ✅ |
| Dukungan proxy SOCKS5 | ✅ |

### Autentikasi
| Fitur | Status |
|-------|--------|
| Login nomor telepon (OTP) | ✅ |
| Kata sandi cloud 2FA (SRP) | ✅ |
| Login token bot | ✅ |
| Persistensi sesi (file / memori) | ✅ |
| Login QR code | ✅ |

### Pesan
| Fitur | Status |
|-------|--------|
| Kirim pesan teks | ✅ |
| Edit pesan | ✅ |
| Hapus pesan (dengan revoke) | ✅ |
| Forward pesan | ✅ |
| Balas pesan | ✅ |
| Kirim polling (biasa, kuis, pilihan ganda) | ✅ |
| Pin / unpin pesan | ✅ |
| Ambil riwayat chat | ✅ |
| Cari pesan (dalam chat & global) | ✅ |
| Klik tombol inline berdasarkan posisi atau teks label | ✅ |
| Mulai bot dengan parameter `/start` | ✅ |
| Reaksi pesan | ✅ |

### Media
| Fitur | Status |
|-------|--------|
| Kirim foto (inline / dokumen) | ✅ |
| Kirim video | ✅ |
| Kirim audio / MP3 | ✅ |
| Kirim pesan suara | ✅ |
| Kirim dokumen (semua tipe file) | ✅ |
| Deteksi tipe media otomatis dari ekstensi | ✅ |
| Upload bertahap (512 KB per chunk) | ✅ |
| Upload file besar (> 10 MB) | ✅ |
| Download foto / dokumen / audio | ✅ |
| Download dengan callback progres | ✅ |
| Migrasi DC saat download | ✅ |
| Auto-refresh `FILE_REFERENCE_EXPIRED` | ✅ |

### Kontak & Dialog
| Fitur | Status |
|-------|--------|
| Ambil semua dialog (dengan paginasi) | ✅ |
| Ambil daftar kontak | ✅ |
| Resolve username / nomor / ID ke InputPeer | ✅ |
| Info lengkap pengguna (`getFullUser`) | ✅ |
| Info lengkap chat / channel | ✅ |

### Grup & Channel
| Fitur | Status |
|-------|--------|
| Buat grup biasa (basic group) | ✅ |
| Buat supergroup / channel broadcast | ✅ |
| Hapus grup / channel | ✅ |
| Upgrade grup biasa → supergroup | ✅ |
| Edit judul / deskripsi | ✅ |
| Tambah anggota ke grup biasa | ✅ |
| Undang ke channel / supergroup | ✅ |
| Gabung / keluar channel | ✅ |
| Ambil daftar anggota channel | ✅ |
| Ambil daftar anggota basic group | ✅ |
| Promosi / turunkan admin | ✅ |
| Ban / unban / kick anggota | ✅ |
| Restrict / mute / read-only anggota | ✅ |
| Slow mode | ✅ |
| Export link undangan (dengan opsi expiry, batas, persetujuan) | ✅ |
| Atur izin default anggota | ✅ |
| Tanda tangan admin di channel broadcast | ✅ |
| Wajib join sebelum kirim pesan | ✅ |
| Wajib persetujuan admin untuk join | ✅ |
| Ambil channel yang dikelola admin | ✅ |

### Akun
| Fitur | Status |
|-------|--------|
| Update profil (nama, bio) | ✅ |
| Update username | ✅ |
| Upload foto profil | ✅ |
| Lihat sesi aktif (otorisasi) | ✅ |
| Hapus sesi berdasarkan hash | ✅ |
| Keluar dari semua sesi lain | ✅ |
| Baca / ubah pengaturan privasi | ✅ |

### Penanganan Update / Event
| Fitur | Status |
|-------|--------|
| `on(NewMessage, callable)` — handler dengan filter | ✅ |
| `onUpdate(callable)` — handler update mentah | ✅ |
| `runUntilDisconnected()` — loop event | ✅ |
| `pollOnce()` — satu tick polling | ✅ |
| Filter pesan masuk / keluar / dari peer tertentu | ✅ |

---

## Contoh Kode

### Handler pesan masuk dengan klik tombol

```php
use XnoxsProto\Events\NewMessage;
use XnoxsProto\Events\NewMessageEvent;

$client->on(new NewMessage(incoming: true), function(NewMessageEvent $event) use ($client) {
    $msg  = $event->message;
    $text = $event->rawText;

    if ($text === '/start') {
        $client->sendMessage($msg->peerId, 'Halo! Selamat datang.');
        return;
    }

    // Klik tombol inline berdasarkan teks label
    if ($msg->replyMarkup) {
        $msg->click('✅ Lanjut');      // exact match
        $msg->click('lanjut');         // partial match (case-insensitive)
        $msg->click(0, 0);             // posisi baris 0, kolom 0
    }
});

$client->runUntilDisconnected();
```

### Download media dari riwayat chat

```php
$messages = $client->getHistory('@channel', 20);

foreach ($messages as $msg) {
    if (empty($msg['media'])) continue;

    $path = $client->downloadMedia($msg, "downloads/file_{$msg['id']}",
        function(int $recv, int $total, int $pct) {
            echo "\r  {$pct}%";
        }
    );
    echo "\nTersimpan: {$path}\n";
}
```

### Listener update mentah

```php
use XnoxsProto\Events\RawUpdateEvent;

$client->onUpdate(function(RawUpdateEvent $event) {
    if ($event->type === 'user_status') {
        $status = $event->online ? 'online' : 'offline';
        echo "User {$event->userId} sekarang {$status}\n";
    }

    if ($event->type === 'edit_message') {
        echo "Pesan diedit: {$event->message->text}\n";
    }

    if ($event->type === 'delete_messages') {
        echo "Dihapus: " . implode(', ', $event->messageIds) . "\n";
    }
});

$client->runUntilDisconnected();
```

### Manajemen admin & moderasi

```php
// Promote admin dengan hak kustom
$client->promoteAdmin('@supergroup', '@user',
    TelegramClient::ADMIN_DELETE_MESSAGES
    | TelegramClient::ADMIN_BAN_USERS
    | TelegramClient::ADMIN_PIN_MESSAGES
    | TelegramClient::ADMIN_OTHER,  // wajib agar admin aktif
    'Moderator'
);

// Mute user 1 jam
$client->muteUser('@supergroup', '@user', 3600);

// Read-only selamanya
$client->readOnlyUser('@supergroup', '@user');

// Ban sementara 1 hari
$client->banUser('@supergroup', '@user', time() + 86400);
```

### Buat supergroup dan kelola anggota

```php
// Buat supergroup baru
$result = $client->createChannel('Grup Diskusi', 'Deskripsi grup', megagroup: true);

// Undang anggota
$client->inviteToChannel($result['channel_id'], ['@alice', '@bob']);

// Export link undangan dengan batas pemakaian
$link = $client->exportInviteLink($result['channel_id'],
    usageLimit: 100,
    title:      'Link Acara'
);
echo $link['link']; // https://t.me/+...
```

### Proxy SOCKS5

```php
$client->setProxy('127.0.0.1', 1080);
$client->setProxy('127.0.0.1', 1080, 'user', 'password'); // dengan autentikasi
$client->start('+62812...');
```

---

## Arsitektur

```
src/
├── Client/
│   ├── TelegramClient.php     # Titik masuk utama — semua metode tingkat tinggi
│   ├── Auth.php               # Login, 2FA, login bot, logout
│   ├── Messages.php           # Pesan, riwayat, pencarian, kirim media
│   ├── Account.php            # Profil, privasi, manajemen sesi
│   └── FileDownloader.php     # Download bertahap, migrasi DC, file_reference refresh
│
├── Network/
│   ├── Connection.php         # Soket TCP mentah
│   ├── Socks5Connection.php   # Terowongan proxy SOCKS5
│   ├── TcpAbridged.php        # Framing TCP Abridged MTProto
│   ├── MTProtoPlainSender.php # Pengirim tidak terenkripsi (handshake auth)
│   ├── MTProtoSender.php      # Pengirim terenkripsi (sesi utama)
│   ├── Authenticator.php      # Pertukaran kunci DH
│   └── LayerDetector.php      # Deteksi layer API otomatis saat connect
│
├── Crypto/
│   ├── AES.php                # Enkripsi/dekripsi AES-IGE
│   ├── RSA.php                # RSA untuk handshake DH
│   ├── AuthKey.php            # Wadah auth key + key ID
│   └── SRP.php                # SRP-2048 untuk password cloud 2FA
│
├── TL/
│   ├── BinaryReader.php       # Deserializer biner TL
│   ├── BinaryWriter.php       # Serializer biner TL
│   ├── Types/                 # Tipe TL: User, Chat, FullMessage, InputPeer …
│   ├── Functions/             # Metode TL: SendMessage, GetHistory, EditAdmin …
│   └── Parser/
│       ├── UpdateParser.php   # Parse semua tipe update server-push
│       └── TLSkipHelper.php   # Lewati objek TL sembarang berdasarkan constructor
│
├── Sessions/
│   ├── AbstractSession.php    # Antarmuka sesi
│   ├── FileSession.php        # Sesi berbasis file (.session)
│   └── MemorySession.php      # Sesi dalam memori (tanpa persistensi)
│
├── Upload/
│   └── FileUploader.php       # Upload bertahap (file kecil & besar)
│
├── Events/
│   ├── NewMessage.php         # Filter event pesan baru
│   ├── NewMessageEvent.php    # Objek event pesan baru
│   └── RawUpdateEvent.php     # Objek update mentah
│
└── Exceptions/
    └── RPCException.php       # Error RPC Telegram (errorCode, errorMessage)
```

---

## File Sesi

Sesi disimpan ke file `.session` yang menyimpan auth key, server salt, info DC, dan layer API — sehingga koneksi berikutnya langsung tersambung tanpa autentikasi ulang.

```
sessions/
└── 628123456789.session    ← dibuat otomatis saat login pertama kali
```

---

## Dokumentasi

Dokumentasi lengkap dalam Bahasa Indonesia tersedia di [`DOKUMENTASI.md`](DOKUMENTASI.md).

Mencakup:
- Semua tanda tangan metode beserta parameter dan nilai kembali
- Struktur data (FullMessage, replyMarkup, update types)
- Upload & download media (migrasi DC, penanganan `FILE_REFERENCE_EXPIRED`)
- Pola event handler dan filter
- Panduan lengkap manajemen grup/channel/admin
- Konstanta `ADMIN_*` dan `BAN_*`
- Pengaturan privasi akun

---

## Keterbatasan yang Diketahui

- Tidak ada konkurensi multi-akun (satu sesi per proses)
- Tidak mendukung MTProto v1 (DC lama)
- Tidak ada download file CDN
- Tidak mendukung chat rahasia (end-to-end encrypted)
- Tidak ada MTProto over WebSocket

---

## Referensi

- [Telegram MTProto](https://core.telegram.org/mtproto)
- [Metode API Telegram](https://core.telegram.org/methods)
- [Skema TDLib](https://github.com/tdlib/td/blob/master/td/generate/scheme/telegram_api.tl)
- [Telethon](https://github.com/LonamiWebs/Telethon) — implementasi referensi Python

---

## Lisensi

MIT — lihat [LICENSE](LICENSE)
