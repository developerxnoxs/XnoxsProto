# XnoxsProto - Telegram MTProto Library for PHP

✅ **STATUS: PRODUCTION READY - Real Login Working!** ✅

A PHP implementation of Telegram's MTProto protocol.

## 🎉 Current Status

**Real Telegram login is fully functional!** This library successfully:
- ✅ Connects to Telegram servers using MTProto protocol
- ✅ Generates authentication keys via full DH key exchange
- ✅ Sends verification codes to phone numbers
- ✅ Completes login flow and retrieves user information
- ✅ Auto-migrates between datacenters
- ✅ Handles all MTProto service messages

## 🚀 Quick Start

### Installation

```bash
composer install
```

### Interactive Login

```bash
php interactive_login.php
```

You'll need:
1. API ID and API Hash from https://my.telegram.org/apps
2. Your phone number (with country code, e.g., +628123456789)
3. Verification code (sent via SMS or Telegram app)

### Example Code

```php
<?php
require_once 'vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;

// Your API credentials from https://my.telegram.org/apps
$apiId = YOUR_API_ID;
$apiHash = 'YOUR_API_HASH';

$session = new FileSession('my_session.json');
$client = new TelegramClient($apiId, $apiHash, $session);

// Connect to Telegram
$client->connect();

// Send verification code
$sentCode = $client->getAuth()->sendCode('+628123456789');

// Get code from user (SMS or Telegram app)
$code = '12345';

// Sign in
$user = $client->getAuth()->signIn(
    '+628123456789',
    $sentCode['phone_code_hash'],
    $code
);

echo "Logged in as: " . $user['user']['first_name'] . "\n";
echo "User ID: " . $user['user']['id'] . "\n";
```

## 📦 What's Implemented

### ✅ Core MTProto Protocol
- **TCP Abridged Transport** - Telegram's efficient transport layer
- **Authentication** - Full DH key exchange (Steps 1-9)
- **AES-IGE Encryption** - MTProto encryption/decryption
- **RSA Encryption** - Using Telegram's public keys
- **Message Serialization** - TL (Type Language) binary format

### ✅ Service Message Handling
- `bad_server_salt` - Auto update salt and retry
- `msg_container` - Parse multiple messages in one packet
- `new_session_created` - Extract and update server salt
- `rpc_result` - Response wrapper parsing
- `rpc_error` - Error handling with typed exceptions

### ✅ Authentication & Login
- `auth.sendCode` - Send verification code to phone
- `auth.signIn` - Complete login with verification code
- `invokeWithLayer` - API layer wrapper
- `initConnection` - Client initialization
- Auto DC migration - Automatically switches to correct datacenter

### ✅ Session Management
- File-based sessions - Persistent auth keys
- Memory sessions - Temporary sessions
- DC information storage

## 🏗️ Architecture

Follows Telethon's clean modular design:

```
src/
├── Client/          # High-level API
│   ├── TelegramClient.php
│   └── Auth.php
├── Crypto/          # Cryptographic primitives
│   ├── AES.php
│   ├── RSA.php
│   └── AuthKey.php
├── TL/              # Type Language serialization
│   ├── BinaryReader.php
│   ├── BinaryWriter.php
│   ├── Types/       # TL type classes
│   └── Functions/   # TL function classes
├── Network/         # Network layer
│   ├── Connection.php
│   ├── TcpAbridged.php
│   ├── MTProtoPlainSender.php
│   ├── MTProtoSender.php
│   └── Authenticator.php
├── Sessions/        # Session persistence
│   ├── FileSession.php
│   └── MemorySession.php
├── Exceptions/      # Custom exceptions
│   └── RPCException.php
└── Helpers/         # Utility functions
    └── Helpers.php
```

## 🔜 Next Development Steps

To add more functionality:

1. **More API Methods** - Implement additional Telegram API methods
   - `messages.sendMessage` - Send text messages
   - `messages.getHistory` - Get chat history
   - `users.getFullUser` - Get user information
   - See full API: https://core.telegram.org/methods

2. **Update Handling** - Listen for incoming updates
3. **File Operations** - Upload/download files
4. **Multi-account** - Support multiple sessions

## 📚 Documentation

For detailed technical documentation, see [`replit.md`](replit.md).

## 🔧 Requirements

- PHP >= 8.2
- Extensions:
  - ext-openssl (cryptography)
  - ext-gmp (big integer math)
  - ext-mbstring (string handling)
  - ext-json (JSON support)

## 🙏 Credits

- MTProto protocol by Telegram
- Pollard's rho-Brent factorization algorithm

## 📄 License

MIT License

## 🔗 References

- Telegram MTProto: https://core.telegram.org/mtproto
- API Methods: https://core.telegram.org/methods

---

**XnoxsProto - Professional PHP MTProto Implementation**
