<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;
use XnoxsProto\TL\Parser\ReplyMarkupParser;

/**
 * Full message object — parses reply_markup, from_id, peer_id.
 *
 * Supports both old and new message constructors:
 *   messageEmpty#76a6d327
 *   message#94345242   (layer ≤184, single flags:# word)
 *   message#9815cec8   (layer 185+, has flags:# AND flags2:#)
 *   messageService#2357bf25
 */
class FullMessage
{
    const CTR_EMPTY           = 0x76a6d327;
    const CTR_MESSAGE_NEW     = 0x9815cec8;  // layer 185+ with flags2
    const CTR_MESSAGE_OLD     = 0x94345242;  // layer ≤184, no flags2
    const CTR_SERVICE         = 0x2357bf25;  // old service (with flags2 extra word)
    const CTR_SERVICE_CURRENT = 0x7a800e0a;  // TL_messageService — current layer, no flags2

    const PEER_USER      = 0x59511722;
    const PEER_USER_ALT  = 0x9db1bc6d;
    const PEER_CHAT      = 0x36c6019a;
    const PEER_CHAT_ALT  = 0xbad0e5bb;
    const PEER_CHANNEL   = 0xa2a5371e;

    public int     $id            = 0;
    public int     $date          = 0;
    public string  $text          = '';
    public bool    $out           = false;
    public string  $type          = 'empty';

    public ?int    $fromUserId    = null;
    public ?int    $fromChatId    = null;
    public ?int    $fromChannelId = null;

    public string  $peerType      = 'user';  // 'user'|'chat'|'channel'
    public int     $peerId        = 0;

    public ?array  $replyMarkup   = null;
    public ?array  $media         = null;

    private mixed  $client        = null;
    public  mixed  $peerInputPeer = null;

    public static function fromReader(BinaryReader $reader, int $ctor): self
    {
        $obj = new self();

        switch ($ctor) {
            case self::CTR_MESSAGE_NEW:
                $obj->type = 'message';
                self::parseMessageNew($reader, $obj);
                break;

            case self::CTR_MESSAGE_OLD:
                $obj->type = 'message';
                self::parseMessageOld($reader, $obj);
                break;

            case self::CTR_SERVICE:
                $obj->type = 'service';
                self::parseService($reader, $obj);
                break;

            case self::CTR_SERVICE_CURRENT: // 0x7a800e0a — TL_messageService (current layer, no flags2)
                $obj->type = 'service';
                self::parseServiceCurrent($reader, $obj);
                break;

            case self::CTR_EMPTY:
            default:
                $obj->type = 'empty';
                $flags = $reader->readInt();
                $obj->id = $reader->readInt();
                if ($flags & (1 << 0)) self::skipPeer($reader); // peer_id:flags.0?Peer
                break;
        }

        return $obj;
    }

    // -------------------------------------------------------------------------
    // message#9815cec8  — layer 185+ (flags:# + flags2:#)
    // flags:#  out:f.1 mentioned:f.4 media_unread:f.5 silent:f.13 post:f.14
    //          from_scheduled:f.18 legacy:f.19 edit_hide:f.21 pinned:f.24
    //          noforwards:f.26 invert_media:f.27 flags2:f.31
    //          from_boosts_applied:f.29?int  saved_peer_id:f.28?Peer
    //          via_business_bot_id:f2.0?long  guestchat_via_from:f2.19?Peer
    //          quick_reply_shortcut_id:f.30?int  effect:f2.2?long
    //          factcheck:f2.3?FactCheck  paid_message_stars:f2.6?long
    // Verified against: TDesktop api.tl (message#95ef6f2b) + raw dump of message#9815cec8
    // Raw dump confirmed: saved_peer_id at flags.28 (not flags2.2)
    // -------------------------------------------------------------------------
    private static function parseMessageNew(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $flags2 = $r->readInt();

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        // from_id:flags.8?Peer
        if ($flags & (1 << 8)) {
            [$type, $id] = self::readPeer($r);
            if ($type === 'user')    $obj->fromUserId    = $id;
            elseif ($type === 'chat')    $obj->fromChatId    = $id;
            elseif ($type === 'channel') $obj->fromChannelId = $id;
        }

        // from_boosts_applied:flags.29?int  ← flags WORD 1, bit 29 (not flags2.0)
        if ($flags & (1 << 29)) $r->readInt();

        // from_rank:flags2.12?string  ← present in newer layers
        if ($flags2 & (1 << 12)) $r->readString();

        // peer_id:Peer — always present
        [$obj->peerType, $obj->peerId] = self::readPeer($r);

        // saved_peer_id:flags.28?Peer  ← flags WORD 1, bit 28 (not flags2.2)
        // Confirmed by raw dump: flags=0x10000000 (bit 28) triggers saved_peer_id
        if ($flags & (1 << 28)) self::skipPeer($r);

        // fwd_from:flags.2?MessageFwdHeader
        if ($flags & (1 << 2)) TLSkipHelper::skipMessageFwdHeader($r);

        // via_bot_id:flags.11?long  ← comes BEFORE reply_to
        if ($flags & (1 << 11)) $r->readLong();

        // via_business_bot_id:flags2.0?long  ← flags2 WORD, bit 0 (not flags2.6)
        if ($flags2 & (1 << 0)) $r->readLong();

        // guestchat_via_from:flags2.19?Peer  ← present in newer layers
        if ($flags2 & (1 << 19)) self::skipPeer($r);

        // reply_to:flags.3?MessageReplyHeader  ← comes AFTER via_bot_id/via_business_bot_id
        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);

        $obj->date = $r->readInt();    // date:int
        $obj->text = $r->readString(); // message:string

        // media:flags.9?MessageMedia — capture type info
        if ($flags & (1 << 9)) {
            $m = TLSkipHelper::readMessageMedia($r);
            $obj->media = ($m['type'] !== 'empty') ? $m : null;
        }

        // reply_markup:flags.6?ReplyMarkup — PARSE (not skip)
        if ($flags & (1 << 6)) {
            try {
                $obj->replyMarkup = ReplyMarkupParser::parse($r);
            } catch (\Exception $e) {
                $obj->replyMarkup = null;
            }
        }

        // entities:flags.7?Vector<MessageEntity>
        if ($flags & (1 << 7)) TLSkipHelper::skipVector($r, fn($x) => TLSkipHelper::skipMessageEntity($x));

        // views:flags.10?int  forwards:flags.10?int (both guarded by same flag)
        if ($flags & (1 << 10)) { $r->readInt(); $r->readInt(); }

        // replies:flags.23?MessageReplies
        if ($flags & (1 << 23)) TLSkipHelper::skipMessageReplies($r);

        // edit_date:flags.15?int
        if ($flags & (1 << 15)) $r->readInt();

        // post_author:flags.16?string
        if ($flags & (1 << 16)) $r->readString();

        // grouped_id:flags.17?long
        if ($flags & (1 << 17)) $r->readLong();

        // reactions:flags.20?MessageReactions
        if ($flags & (1 << 20)) TLSkipHelper::skipMessageReactions($r);

        // restriction_reason:flags.22?Vector<RestrictionReason>
        if ($flags & (1 << 22)) TLSkipHelper::skipVector($r, fn($x) => TLSkipHelper::skipRestrictionReason($x));

        // ttl_period:flags.25?int
        if ($flags & (1 << 25)) $r->readInt();

        // quick_reply_shortcut_id:flags.30?int  ← flags WORD 1, bit 30 (not flags2.1)
        if ($flags & (1 << 30)) $r->readInt();

        // effect:flags2.2?long  ← flags2 bit 2 (not flags2.3)
        if ($flags2 & (1 << 2)) $r->readLong();

        // factcheck:flags2.3?FactCheck  ← flags2 bit 3 (not flags2.4)
        if ($flags2 & (1 << 3)) TLSkipHelper::skipFactCheck($r);

        // report_delivery_until_date:flags2.5?int
        if ($flags2 & (1 << 5)) $r->readInt();

        // paid_message_stars:flags2.6?long
        if ($flags2 & (1 << 6)) $r->readLong();

        // suggested_post:flags2.7?SuggestedPost
        if ($flags2 & (1 << 7)) TLSkipHelper::skipSuggestedPost($r);

        // schedule_repeat_period:flags2.10?int
        if ($flags2 & (1 << 10)) $r->readInt();

        // summary_from_language:flags2.11?string
        if ($flags2 & (1 << 11)) $r->readString();
    }

    // -------------------------------------------------------------------------
    // message#94345242 — layer ≤184 (only flags:#, no flags2)
    // Same field set but without from_boosts_applied, saved_peer_id, etc.
    // -------------------------------------------------------------------------
    private static function parseMessageOld(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt();  // only one flags word

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        if ($flags & (1 << 8)) {
            [$type, $id] = self::readPeer($r);
            if ($type === 'user')    $obj->fromUserId    = $id;
            elseif ($type === 'chat')    $obj->fromChatId    = $id;
            elseif ($type === 'channel') $obj->fromChannelId = $id;
        }

        [$obj->peerType, $obj->peerId] = self::readPeer($r);

        if ($flags & (1 << 2)) TLSkipHelper::skipMessageFwdHeader($r);
        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);
        if ($flags & (1 << 11)) $r->readLong(); // via_bot_id

        $obj->date = $r->readInt();
        $obj->text = $r->readString();

        if ($flags & (1 << 9)) {
            $m = TLSkipHelper::readMessageMedia($r);
            $obj->media = ($m['type'] !== 'empty') ? $m : null;
        }

        if ($flags & (1 << 6)) {
            try {
                $obj->replyMarkup = ReplyMarkupParser::parse($r);
            } catch (\Exception $e) {
                $obj->replyMarkup = null;
            }
        }

        if ($flags & (1 << 7)) TLSkipHelper::skipVector($r, fn($x) => TLSkipHelper::skipMessageEntity($x));
        if ($flags & (1 << 10)) { $r->readInt(); $r->readInt(); }
        if ($flags & (1 << 23)) TLSkipHelper::skipMessageReplies($r);
        if ($flags & (1 << 15)) $r->readInt();
        if ($flags & (1 << 16)) $r->readString();
        if ($flags & (1 << 17)) $r->readLong();
        if ($flags & (1 << 20)) TLSkipHelper::skipMessageReactions($r);
        if ($flags & (1 << 22)) TLSkipHelper::skipVector($r, fn($x) => TLSkipHelper::skipRestrictionReason($x));
        if ($flags & (1 << 25)) $r->readInt();
    }

    // -------------------------------------------------------------------------
    // messageService#2357bf25 (old — has extra flags2 word before id)
    // -------------------------------------------------------------------------
    private static function parseService(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $r->readInt(); // flags2 extra word present in old constructor

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        if ($flags & (1 << 8)) self::skipPeer($r); // from_id

        [$obj->peerType, $obj->peerId] = self::readPeer($r);

        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);

        $obj->date = $r->readInt();
        TLSkipHelper::skipMessageAction($r);

        if ($flags & (1 << 25)) $r->readInt(); // ttl_period

        $obj->text = '[Service Message]';
    }

    // -------------------------------------------------------------------------
    // TL_messageService#7a800e0a (current layer — no flags2 word)
    // flags:#  out:f.1  mentioned:f.4  silent:f.13  post:f.14  legacy:f.19
    // id:int  from_id:f.8?Peer  peer_id:Peer  saved_peer_id:f.28?Peer
    // reply_to:f.3?MessageReplyHeader  date:int  action:MessageAction
    // reactions:f.20?TL_messageReactions  ttl_period:f.25?int
    // Source: TLRPC.java TL_messageService::readParams
    // -------------------------------------------------------------------------
    private static function parseServiceCurrent(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt();

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        if ($flags & (1 << 8))  self::skipPeer($r);                               // from_id
        [$obj->peerType, $obj->peerId] = self::readPeer($r);                      // peer_id
        if ($flags & (1 << 28)) self::skipPeer($r);                               // saved_peer_id
        if ($flags & (1 << 3))  TLSkipHelper::skipMessageReplyHeader($r);         // reply_to

        $obj->date = $r->readInt();
        TLSkipHelper::skipMessageAction($r);                                       // action

        if ($flags & (1 << 20)) TLSkipHelper::skipMessageReactions($r);           // reactions
        if ($flags & (1 << 25)) $r->readInt();                                    // ttl_period

        $obj->text = '[Service Message]';
    }

    private static function readPeer(BinaryReader $r): array
    {
        $ctor = $r->readInt();
        $id   = $r->readLong();
        return match ($ctor) {
            self::PEER_USER, self::PEER_USER_ALT    => ['user',    $id],
            self::PEER_CHAT, self::PEER_CHAT_ALT    => ['chat',    $id],
            self::PEER_CHANNEL                       => ['channel', $id],
            default                                  => ['user',    $id],
        };
    }

    private static function skipPeer(BinaryReader $r): void
    {
        $r->readInt();  // ctor
        $r->readLong(); // id
    }

    // =========================================================================
    // Telethon-like API
    // =========================================================================

    public function setClient(mixed $client, mixed $peerInputPeer = null): void
    {
        $this->client        = $client;
        $this->peerInputPeer = $peerInputPeer;
    }

    /**
     * Klik tombol inline keyboard.
     *
     * Cara pemakaian:
     *   $msg->click(0, 0)          — posisi baris 0, kolom 0 (lama)
     *   $msg->click('📖 Bantuan')  — cari tombol berdasarkan teks label (exact)
     *   $msg->click('Bantuan')     — cari tombol yang mengandung teks ini (case-insensitive)
     *
     * Jika ditemukan lebih dari satu cocok parsial, tombol pertama yang ditemukan diklik.
     */
    public function click(int|string $row = 0, int $col = 0): ?array
    {
        if ($this->client === null) {
            throw new \RuntimeException('No client attached — call setClient() first or use event handler');
        }
        if ($this->replyMarkup === null || empty($this->replyMarkup['rows'])) {
            throw new \RuntimeException('Message has no reply markup / inline keyboard');
        }

        $rows   = $this->replyMarkup['rows'];
        $button = null;

        if (is_string($row)) {
            // Cari tombol berdasarkan teks label
            $needle = $row;

            // 1. Coba exact match dulu
            foreach ($rows as $r) {
                foreach ($r as $btn) {
                    if (($btn['text'] ?? '') === $needle) {
                        $button = $btn;
                        break 2;
                    }
                }
            }

            // 2. Fallback: partial/case-insensitive match
            if ($button === null) {
                $needleLower = mb_strtolower($needle);
                foreach ($rows as $r) {
                    foreach ($r as $btn) {
                        if (mb_strpos(mb_strtolower($btn['text'] ?? ''), $needleLower) !== false) {
                            $button = $btn;
                            break 2;
                        }
                    }
                }
            }

            if ($button === null) {
                throw new \RuntimeException("Tombol dengan teks \"{$needle}\" tidak ditemukan");
            }
        } else {
            // Mode lama: posisi angka row, col
            if (!isset($rows[$row][$col])) {
                throw new \RuntimeException("Tidak ada tombol di row=$row col=$col");
            }
            $button = $rows[$row][$col];
        }

        $data   = $button['data'] ?? null;
        $isGame = $button['type'] === 'game';

        $peer = $this->peerInputPeer ?? $this->client->resolvePeerFromMessage($this);

        return $this->client->clickButton($peer, $this->id, $data, $isGame);
    }

    /**
     * Get button URL at [$row][$col] without clicking.
     */
    public function getButtonUrl(int $row = 0, int $col = 0): ?string
    {
        return $this->replyMarkup['rows'][$row][$col]['url'] ?? null;
    }

    /**
     * Get button text at [$row][$col].
     */
    public function getButtonText(int $row = 0, int $col = 0): ?string
    {
        return $this->replyMarkup['rows'][$row][$col]['text'] ?? null;
    }
}
