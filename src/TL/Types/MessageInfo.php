<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;

/**
 * Parsed top message untuk keperluan getDialogs.
 *
 * Constructor IDs (tdlib telegram_api.tl):
 *   messageEmpty#76a6d327     flags:# id:int peer_id:f.0?Peer
 *   message#9815cec8          flags:# flags2:# id:int from_id:f.8?Peer ...  (layer 185+)
 *   message#94345242          flags:# id:int from_id:f.8?Peer ...            (layer ≤184, no flags2)
 *   messageService#2357bf25   flags:# flags2:# id:int ...                    (old, has extra flags2 word)
 *   messageService#7a800e0a   flags:# id:int ...                             (current layer, NO flags2)
 *
 * Kita ekstrak id, date, text, out, type — semua field lain di-skip.
 *
 * PENTING: Setiap konstruktor punya urutan field berbeda — lihat komentar per metode.
 */
class MessageInfo
{
    const CONSTRUCTOR_EMPTY            = 0x76a6d327;
    const CONSTRUCTOR_MESSAGE          = 0x9815cec8; // layer 185+, has flags2
    const CONSTRUCTOR_MESSAGE_OLD      = 0x94345242; // legacy, NO flags2
    const CONSTRUCTOR_SERVICE_OLD      = 0x2357bf25; // old service (has extra flags2 word)
    const CONSTRUCTOR_SERVICE_CURRENT  = 0x7a800e0a; // current layer service, NO flags2

    const PEER_USER     = 0x59511722;
    const PEER_USER_OLD = 0x9db1bc6d;
    const PEER_CHAT     = 0x36c6019a;
    const PEER_CHAT_OLD = 0xbad0e5bb;
    const PEER_CHANNEL  = 0xa2a5371e;

    public int    $id           = 0;
    public int    $date         = 0;
    public string $text         = '';
    public bool   $out          = false;
    public string $type         = 'empty';
    public ?int   $fromUserId   = null;

    public static function fromReader(BinaryReader $reader, int $constructor): self
    {
        $obj = new self();

        switch ($constructor) {
            case self::CONSTRUCTOR_EMPTY:
                $obj->type = 'empty';
                $flags = $reader->readInt();
                $obj->id = $reader->readInt();
                if ($flags & (1 << 0)) self::skipPeer($reader); // peer_id:f.0?Peer
                break;

            case self::CONSTRUCTOR_MESSAGE:
                $obj->type = 'message';
                self::parseMessageNew($reader, $obj);
                break;

            case self::CONSTRUCTOR_MESSAGE_OLD:
                $obj->type = 'message';
                self::parseMessageOld($reader, $obj);
                break;

            case self::CONSTRUCTOR_SERVICE_OLD:
                $obj->type = 'service';
                self::parseServiceOld($reader, $obj);
                break;

            case self::CONSTRUCTOR_SERVICE_CURRENT: // 0x7a800e0a — current layer, NO flags2
                $obj->type = 'service';
                self::parseServiceCurrent($reader, $obj);
                break;

            default:
                // Unknown constructor — throw so caller can decide how to handle
                throw new \RuntimeException(
                    sprintf('Unknown message constructor: 0x%08x', $constructor)
                );
        }

        return $obj;
    }

    // -------------------------------------------------------------------------
    // message#9815cec8 — layer 185+ (flags:# + flags2:#)
    // Verified against: FullMessage::parseMessageNew + TLRPC.java raw dump
    //
    // Field order (critical — must match wire format exactly):
    //   flags + flags2
    //   out:f.1  id  from_id:f.8?Peer
    //   from_boosts_applied:f.29?int  from_rank:f2.12?string
    //   peer_id:Peer  saved_peer_id:f.28?Peer
    //   fwd_from:f.2?  via_bot_id:f.11?long  via_business_bot_id:f2.0?long
    //   guestchat_via_from:f2.19?Peer  reply_to:f.3?
    //   date  message(text)  media:f.9?  reply_markup:f.6?  entities:f.7?
    //   views+forwards:f.10?  replies:f.23?  edit_date:f.15?  post_author:f.16?
    //   grouped_id:f.17?  reactions:f.20?  restriction_reason:f.22?
    //   ttl_period:f.25?  quick_reply_shortcut_id:f.30?
    //   effect:f2.2?  factcheck:f2.3?  report_delivery_until_date:f2.5?
    //   paid_message_stars:f2.6?  suggested_post:f2.7?
    //   schedule_repeat_period:f2.10?  summary_from_language:f2.11?
    // -------------------------------------------------------------------------
    private static function parseMessageNew(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $flags2 = $r->readInt();

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        // from_id:flags.8?Peer
        if ($flags & (1 << 8)) {
            $peerCtor = $r->readInt();
            $peerId   = $r->readLong();
            if ($peerCtor === self::PEER_USER || $peerCtor === self::PEER_USER_OLD) {
                $obj->fromUserId = $peerId;
            }
        }

        // from_boosts_applied:flags.29?int  ← flags WORD 1 bit 29 (NOT flags2.0)
        if ($flags & (1 << 29)) $r->readInt();

        // from_rank:flags2.12?string
        if ($flags2 & (1 << 12)) $r->readString();

        // peer_id:Peer — selalu ada
        self::skipPeer($r);

        // saved_peer_id:flags.28?Peer  ← flags WORD 1 bit 28 (NOT flags2.2)
        if ($flags & (1 << 28)) self::skipPeer($r);

        // fwd_from:flags.2?MessageFwdHeader
        if ($flags & (1 << 2)) TLSkipHelper::skipMessageFwdHeader($r);

        // via_bot_id:flags.11?long  ← comes BEFORE reply_to
        if ($flags & (1 << 11)) $r->readLong();

        // via_business_bot_id:flags2.0?long  ← flags2 bit 0 (NOT flags2.6)
        if ($flags2 & (1 << 0)) $r->readLong();

        // guestchat_via_from:flags2.19?Peer
        if ($flags2 & (1 << 19)) self::skipPeer($r);

        // reply_to:flags.3?MessageReplyHeader  ← AFTER via_bot_id/via_business_bot_id
        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);

        $obj->date = $r->readInt();    // date
        $obj->text = $r->readString(); // message

        // media:flags.9?MessageMedia
        if ($flags & (1 << 9)) TLSkipHelper::skipMessageMedia($r);

        // reply_markup:flags.6?ReplyMarkup
        if ($flags & (1 << 6)) TLSkipHelper::skipReplyMarkup($r);

        // entities:flags.7?Vector<MessageEntity>
        if ($flags & (1 << 7)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipMessageEntity($x)
        );

        // views:flags.10?int + forwards:flags.10?int (same flag guards both)
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
        if ($flags & (1 << 22)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipRestrictionReason($x)
        );

        // ttl_period:flags.25?int
        if ($flags & (1 << 25)) $r->readInt();

        // quick_reply_shortcut_id:flags.30?int  ← flags WORD 1 bit 30 (NOT flags2.1)
        if ($flags & (1 << 30)) $r->readInt();

        // effect:flags2.2?long  ← flags2 bit 2 (NOT flags2.3)
        if ($flags2 & (1 << 2)) $r->readLong();

        // factcheck:flags2.3?FactCheck  ← flags2 bit 3 (NOT flags2.4)
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
    // message#94345242 — layer ≤184 (flags:# SAJA, tidak ada flags2)
    // -------------------------------------------------------------------------
    private static function parseMessageOld(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt(); // SATU flags word saja

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        // from_id:flags.8?Peer
        if ($flags & (1 << 8)) {
            $peerCtor = $r->readInt();
            $peerId   = $r->readLong();
            if ($peerCtor === self::PEER_USER || $peerCtor === self::PEER_USER_OLD) {
                $obj->fromUserId = $peerId;
            }
        }

        // peer_id:Peer — selalu ada
        self::skipPeer($r);

        // fwd_from:flags.2?MessageFwdHeader
        if ($flags & (1 << 2)) TLSkipHelper::skipMessageFwdHeader($r);

        // reply_to:flags.3?MessageReplyHeader
        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);

        // via_bot_id:flags.11?long
        if ($flags & (1 << 11)) $r->readLong();

        $obj->date = $r->readInt();    // date
        $obj->text = $r->readString(); // message

        // media:flags.9?MessageMedia
        if ($flags & (1 << 9)) TLSkipHelper::skipMessageMedia($r);

        // reply_markup:flags.6?ReplyMarkup
        if ($flags & (1 << 6)) TLSkipHelper::skipReplyMarkup($r);

        // entities:flags.7?Vector<MessageEntity>
        if ($flags & (1 << 7)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipMessageEntity($x)
        );

        // views:flags.10?int + forwards:flags.10?int
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
        if ($flags & (1 << 22)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipRestrictionReason($x)
        );

        // ttl_period:flags.25?int
        if ($flags & (1 << 25)) $r->readInt();
    }

    // -------------------------------------------------------------------------
    // messageService#2357bf25 (old — has EXTRA flags2 word before id)
    // flags:# flags2:# id:int from_id:f.8?Peer peer_id:Peer
    // reply_to:f.3?MessageReplyHeader date:int action:MessageAction
    // ttl_period:f.25?int
    // -------------------------------------------------------------------------
    private static function parseServiceOld(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt();
        $r->readInt(); // flags2 — extra word present in OLD constructor only

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        // from_id:flags.8?Peer
        if ($flags & (1 << 8)) self::skipPeer($r);

        // peer_id:Peer — selalu ada
        self::skipPeer($r);

        // reply_to:flags.3?MessageReplyHeader
        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);

        $obj->date = $r->readInt();
        TLSkipHelper::skipMessageAction($r); // action — selalu ada

        // ttl_period:flags.25?int
        if ($flags & (1 << 25)) $r->readInt();

        $obj->text = '[Service Message]';
    }

    // -------------------------------------------------------------------------
    // messageService#7a800e0a — current layer (NO flags2 word)
    // flags:#  out:f.1  id:int  from_id:f.8?Peer  peer_id:Peer
    // saved_peer_id:f.28?Peer  reply_to:f.3?MessageReplyHeader
    // date:int  action:MessageAction
    // reactions:f.20?MessageReactions  ttl_period:f.25?int
    // Source: TLRPC.java TL_messageService::readParams + TDLib telegram_api.tl
    // -------------------------------------------------------------------------
    private static function parseServiceCurrent(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt();

        $obj->out = (bool)($flags & (1 << 1));
        $obj->id  = $r->readInt();

        // from_id:flags.8?Peer
        if ($flags & (1 << 8)) self::skipPeer($r);

        // peer_id:Peer — selalu ada
        self::skipPeer($r);

        // saved_peer_id:flags.28?Peer
        if ($flags & (1 << 28)) self::skipPeer($r);

        // reply_to:flags.3?MessageReplyHeader
        if ($flags & (1 << 3)) TLSkipHelper::skipMessageReplyHeader($r);

        $obj->date = $r->readInt();
        TLSkipHelper::skipMessageAction($r); // action — selalu ada

        // reactions:flags.20?MessageReactions
        if ($flags & (1 << 20)) TLSkipHelper::skipMessageReactions($r);

        // ttl_period:flags.25?int
        if ($flags & (1 << 25)) $r->readInt();

        $obj->text = '[Service Message]';
    }

    // -------------------------------------------------------------------------
    // Helper: skip Peer (ctor:int + id:long)
    // -------------------------------------------------------------------------
    public static function skipPeer(BinaryReader $r): void
    {
        $r->readInt();  // ctor
        $r->readLong(); // id
    }

    public function getPreview(int $maxLen = 60): string
    {
        if ($this->text === '') return '';
        return mb_strlen($this->text) > $maxLen
            ? mb_substr($this->text, 0, $maxLen) . '…'
            : $this->text;
    }
}
