# XnoxsProto

> Pure PHP implementation of Telegram's MTProto protocol — no wrappers, no third-party API clients, communicates directly with Telegram servers.

![PHP](https://img.shields.io/badge/PHP-8.2+-blue?logo=php)
![Layer](https://img.shields.io/badge/MTProto_Layer-214-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Status](https://img.shields.io/badge/Status-Active_Development-orange)

---

## Overview

XnoxsProto is a ground-up PHP library implementing the full [Telegram MTProto protocol](https://core.telegram.org/mtproto). It handles everything from the DH key exchange and AES-IGE encryption layer up to high-level API calls — all in pure PHP, with no Composer dependencies beyond PHP's built-in extensions.

**Target use case:** automation scripts, bots, user clients, and Telegram tooling written in PHP.

> **Development status:** Core features are working and tested against real Telegram servers (Layer 214). The library is actively developed. APIs may change between versions.

---

## Requirements

- PHP **8.2** or later
- Extensions: `ext-gmp`, `ext-openssl`, `ext-mbstring`, `ext-json`, `ext-curl`

---

## Installation

```bash
git clone https://github.com/yourusername/xnoxsproto.git
cd xnoxsproto
composer install
```

Get API credentials from **https://my.telegram.org/apps**

---

## Quick Start

### Login (phone number)

```bash
php interactive_login.php
```

### Code example

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = TelegramClient::create(YOUR_API_ID, 'YOUR_API_HASH');

// First run: triggers interactive phone/OTP login
$client->start('+628123456789');

// After login, session is saved automatically
$me = $client->getMe();
echo "Logged in as: " . $me['first_name'] . " (ID: " . $me['id'] . ")\n";

$client->sendMessage('@username', 'Hello from XnoxsProto!');
$client->disconnect();
```

### Reconnecting with saved session

```php
$client = TelegramClient::create(YOUR_API_ID, 'YOUR_API_HASH', 'sessions/my.session');
$client->connect();

// Session is restored — no login needed
$client->sendMessage('me', 'Still here.');
$client->disconnect();
```

---

## Feature Overview

### Protocol Layer
| Feature | Status |
|---------|--------|
| MTProto Layer 214 (latest) | ✅ |
| TCP Abridged transport | ✅ |
| Full DH key exchange (Authenticator) | ✅ |
| AES-IGE encryption / decryption | ✅ |
| RSA key exchange | ✅ |
| SRP (Secure Remote Password, 2FA) | ✅ |
| Layer auto-detection at connect | ✅ |
| Auto datacenter migration | ✅ |
| `bad_server_salt` auto-retry | ✅ |
| `msg_container` multi-message handling | ✅ |
| gzip-compressed responses | ✅ |
| SOCKS5 proxy support | ✅ |

### Authentication
| Feature | Status |
|---------|--------|
| Phone number login (OTP) | ✅ |
| 2FA / cloud password (SRP) | ✅ |
| Bot token login | ✅ |
| Session persistence (file / memory) | ✅ |
| QR code login | 🔜 |

### Messaging
| Feature | Status |
|---------|--------|
| Send text messages | ✅ |
| Edit messages | ✅ |
| Delete messages (with revoke) | ✅ |
| Forward messages | ✅ |
| Reply to messages | ✅ |
| Send polls | ✅ |
| Pin / unpin messages | ✅ |
| Get chat history | ✅ |
| Search messages (in-chat & global) | ✅ |
| Click inline / keyboard buttons | ✅ |
| Start bot with `/start` parameter | ✅ |
| Reactions | 🔜 |

### Media
| Feature | Status |
|---------|--------|
| Send photo (inline / document) | ✅ |
| Send video | ✅ |
| Send audio / MP3 | ✅ |
| Send voice message | ✅ |
| Send document (any file type) | ✅ |
| Auto-detect media type | ✅ |
| Chunked upload (512 KB chunks) | ✅ |
| Big file upload (> 10 MB) | ✅ |
| Download photo / document / audio | ✅ |
| Download with progress callback | ✅ |
| DC migration during download | ✅ |
| `FILE_REFERENCE_EXPIRED` auto-refresh | ✅ |
| Sticker / GIF send | 🔜 |

### Contacts & Dialogs
| Feature | Status |
|---------|--------|
| Get dialogs (all chats / pagination) | ✅ |
| Get contacts | ✅ |
| Resolve username / peer | ✅ |
| Get full user info | ✅ |
| Get full chat / channel info | ✅ |

### Groups & Channels
| Feature | Status |
|---------|--------|
| Create basic group | ✅ |
| Create supergroup / channel | ✅ |
| Delete group / channel | ✅ |
| Upgrade basic group → supergroup | ✅ |
| Edit title / description | ✅ |
| Add users to group | ✅ |
| Invite users to channel | ✅ |
| Join / leave channel | ✅ |
| Get channel members | ✅ |
| Promote / demote admin | ✅ |
| Ban / unban / kick users | ✅ |
| Restrict / mute / read-only users | ✅ |
| Slow mode | ✅ |
| Export invite link | ✅ |
| Default member permissions | ✅ |
| Toggle signatures (channel) | ✅ |
| Toggle join-to-send | ✅ |
| Toggle join-approval | ✅ |
| Get channels where you are admin | ✅ |

### Account
| Feature | Status |
|---------|--------|
| Update profile (name, bio) | ✅ |
| Update username | ✅ |
| Upload profile photo | ✅ |
| Get active sessions (authorizations) | ✅ |
| Terminate session by hash | ✅ |
| Terminate all other sessions | ✅ |
| Get / set privacy rules | ✅ |

### Event Handling
| Feature | Status |
|---------|--------|
| `on(NewMessageFilter, callable)` — filtered handler | ✅ |
| `onUpdate(callable)` — raw update handler | ✅ |
| `runUntilDisconnected()` — event loop | ✅ |
| `pollOnce()` — single poll tick | ✅ |
| Filter by peer / keyword / media type | ✅ |

---

## Code Examples

### Download media from chat history

```php
$messages = $client->getHistory('@channel', 20);

foreach ($messages as $msg) {
    if (empty($msg['media'])) continue;

    $ext  = $client->getMediaExtension($msg['media']);
    $path = "downloads/file_{$msg['id']}.$ext";

    $client->downloadMedia($msg, $path, function (int $recv, int $total, int $pct) {
        echo "\r  $pct% — " . number_format($recv) . "/" . number_format($total) . " bytes";
    });

    echo "\nSaved: $path\n";
}
```

### Event-driven message listener

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

### Group / channel management

```php
// Create a supergroup
$group = $client->createChannel('My Group', 'A test group', megagroup: true);

// Invite users
$client->inviteToChannel($group['id'], ['@alice', '@bob']);

// Promote an admin
$client->promoteAdmin($group['id'], '@alice', canDeleteMessages: true, canBanUsers: true);

// Export invite link
$link = $client->exportInviteLink($group['id']);
echo $link['link'];
```

### SOCKS5 proxy

```php
$client->setProxy('127.0.0.1', 1080);          // without auth
$client->setProxy('127.0.0.1', 1080, 'user', 'pass');  // with auth
$client->connect();
```

### Bot login

```php
$client->getAuth()->loginAsBot('YOUR_BOT_TOKEN:HERE');
$me = $client->getMe();
echo "Bot: @" . $me['username'];
```

---

## Architecture

```
src/
├── Client/
│   ├── TelegramClient.php     # Main entry point — all high-level methods
│   ├── Auth.php               # Login, 2FA, bot auth, logout
│   ├── Messages.php           # Messaging, history, search, media send
│   ├── Account.php            # Profile, privacy, sessions
│   └── FileDownloader.php     # Chunked download, DC migration
│
├── Network/
│   ├── Connection.php         # Raw TCP socket (IPv4/IPv6)
│   ├── Socks5Connection.php   # SOCKS5 proxy tunnel
│   ├── TcpAbridged.php        # MTProto TCP Abridged framing
│   ├── MTProtoPlainSender.php # Unencrypted sender (auth handshake)
│   ├── MTProtoSender.php      # Encrypted sender (main session)
│   ├── Authenticator.php      # DH key exchange (Steps 1–9)
│   └── LayerDetector.php      # Auto-detect API layer at connect
│
├── Crypto/
│   ├── AES.php                # AES-IGE encryption/decryption
│   ├── RSA.php                # RSA (PKCS#1 v1.5) for DH handshake
│   ├── AuthKey.php            # Auth key container + key ID
│   └── SRP.php                # SRP-2048 for 2FA cloud password
│
├── TL/
│   ├── BinaryReader.php       # TL binary deserializer
│   ├── BinaryWriter.php       # TL binary serializer
│   ├── TLObject.php           # Base TL type
│   ├── Types/                 # TL type classes (User, Chat, Message …)
│   ├── Functions/             # TL method classes (SendMessage, GetHistory …)
│   └── Parser/
│       └── TLSkipHelper.php   # Skip/read arbitrary TL objects by constructor
│
├── Sessions/
│   ├── AbstractSession.php    # Session interface
│   ├── FileSession.php        # Persistent file-based session (.session)
│   └── MemorySession.php      # In-memory session (no persistence)
│
├── Upload/
│   └── FileUploader.php       # Chunked upload (small + big file mode)
│
├── Events/
│   ├── NewMessage.php         # Typed new-message event + filter builder
│   ├── NewMessageEvent.php    # Event wrapper
│   └── RawUpdateEvent.php     # Raw TL update wrapper
│
├── Helpers/
│   └── Helpers.php            # Utility functions
│
└── Exceptions/
    └── RPCException.php       # Telegram RPC error ($errorCode, $errorMessage)
```

---

## Session Files

Sessions are saved to `sessions/<phone>.session` by default (configurable). The file stores the auth key, server salt, DC info, and negotiated API layer — so subsequent runs connect instantly without re-authenticating.

```
sessions/
└── 628123456789.session    ← binary session file
```

---

## Documentation

Full Indonesian documentation (method signatures, examples, protocol notes): [`DOKUMENTASI.md`](DOKUMENTASI.md)

Topics covered:
- MTProto protocol internals
- All method signatures with parameters and return types
- Media upload & download (including DC migration, `FILE_REFERENCE_EXPIRED` handling)
- Event handling patterns
- Group/channel management cookbook
- Privacy settings reference
- Changelog & known Layer 214 constructor differences vs older layers

---

## Known Limitations

- No multi-account concurrency (one session per process)
- No MTProto v1 (old DC) support
- No CDN file download (redirected to CDN DC)
- No end-to-end encrypted (secret) chats
- No MTProto over WebSocket

---

## References

- [Telegram MTProto](https://core.telegram.org/mtproto)
- [Telegram API methods](https://core.telegram.org/methods)
- [TDLib schema](https://github.com/tdlib/td/blob/master/td/generate/scheme/telegram_api.tl)
- [Telethon](https://github.com/LonamiWebs/Telethon) — reference implementation used for protocol cross-checking

---

## License

MIT — see [LICENSE](LICENSE)
