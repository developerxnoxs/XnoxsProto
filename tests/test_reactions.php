<?php
/**
 * Standalone test — Reaction support
 *
 * Tes:
 *   1. MessagesSendReactionRequest serialization (emoji / remove / custom_emoji)
 *   2. TLSkipHelper::parseMessageReactions  — binary decode reactions
 *   3. TLSkipHelper::skipMessageReactions   — konsisteni dengan parse
 *   4. Visual: cetakBubble dengan reaksi
 *
 * Jalankan: php tests/test_reactions.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Parser\TLSkipHelper;
use XnoxsProto\TL\Types\InputPeer;
use XnoxsProto\TL\Functions\MessagesSendReactionRequest;

// ─── Helpers ────────────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;

function ok(string $msg): void  { global $pass; $pass++; echo "  \033[32m✓\033[0m $msg\n"; }
function fail(string $msg, string $got = '', string $exp = ''): void {
    global $fail; $fail++;
    echo "  \033[31m✗\033[0m $msg";
    if ($got !== '' || $exp !== '') echo " (got=$got, exp=$exp)";
    echo "\n";
}

/** Pack uint32 little-endian */
function p32(int $v): string { return pack('V', $v); }

/** TL-encode a string: [len:byte] [data] [padding to 4-byte] */
function tlStr(string $s): string {
    $len = strlen($s);
    assert($len < 254, "Use long-string format for len>=254");
    $raw = chr($len) . $s;
    $pad = (4 - (strlen($raw) % 4)) % 4;
    return $raw . str_repeat("\x00", $pad);
}

// ─── Test 1: MessagesSendReactionRequest — kirim emoji ──────────────────────
echo "\n\033[1mTEST 1: MessagesSendReactionRequest — kirim emoji\033[0m\n";
{
    $peer = InputPeer::user(12345, 99999);
    $req  = new MessagesSendReactionRequest(
        $peer, 42,
        [['type' => 'emoji', 'emoticon' => '👍']],
        false, true
    );
    $bytes = $req->toBytes();

    // Bytes 0-3: constructor = 0xd30d78d4
    $ctor  = unpack('V', substr($bytes, 0, 4))[1];
    $ctor  === 0xd30d78d4
        ? ok("constructor = 0xd30d78d4")
        : fail("constructor", sprintf('0x%08x', $ctor), '0xd30d78d4');

    // Bytes 4-7: flags — bit0 (reaction vector) + bit2 (add_to_recent)
    $flags = unpack('V', substr($bytes, 4, 4))[1];
    ($flags & 0x01) ? ok("flags.0 (reaction vector) set")       : fail("flags.0 not set");
    ($flags & 0x04) ? ok("flags.2 (add_to_recent) set")         : fail("flags.2 not set");
    !($flags & 0x02) ? ok("flags.1 (big) tidak set")            : fail("flags.1 harus 0");

    // Harus ada data (total > 40 bytes)
    strlen($bytes) > 40
        ? ok("serialized size = " . strlen($bytes) . " bytes")
        : fail("serialized size terlalu kecil: " . strlen($bytes));
}

// ─── Test 2: MessagesSendReactionRequest — hapus reaksi ─────────────────────
echo "\n\033[1mTEST 2: MessagesSendReactionRequest — hapus reaksi ([] kosong)\033[0m\n";
{
    $peer = InputPeer::user(12345, 99999);
    $req  = new MessagesSendReactionRequest($peer, 42, []);
    $bytes = $req->toBytes();

    $flags = unpack('V', substr($bytes, 4, 4))[1];
    ($flags & 0x01) ? ok("flags.0 (reaction vector) tetap set")  : fail("flags.0 harus tetap set");

    // Cari vector ctor (0x1cb5c415) setelah InputPeer
    // InputPeer::user serializes sebagai: ctor(4) + user_id(8) + access_hash(8) = 20 bytes
    // jadi vector dimulai setelah offset 4(ctor)+4(flags)+20(peer)+4(msg_id) = 32
    $vecStart = 32;
    if (strlen($bytes) >= $vecStart + 8) {
        $vecCtor  = unpack('V', substr($bytes, $vecStart, 4))[1];
        $vecCount = unpack('V', substr($bytes, $vecStart + 4, 4))[1];
        $vecCtor  === 0x1cb5c415
            ? ok("vector constructor benar")
            : fail("vector ctor", sprintf('0x%08x', $vecCtor), '0x1cb5c415');
        $vecCount === 0
            ? ok("vector count = 0 (hapus reaksi)")
            : fail("vector count", (string)$vecCount, '0');
    } else {
        fail("bytes terlalu pendek untuk baca vector");
    }
}

// ─── Test 3: parseMessageReactions — decode binary ──────────────────────────
echo "\n\033[1mTEST 3: TLSkipHelper::parseMessageReactions — decode binary\033[0m\n";
{
    $thumbsUp = "\xF0\x9F\x91\x8D"; // 👍 (4 bytes UTF-8)
    $heart    = "\xE2\x9D\xA4\xEF\xB8\x8F"; // ❤️ (6 bytes UTF-8)

    // Bangun binary MessageReactions
    $blob  = p32(0x4f2b9479);  // MessageReactions ctor
    $blob .= p32(0x00000000);  // flags = 0 (no min, no can_see_list, no recent_reactions)
    $blob .= p32(0x1cb5c415);  // vector ctor
    $blob .= p32(2);           // count = 2

    // ReactionCount#1: 👍, count=3, chosen (flags.0 set → chosen_order present)
    $blob .= p32(0xa3d1cb80);  // ReactionCount ctor
    $blob .= p32(0x00000001);  // flags = 1 → chosen_order ada
    $blob .= p32(0);           // chosen_order = 0
    $blob .= p32(0x1b2286b8);  // reactionEmoji ctor
    $blob .= tlStr($thumbsUp); // emoticon = "👍"
    $blob .= p32(3);           // count = 3

    // ReactionCount#2: ❤️, count=5, not chosen
    $blob .= p32(0xa3d1cb80);  // ReactionCount ctor
    $blob .= p32(0x00000000);  // flags = 0
    $blob .= p32(0x1b2286b8);  // reactionEmoji ctor
    $blob .= tlStr($heart);    // emoticon = "❤️"
    $blob .= p32(5);           // count = 5

    $reader    = new BinaryReader($blob);
    $reactions = TLSkipHelper::parseMessageReactions($reader);

    count($reactions) === 2
        ? ok("parsed 2 reactions")
        : fail("jumlah reactions", (string)count($reactions), '2');

    $r0 = $reactions[0] ?? [];
    $r0['emoji'] === $thumbsUp  ? ok("reactions[0].emoji = 👍")       : fail("reactions[0].emoji", bin2hex($r0['emoji'] ?? ''), bin2hex($thumbsUp));
    ($r0['count'] ?? 0) === 3   ? ok("reactions[0].count = 3")        : fail("reactions[0].count", (string)($r0['count'] ?? '?'), '3');
    !empty($r0['chosen'])       ? ok("reactions[0].chosen = true")    : fail("reactions[0].chosen harus true");

    $r1 = $reactions[1] ?? [];
    $r1['emoji'] === $heart     ? ok("reactions[1].emoji = ❤️")       : fail("reactions[1].emoji", bin2hex($r1['emoji'] ?? ''), bin2hex($heart));
    ($r1['count'] ?? 0) === 5   ? ok("reactions[1].count = 5")        : fail("reactions[1].count", (string)($r1['count'] ?? '?'), '5');
    empty($r1['chosen'])        ? ok("reactions[1].chosen = false")   : fail("reactions[1].chosen harus false");
}

// ─── Test 4: parseMessageReactions dengan recent_reactions ──────────────────
echo "\n\033[1mTEST 4: parseMessageReactions — dengan recent_reactions (flags.1)\033[0m\n";
{
    $fire = "\xF0\x9F\x94\xA5"; // 🔥

    $blob  = p32(0x4f2b9479);  // MessageReactions ctor
    $blob .= p32(0x00000002);  // flags.1 = recent_reactions ada
    $blob .= p32(0x1cb5c415);  // vector ctor (results)
    $blob .= p32(1);           // 1 result

    // ReactionCount: 🔥 ×7
    $blob .= p32(0xa3d1cb80);
    $blob .= p32(0x00000000);  // not chosen
    $blob .= p32(0x1b2286b8);  // reactionEmoji
    $blob .= tlStr($fire);
    $blob .= p32(7);

    // recent_reactions:Vector<MessagePeerReaction> — 1 item
    $blob .= p32(0x1cb5c415);  // vector ctor
    $blob .= p32(1);           // count = 1
    // MessagePeerReaction#b156fe9c  flags:# peer_id:Peer date:int reaction:Reaction
    $blob .= p32(0xb156fe9c);  // ctor
    $blob .= p32(0x00000004);  // flags (my=true → bit2)
    // peer_id: peerUser#59511722 id:long
    $blob .= p32(0x59511722);  // peerUser ctor
    $blob .= pack('P', 99999); // user id (8 bytes)
    $blob .= p32(1748000000);  // date
    // reaction: reactionEmoji
    $blob .= p32(0x1b2286b8);
    $blob .= tlStr($fire);

    $reader    = new BinaryReader($blob);
    $reactions = TLSkipHelper::parseMessageReactions($reader);

    count($reactions) === 1
        ? ok("parsed 1 result (recent_reactions di-skip dengan benar)")
        : fail("jumlah reactions", (string)count($reactions), '1');

    ($reactions[0]['emoji'] ?? '') === $fire
        ? ok("emoji = 🔥")
        : fail("emoji salah");

    ($reactions[0]['count'] ?? 0) === 7
        ? ok("count = 7")
        : fail("count", (string)($reactions[0]['count'] ?? '?'), '7');
}

// ─── Test 5: skipMessageReactions = wrapper parseMessageReactions ────────────
echo "\n\033[1mTEST 5: skipMessageReactions — tidak crash, konsumsi bytes benar\033[0m\n";
{
    $emoji = "\xF0\x9F\x91\x8E"; // 👎

    $blob  = p32(0x4f2b9479);
    $blob .= p32(0x00000000);
    $blob .= p32(0x1cb5c415);
    $blob .= p32(1);
    $blob .= p32(0xa3d1cb80);
    $blob .= p32(0x00000000);
    $blob .= p32(0x1b2286b8);
    $blob .= tlStr($emoji);
    $blob .= p32(1);
    // Tambahkan sentinel 4 bytes setelah blob
    $sentinel = 0xdeadbeef;
    $blob .= p32($sentinel);

    $reader = new BinaryReader($blob);
    TLSkipHelper::skipMessageReactions($reader);
    $after = unpack('V', $reader->read(4))[1];
    ($after & 0xffffffff) === ($sentinel & 0xffffffff)
        ? ok("sentinel benar — skip mengkonsumsi bytes yang tepat")
        : fail("sentinel", sprintf('0x%08x', $after), sprintf('0x%08x', $sentinel));
}

// ─── Test 6: sendReaction toDict ────────────────────────────────────────────
echo "\n\033[1mTEST 6: MessagesSendReactionRequest::toDict\033[0m\n";
{
    $peer = InputPeer::user(777, 888);
    $req  = new MessagesSendReactionRequest(
        $peer, 99,
        [['type' => 'emoji', 'emoticon' => '🔥']],
        true, false
    );
    $dict = $req->toDict();
    $dict['_'] === 'messages.sendReaction'
        ? ok("_ = messages.sendReaction")
        : fail("_", $dict['_'], 'messages.sendReaction');
    $dict['msg_id'] === 99
        ? ok("msg_id = 99")
        : fail("msg_id", (string)$dict['msg_id'], '99');
    isset($dict['reactions'])
        ? ok("reactions key ada")
        : fail("reactions key tidak ada");
}

// ─── Ringkasan ───────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 50) . "\n";
echo "Total: \033[32m$pass lulus\033[0m";
if ($fail > 0) {
    echo ", \033[31m$fail gagal\033[0m";
} else {
    echo " — \033[32;1m✅ Semua tes lulus!\033[0m";
}
echo "\n\n";

exit($fail > 0 ? 1 : 0);
