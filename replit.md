# XnoxsProto — PHP MTProto Library for Telegram

## Project Overview

A pure-PHP implementation of Telegram's MTProto protocol. Communicates directly with real Telegram servers — no wrapper around any other library.

**Namespace:** `XnoxsProto`  
**Entry point:** `src/Client/TelegramClient.php`  
**Requires:** PHP 8.2+, GMP extension, OpenSSL extension, BCMath extension  
**API Layer:** 214  

---

## Quick Start

```php
require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$client = TelegramClient::create(YOUR_API_ID, 'YOUR_API_HASH');
$client->start('+6281234567890');

$me = $client->getMe();
echo "Logged in as: " . $me['first_name'];

$client->sendMessage('@username', 'Hello from XnoxsProto!');
$client->disconnect();
```

API credentials from: https://my.telegram.org/apps  
Full documentation: see `DOKUMENTASI.md`

---

## Dependencies

No third-party Composer packages — pure PHP implementation.

```json
{
    "require": { "php": ">=8.2" },
    "ext": ["gmp", "openssl", "bcmath"]
}
```

---

## User Preferences

- Documentation is written in `DOKUMENTASI.md` (Indonesian)
