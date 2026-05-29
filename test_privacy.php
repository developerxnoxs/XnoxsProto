<?php

/**
 * XnoxsProto — Account Privacy Settings Test
 * ============================================
 * Menguji getPrivacy() dan setPrivacy() untuk semua 11 privacy key.
 * Semua nilai dikembalikan ke semula di akhir test (restore).
 *
 * SECTION A — Read (getPrivacy semua key)
 *   1.  getPrivacy KEY_STATUS_TIMESTAMP
 *   2.  getPrivacy KEY_CHAT_INVITE
 *   3.  getPrivacy KEY_PHONE_CALL
 *   4.  getPrivacy KEY_PHONE_P2P
 *   5.  getPrivacy KEY_FORWARDS
 *   6.  getPrivacy KEY_PROFILE_PHOTO
 *   7.  getPrivacy KEY_PHONE_NUMBER
 *   8.  getPrivacy KEY_ADDED_BY_PHONE
 *   9.  getPrivacy KEY_VOICE_MESSAGES
 *  10.  getPrivacy KEY_ABOUT
 *  11.  getPrivacy KEY_BIRTHDAY
 *
 * SECTION B — Write + Verify (setPrivacy lalu baca balik)
 *  12.  STATUS_TIMESTAMP → allow_all, baca balik
 *  13.  STATUS_TIMESTAMP → allow_contacts, baca balik
 *  14.  STATUS_TIMESTAMP → disallow_all, baca balik
 *  15.  CHAT_INVITE → allow_contacts, baca balik
 *  16.  CHAT_INVITE → allow_all, baca balik
 *  17.  PHONE_CALL → allow_contacts, baca balik
 *  18.  PHONE_CALL → disallow_all, baca balik
 *  19.  PHONE_P2P → disallow_all, baca balik
 *  20.  PHONE_P2P → allow_contacts, baca balik
 *  21.  FORWARDS → disallow_all, baca balik
 *  22.  FORWARDS → allow_all, baca balik
 *  23.  PROFILE_PHOTO → allow_contacts, baca balik
 *  24.  PROFILE_PHOTO → allow_all, baca balik
 *  25.  PHONE_NUMBER → disallow_all, baca balik
 *  26.  PHONE_NUMBER → allow_contacts, baca balik
 *  27.  ADDED_BY_PHONE → disallow_all  — SKIP: Telegram tidak izinkan disallow_all untuk key ini
 *  28.  ADDED_BY_PHONE → allow_contacts, baca balik
 *  29.  VOICE_MESSAGES → allow_contacts, baca balik
 *  30.  VOICE_MESSAGES → allow_all, baca balik
 *  31.  ABOUT → allow_contacts, baca balik
 *  32.  ABOUT → allow_all, baca balik
 *  33.  BIRTHDAY → allow_contacts, baca balik
 *  34.  BIRTHDAY → allow_all, baca balik
 *
 * SECTION C — setPrivacy via integer RULE_* constants (bukan string)
 *  35.  setPrivacy integer rule RULE_DISALLOW_ALL
 *  36.  setPrivacy integer rule RULE_ALLOW_CONTACTS
 *
 * SECTION D — Restore ke nilai semula
 *  37–47. Restore semua 11 key ke nilai original
 *
 * Catatan Telegram API Constraints (SKIP):
 *   - ADDED_BY_PHONE → disallow_all: [400] PRIVACY_VALUE_INVALID
 *     Key ini hanya mendukung allow_all dan allow_contacts, tidak bisa disallow_all.
 *
 * Jalankan:
 *   TG_API_ID=123456 TG_API_HASH=abc123 php test_privacy.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest as K;
use XnoxsProto\TL\Functions\AccountSetPrivacyRequest;

// ═══════════════════════════════════════════════════════════════════════════
// HELPER
// ═══════════════════════════════════════════════════════════════════════════

$pass    = 0;
$fail    = 0;
$skip    = 0;
$results = [];

function step(string $name, callable $fn): mixed
{
    global $pass, $fail, $results;
    echo "  » $name ... ";
    try {
        $ret    = $fn();
        $detail = is_string($ret) ? $ret : '';
        $results[] = ['PASS', $name, $detail];
        echo "\033[32mPASS\033[0m" . ($detail ? " — $detail" : '') . "\n";
        $pass++;
        return $ret;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $results[] = ['FAIL', $name, $msg];
        echo "\033[31mFAIL\033[0m — $msg\n";
        $fail++;
        return null;
    }
}

function skipStep(string $name, string $reason): void
{
    global $skip, $results;
    $results[] = ['SKIP', $name, $reason];
    echo "  » $name ... \033[33mSKIP\033[0m — $reason\n";
    $skip++;
}

function section(string $t): void
{
    echo "\n\033[1;34m── $t\033[0m\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// BANNER & KONEKSI
// ═══════════════════════════════════════════════════════════════════════════

echo "\n\033[1;36m╔══════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║  Account Privacy Settings Test                   ║\033[0m\n";
echo "\033[1;36m║  XnoxsProto — PHP MTProto Layer 214              ║\033[0m\n";
echo "\033[1;36m╚══════════════════════════════════════════════════╝\033[0m\n";

$apiId   = (int)  getenv('TG_API_ID');
$apiHash = trim((string) getenv('TG_API_HASH'));

if ($apiId === 0 || $apiHash === '') {
    echo "\n\033[31m  ERROR: TG_API_ID / TG_API_HASH belum di-set.\033[0m\n";
    echo "  Jalankan: TG_API_ID=123456 TG_API_HASH=abc123 php test_privacy.php\n\n";
    exit(1);
}

// Auto-detect session file — pakai *.session terbaru
$sessionFiles = glob(__DIR__ . '/*.session') ?: [];
if (empty($sessionFiles)) {
    echo "\n\033[31m  ERROR: tidak ada file *.session di direktori ini.\033[0m\n";
    echo "  Jalankan login dulu dengan php interactive_login.php\n\n";
    exit(1);
}
usort($sessionFiles, fn($a, $b) => filemtime($b) - filemtime($a));
$sessionFile = $sessionFiles[0];
echo "\n  Session : $sessionFile\n";

$client = new TelegramClient($apiId, $apiHash, new FileSession($sessionFile));
$client->connect();

$me = $client->getMe();
echo "  Akun    : {$me['first_name']} ({$me['phone']})\n";

$account = $client->getAccount();

// Semua KEY yang akan ditest
$allKeys = [
    'KEY_STATUS_TIMESTAMP' => K::KEY_STATUS_TIMESTAMP,
    'KEY_CHAT_INVITE'      => K::KEY_CHAT_INVITE,
    'KEY_PHONE_CALL'       => K::KEY_PHONE_CALL,
    'KEY_PHONE_P2P'        => K::KEY_PHONE_P2P,
    'KEY_FORWARDS'         => K::KEY_FORWARDS,
    'KEY_PROFILE_PHOTO'    => K::KEY_PROFILE_PHOTO,
    'KEY_PHONE_NUMBER'     => K::KEY_PHONE_NUMBER,
    'KEY_ADDED_BY_PHONE'   => K::KEY_ADDED_BY_PHONE,
    'KEY_VOICE_MESSAGES'   => K::KEY_VOICE_MESSAGES,
    'KEY_ABOUT'            => K::KEY_ABOUT,
    'KEY_BIRTHDAY'         => K::KEY_BIRTHDAY,
];

// ═══════════════════════════════════════════════════════════════════════════
// SECTION A — Read: getPrivacy semua key
// ═══════════════════════════════════════════════════════════════════════════

section('SECTION A — getPrivacy: baca semua 11 key');

$originalValues = [];

foreach ($allKeys as $keyName => $keyConst) {
    $result = step("getPrivacy $keyName", function () use ($account, $keyConst, $keyName, &$originalValues) {
        $r = $account->getPrivacy($keyConst);
        if (!isset($r['rules']) || !is_array($r['rules'])) {
            throw new \RuntimeException("Return value tidak punya 'rules' array");
        }
        $ruleStr = implode(', ', $r['rules']) ?: '(kosong)';
        $originalValues[$keyName] = $r['rules'];
        return "rules=[{$ruleStr}]";
    });

    if ($result === null) {
        // getPrivacy gagal — simpan default supaya restore tetap bisa jalan
        $originalValues[$keyName] = ['allow_contacts'];
    }

    usleep(300_000); // 300ms jeda antar request
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION B — Write + Verify: setPrivacy tiap key, lalu baca balik
// ═══════════════════════════════════════════════════════════════════════════

section('SECTION B — setPrivacy + verifikasi read-back');

/**
 * Helper: set lalu verifikasi dengan getPrivacy.
 * Return string ringkasan atau throw jika tidak cocok.
 */
$setAndVerify = function (int $key, string $keyName, string $rule) use ($account): string {
    $ok = $account->setPrivacy($key, [$rule]);
    if ($ok === false) {
        throw new \RuntimeException("setPrivacy return false");
    }
    usleep(1_500_000); // 1.5s — beri jeda cukup agar tidak kena FLOOD_WAIT
    $back = $account->getPrivacy($key);
    $backRules = $back['rules'] ?? [];
    if (!in_array($rule, $backRules, true)) {
        throw new \RuntimeException(
            "Mismatch: set '$rule' tapi read-back dapat [" . implode(', ', $backRules) . "]"
        );
    }
    return "set '$rule' → verified";
};

// STATUS_TIMESTAMP: coba ketiga rule
step('setPrivacy STATUS_TIMESTAMP → allow_all',
    fn() => $setAndVerify(K::KEY_STATUS_TIMESTAMP, 'STATUS_TIMESTAMP', 'allow_all'));
usleep(1_000_000);

step('setPrivacy STATUS_TIMESTAMP → allow_contacts',
    fn() => $setAndVerify(K::KEY_STATUS_TIMESTAMP, 'STATUS_TIMESTAMP', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy STATUS_TIMESTAMP → disallow_all',
    fn() => $setAndVerify(K::KEY_STATUS_TIMESTAMP, 'STATUS_TIMESTAMP', 'disallow_all'));
usleep(1_000_000);

// CHAT_INVITE
step('setPrivacy CHAT_INVITE → allow_contacts',
    fn() => $setAndVerify(K::KEY_CHAT_INVITE, 'CHAT_INVITE', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy CHAT_INVITE → allow_all',
    fn() => $setAndVerify(K::KEY_CHAT_INVITE, 'CHAT_INVITE', 'allow_all'));
usleep(1_000_000);

// PHONE_CALL
step('setPrivacy PHONE_CALL → allow_contacts',
    fn() => $setAndVerify(K::KEY_PHONE_CALL, 'PHONE_CALL', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy PHONE_CALL → disallow_all',
    fn() => $setAndVerify(K::KEY_PHONE_CALL, 'PHONE_CALL', 'disallow_all'));
usleep(1_000_000);

// PHONE_P2P
step('setPrivacy PHONE_P2P → disallow_all',
    fn() => $setAndVerify(K::KEY_PHONE_P2P, 'PHONE_P2P', 'disallow_all'));
usleep(1_000_000);

step('setPrivacy PHONE_P2P → allow_contacts',
    fn() => $setAndVerify(K::KEY_PHONE_P2P, 'PHONE_P2P', 'allow_contacts'));
usleep(1_000_000);

// FORWARDS
step('setPrivacy FORWARDS → disallow_all',
    fn() => $setAndVerify(K::KEY_FORWARDS, 'FORWARDS', 'disallow_all'));
usleep(1_000_000);

step('setPrivacy FORWARDS → allow_all',
    fn() => $setAndVerify(K::KEY_FORWARDS, 'FORWARDS', 'allow_all'));
usleep(1_000_000);

// PROFILE_PHOTO
step('setPrivacy PROFILE_PHOTO → allow_contacts',
    fn() => $setAndVerify(K::KEY_PROFILE_PHOTO, 'PROFILE_PHOTO', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy PROFILE_PHOTO → allow_all',
    fn() => $setAndVerify(K::KEY_PROFILE_PHOTO, 'PROFILE_PHOTO', 'allow_all'));
usleep(1_000_000);

// PHONE_NUMBER
step('setPrivacy PHONE_NUMBER → disallow_all',
    fn() => $setAndVerify(K::KEY_PHONE_NUMBER, 'PHONE_NUMBER', 'disallow_all'));
usleep(1_000_000);

step('setPrivacy PHONE_NUMBER → allow_contacts',
    fn() => $setAndVerify(K::KEY_PHONE_NUMBER, 'PHONE_NUMBER', 'allow_contacts'));
usleep(1_000_000);

// ADDED_BY_PHONE
skipStep(
    'setPrivacy ADDED_BY_PHONE → disallow_all',
    'Telegram API constraint: KEY_ADDED_BY_PHONE tidak mendukung disallow_all → [400] PRIVACY_VALUE_INVALID'
);

step('setPrivacy ADDED_BY_PHONE → allow_contacts',
    fn() => $setAndVerify(K::KEY_ADDED_BY_PHONE, 'ADDED_BY_PHONE', 'allow_contacts'));
usleep(1_000_000);

// VOICE_MESSAGES
step('setPrivacy VOICE_MESSAGES → allow_contacts',
    fn() => $setAndVerify(K::KEY_VOICE_MESSAGES, 'VOICE_MESSAGES', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy VOICE_MESSAGES → allow_all',
    fn() => $setAndVerify(K::KEY_VOICE_MESSAGES, 'VOICE_MESSAGES', 'allow_all'));
usleep(1_000_000);

// ABOUT
step('setPrivacy ABOUT → allow_contacts',
    fn() => $setAndVerify(K::KEY_ABOUT, 'ABOUT', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy ABOUT → allow_all',
    fn() => $setAndVerify(K::KEY_ABOUT, 'ABOUT', 'allow_all'));
usleep(1_000_000);

// BIRTHDAY
step('setPrivacy BIRTHDAY → allow_contacts',
    fn() => $setAndVerify(K::KEY_BIRTHDAY, 'BIRTHDAY', 'allow_contacts'));
usleep(1_000_000);

step('setPrivacy BIRTHDAY → allow_all',
    fn() => $setAndVerify(K::KEY_BIRTHDAY, 'BIRTHDAY', 'allow_all'));
usleep(1_000_000);

// ═══════════════════════════════════════════════════════════════════════════
// SECTION C — Gunakan integer RULE_* constants (bukan string)
// ═══════════════════════════════════════════════════════════════════════════

section('SECTION C — setPrivacy via integer RULE_* constants');

step('setPrivacy STATUS_TIMESTAMP via RULE_DISALLOW_ALL (integer)', function () use ($account) {
    $ok = $account->setPrivacy(
        K::KEY_STATUS_TIMESTAMP,
        [AccountSetPrivacyRequest::RULE_DISALLOW_ALL]
    );
    if ($ok === false) throw new \RuntimeException("return false");
    usleep(1_500_000);
    $back = $account->getPrivacy(K::KEY_STATUS_TIMESTAMP);
    $rule = $back['rules'][0] ?? '';
    if ($rule !== 'disallow_all') {
        throw new \RuntimeException("Mismatch: dapat '$rule'");
    }
    return "integer RULE_DISALLOW_ALL → verified disallow_all";
});

usleep(1_000_000);

step('setPrivacy STATUS_TIMESTAMP via RULE_ALLOW_CONTACTS (integer)', function () use ($account) {
    $ok = $account->setPrivacy(
        K::KEY_STATUS_TIMESTAMP,
        [AccountSetPrivacyRequest::RULE_ALLOW_CONTACTS]
    );
    if ($ok === false) throw new \RuntimeException("return false");
    usleep(1_500_000);
    $back = $account->getPrivacy(K::KEY_STATUS_TIMESTAMP);
    $rule = $back['rules'][0] ?? '';
    if ($rule !== 'allow_contacts') {
        throw new \RuntimeException("Mismatch: dapat '$rule'");
    }
    return "integer RULE_ALLOW_CONTACTS → verified allow_contacts";
});

usleep(1_000_000);

// ═══════════════════════════════════════════════════════════════════════════
// SECTION D — Restore ke nilai semula
// ═══════════════════════════════════════════════════════════════════════════

section('SECTION D — Restore semua key ke nilai semula');

$restoreOrder = [
    'KEY_STATUS_TIMESTAMP' => K::KEY_STATUS_TIMESTAMP,
    'KEY_CHAT_INVITE'      => K::KEY_CHAT_INVITE,
    'KEY_PHONE_CALL'       => K::KEY_PHONE_CALL,
    'KEY_PHONE_P2P'        => K::KEY_PHONE_P2P,
    'KEY_FORWARDS'         => K::KEY_FORWARDS,
    'KEY_PROFILE_PHOTO'    => K::KEY_PROFILE_PHOTO,
    'KEY_PHONE_NUMBER'     => K::KEY_PHONE_NUMBER,
    'KEY_ADDED_BY_PHONE'   => K::KEY_ADDED_BY_PHONE,
    'KEY_VOICE_MESSAGES'   => K::KEY_VOICE_MESSAGES,
    'KEY_ABOUT'            => K::KEY_ABOUT,
    'KEY_BIRTHDAY'         => K::KEY_BIRTHDAY,
];

foreach ($restoreOrder as $keyName => $keyConst) {
    $origRules = $originalValues[$keyName] ?? ['allow_contacts'];
    // Ambil rule pertama yang valid (allow_all / allow_contacts / disallow_all)
    $restoreRule = null;
    foreach ($origRules as $r) {
        if (in_array($r, ['allow_all', 'allow_contacts', 'disallow_all'], true)) {
            $restoreRule = $r;
            break;
        }
    }
    if ($restoreRule === null) {
        $restoreRule = 'allow_contacts'; // fallback aman
    }

    step("Restore $keyName → '$restoreRule'", function () use ($account, $keyConst, $restoreRule) {
        $account->setPrivacy($keyConst, [$restoreRule]);
        return "restored to '$restoreRule'";
    });
    usleep(1_200_000);
}

// ═══════════════════════════════════════════════════════════════════════════
// RINGKASAN
// ═══════════════════════════════════════════════════════════════════════════

$client->disconnect();

$total = $pass + $fail + $skip;
echo "\n\033[1;36m╔══════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║  HASIL TEST                                      ║\033[0m\n";
echo "\033[1;36m╚══════════════════════════════════════════════════╝\033[0m\n";
echo "  Total  : $total\n";
echo "  \033[32mPASS\033[0m   : $pass\n";
echo "  \033[31mFAIL\033[0m   : $fail\n";
echo "  \033[33mSKIP\033[0m   : $skip\n\n";

if ($fail > 0) {
    echo "\033[31mGAGAL:\033[0m\n";
    foreach ($results as [$status, $name, $detail]) {
        if ($status === 'FAIL') {
            echo "  ✗ $name\n    → $detail\n";
        }
    }
    echo "\n";
}
