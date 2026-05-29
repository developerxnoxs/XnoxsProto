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
- Ekstensi: `ext-gmp`, `ext-openssl`, `ext-mbstring`, `ext-json`, `ext-curl`

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

### Login (nomor telepon)

```bash
php interactive_login.php
```

### Contoh kode

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = TelegramClient::create(YOUR_API_ID, 'YOUR_API_HASH');

// Pertama kali: meminta login via telepon & kode OTP
$client->start('+628123456789');

// Setelah login, sesi tersimpan otomatis
$me = $client->getMe();
echo "Login sebagai: " . $me['first_name'] . " (ID: " . $me['id'] . ")\n";

$client->sendMessage('@username', 'Halo dari XnoxsProto!');
$client->disconnect();
```

### Koneksi ulang dengan sesi tersimpan

```php
$client = TelegramClient::create(YOUR_API_ID, 'YOUR_API_HASH', 'sessions/saya.session');
$client->connect();

// Sesi dipulihkan — tidak perlu login ulang
$client->sendMessage('me', 'Masih di sini.');
$client->disconnect();
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
| Login QR code | 🔜 |

### Pesan
| Fitur | Status |
|-------|--------|
| Kirim pesan teks | ✅ |
| Edit pesan | ✅ |
| Hapus pesan (dengan revoke) | ✅ |
| Forward pesan | ✅ |
| Balas pesan | ✅ |
| Kirim polling | ✅ |
| Pin / unpin pesan | ✅ |
| Ambil riwayat chat | ✅ |
| Cari pesan (dalam chat & global) | ✅ |
| Klik tombol inline / keyboard | ✅ |
| Mulai bot dengan parameter `/start` | ✅ |
| Reaksi pesan | 🔜 |

### Media
| Fitur | Status |
|-------|--------|
| Kirim foto (inline / dokumen) | ✅ |
| Kirim video | ✅ |
| Kirim audio / MP3 | ✅ |
| Kirim pesan suara | ✅ |
| Kirim dokumen (semua tipe file) | ✅ |
| Deteksi tipe media otomatis | ✅ |
| Upload bertahap (512 KB per chunk) | ✅ |
| Upload file besar (> 10 MB) | ✅ |
| Download foto / dokumen / audio | ✅ |
| Download dengan callback progres | ✅ |
| Migrasi DC saat download | ✅ |
| Auto-refresh `FILE_REFERENCE_EXPIRED` | ✅ |
| Kirim stiker / GIF | 🔜 |

### Kontak & Dialog
| Fitur | Status |
|-------|--------|
| Ambil semua dialog (dengan paginasi) | ✅ |
| Ambil daftar kontak | ✅ |
| Resolve username / peer | ✅ |
| Info lengkap pengguna | ✅ |
| Info lengkap chat / channel | ✅ |

### Grup & Channel
| Fitur | Status |
|-------|--------|
| Buat grup biasa | ✅ |
| Buat supergroup / channel | ✅ |
| Hapus grup / channel | ✅ |
| Upgrade grup biasa → supergroup | ✅ |
| Edit judul / deskripsi | ✅ |
| Tambah anggota ke grup | ✅ |
| Undang ke channel | ✅ |
| Gabung / keluar channel | ✅ |
| Ambil daftar anggota channel | ✅ |
| Promosi / turunkan admin | ✅ |
| Ban / unban / kick anggota | ✅ |
| Restrict / bisu / read-only anggota | ✅ |
| Slow mode | ✅ |
| Export link undangan | ✅ |
| Atur izin default anggota | ✅ |
| Tanda tangan admin di channel | ✅ |
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
| `on(NewMessageFilter, callable)` — handler dengan filter | ✅ |
| `onUpdate(callable)` — handler update mentah | ✅ |
| `runUntilDisconnected()` — loop event | ✅ |
| `pollOnce()` — satu tick polling | ✅ |
| Filter berdasarkan peer / kata kunci / tipe media | ✅ |

---

## Contoh Kode

### Download media dari riwayat chat

```php
$messages = $client->getHistory('@channel', 20);

foreach ($messages as $msg) {
    if (empty($msg['media'])) continue;

    $ext  = $client->getMediaExtension($msg['media']);
    $path = "downloads/file_{$msg['id']}.$ext";

    $client->downloadMedia($msg, $path, function (int $recv, int $total, int $pct) {
        echo "\r  $pct% — " . number_format($recv) . "/" . number_format($total) . " bytes";
    });

    echo "\nTersimpan: $path\n";
}
```

### Listener pesan berbasis event

```php
use XnoxsProto\Events\NewMessage;

$client->on(NewMessage::filter(fromUsers: ['@alice', '@bob']), function ($event, $client) {
    $msg = $event->message;
    echo "[{$msg['date']}] {$msg['from_name']}: {$msg['text']}\n";

    if (str_contains($msg['text'], 'ping')) {
        $client->sendMessage($event->peer, 'pong');
    }
});

$client->runUntilDisconnected();
```

### Manajemen grup / channel

```php
// Buat supergroup baru
$group = $client->createChannel('Grup Saya', 'Deskripsi grup', megagroup: true);

// Undang anggota
$client->inviteToChannel($group['id'], ['@alice', '@bob']);

// Promosi admin
$client->promoteAdmin($group['id'], '@alice', canDeleteMessages: true, canBanUsers: true);

// Export link undangan
$link = $client->exportInviteLink($group['id']);
echo $link['link'];
```

### Proxy SOCKS5

```php
$client->setProxy('127.0.0.1', 1080);                       // tanpa autentikasi
$client->setProxy('127.0.0.1', 1080, 'user', 'password');   // dengan autentikasi
$client->connect();
```

### Login sebagai bot

```php
$client->getAuth()->loginAsBot('TOKEN_BOT_KAMU:DI_SINI');
$me = $client->getMe();
echo "Bot: @" . $me['username'];
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
│   └── FileDownloader.php     # Download bertahap, migrasi DC
│
├── Network/
│   ├── Connection.php         # Soket TCP mentah (IPv4/IPv6)
│   ├── Socks5Connection.php   # Terowongan proxy SOCKS5
│   ├── TcpAbridged.php        # Framing TCP Abridged MTProto
│   ├── MTProtoPlainSender.php # Pengirim tidak terenkripsi (handshake auth)
│   ├── MTProtoSender.php      # Pengirim terenkripsi (sesi utama)
│   ├── Authenticator.php      # Pertukaran kunci DH (Langkah 1–9)
│   └── LayerDetector.php      # Deteksi layer API otomatis saat connect
│
├── Crypto/
│   ├── AES.php                # Enkripsi/dekripsi AES-IGE
│   ├── RSA.php                # RSA (PKCS#1 v1.5) untuk handshake DH
│   ├── AuthKey.php            # Wadah auth key + key ID
│   └── SRP.php                # SRP-2048 untuk kata sandi cloud 2FA
│
├── TL/
│   ├── BinaryReader.php       # Deserializer biner TL
│   ├── BinaryWriter.php       # Serializer biner TL
│   ├── TLObject.php           # Tipe dasar TL
│   ├── Types/                 # Kelas tipe TL (User, Chat, Message …)
│   ├── Functions/             # Kelas metode TL (SendMessage, GetHistory …)
│   └── Parser/
│       └── TLSkipHelper.php   # Baca/lewati objek TL sembarang berdasarkan constructor
│
├── Sessions/
│   ├── AbstractSession.php    # Antarmuka sesi
│   ├── FileSession.php        # Sesi berbasis file persisten (.session)
│   └── MemorySession.php      # Sesi dalam memori (tanpa persistensi)
│
├── Upload/
│   └── FileUploader.php       # Upload bertahap (mode file kecil & besar)
│
├── Events/
│   ├── NewMessage.php         # Event pesan baru bertipe + pembangun filter
│   ├── NewMessageEvent.php    # Pembungkus event
│   └── RawUpdateEvent.php     # Pembungkus update TL mentah
│
├── Helpers/
│   └── Helpers.php            # Fungsi utilitas
│
└── Exceptions/
    └── RPCException.php       # Error RPC Telegram ($errorCode, $errorMessage)
```

---

## File Sesi

Sesi disimpan ke `sessions/<nomor_telepon>.session` secara default (dapat dikonfigurasi). File ini menyimpan auth key, server salt, info DC, dan layer API yang dinegosiasikan — sehingga koneksi berikutnya langsung tersambung tanpa autentikasi ulang.

```
sessions/
└── 628123456789.session    ← file sesi biner
```

---

## Dokumentasi

Dokumentasi lengkap dalam Bahasa Indonesia (tanda tangan metode, contoh, catatan protokol): [`DOKUMENTASI.md`](DOKUMENTASI.md)

Topik yang dibahas:
- Internal protokol MTProto
- Semua tanda tangan metode beserta parameter dan tipe kembalian
- Upload & download media (termasuk migrasi DC, penanganan `FILE_REFERENCE_EXPIRED`)
- Pola penanganan event
- Panduan manajemen grup/channel
- Referensi pengaturan privasi
- Changelog & perbedaan constructor Layer 214 vs layer lama

---

## Keterbatasan yang Diketahui

- Tidak ada konkurensi multi-akun (satu sesi per proses)
- Tidak mendukung MTProto v1 (DC lama)
- Tidak ada download file CDN (redirect ke DC CDN)
- Tidak mendukung chat rahasia (end-to-end encrypted)
- Tidak ada MTProto over WebSocket

---

## Referensi

- [Telegram MTProto](https://core.telegram.org/mtproto)
- [Metode API Telegram](https://core.telegram.org/methods)
- [Skema TDLib](https://github.com/tdlib/td/blob/master/td/generate/scheme/telegram_api.tl)
- [Telethon](https://github.com/LonamiWebs/Telethon) — implementasi referensi untuk cross-check protokol

---

## Lisensi

MIT — lihat [LICENSE](LICENSE)
