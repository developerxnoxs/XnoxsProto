<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;

/**
 * Chat types (official TDLib telegram_api.tl):
 *
 * chatEmpty#29562865        id:long
 * chat#1c207ca0             flags:# flags2:# id:long title:string photo:ChatPhoto
 *                           participants_count:int date:int version:int
 *                           migrated_to:f.6?InputChannel
 *                           admin_rights:f.14?ChatAdminRights
 *                           default_banned_rights:f.18?ChatBannedRights
 * chatForbidden#a9eca0ab    id:long title:string
 *
 * channel#1c32b11c (current — Layer 214+)
 *   flags:# flags2:#
 *   id:long access_hash:flags.13?long title:string username:flags.6?string
 *   photo:ChatPhoto date:int restriction_reason:flags.9?Vector<RR>
 *   admin_rights:flags.14?ChatAdminRights banned_rights:flags.15?ChatBannedRights
 *   default_banned_rights:flags.18?ChatBannedRights participants_count:flags.17?int
 *   usernames:flags2.0?Vector<Username> stories_max_id:flags2.4?RecentStory
 *   color:flags2.7?PeerColor profile_color:flags2.8?PeerColor
 *   emoji_status:flags2.9?EmojiStatus level:flags2.10?int
 *   subscription_until_date:flags2.11?int bot_verification_icon:flags2.13?long
 *   send_paid_messages_stars:flags2.14?long linked_monoforum_id:flags2.18?long
 *
 * channel#aadfc8f (legacy — flags:# flags2:# flags3:#)
 *
 * channelForbidden#17d493d5 flags:# broadcast:f.5?true megagroup:f.8?true
 *                           id:long access_hash:long title:string
 *                           until_date:f.2?int
 */
class Chat
{
    const CONSTRUCTOR_EMPTY              = 0x29562865;
    const CONSTRUCTOR_CHAT               = 0x1c207ca0;  // chat with flags2
    const CONSTRUCTOR_CHAT_LAYER123      = 0x41cbf256;  // chat without flags2 (server sends this)
    const CONSTRUCTOR_FORBIDDEN          = 0xa9eca0ab;
    const CONSTRUCTOR_CHANNEL_LAYER216   = 0xfe685355;  // TL_channel_layer216 — server sends this
    const CONSTRUCTOR_CHANNEL            = 0x1c32b11c;  // TDLib master / newer layers
    const CONSTRUCTOR_CHANNEL_LEGACY     = 0x0aadfc8f;  // old schema (3 flag words)
    const CONSTRUCTOR_CHANNEL_FORBIDDEN  = 0x17d493d5;

    /** @var string 'chat'|'channel'|'empty'|'forbidden' */
    public string  $type       = 'chat';
    public int     $id         = 0;
    public ?int    $accessHash = null;
    public string  $title      = '';
    public ?string $username   = null;
    public bool    $broadcast  = false;  // channel broadcast
    public bool    $megagroup  = false;  // supergroup
    public bool    $verified   = false;
    public bool    $creator    = false;
    public bool    $left       = false;
    public int     $participantsCount = 0;
    public int     $date       = 0;

    /**
     * Parse dari BinaryReader. Constructor SUDAH dibaca oleh pemanggil.
     */
    public static function fromReader(BinaryReader $reader, int $constructor): self
    {
        $obj = new self();
        $obj->id = 0;

        switch ($constructor) {
            case self::CONSTRUCTOR_EMPTY:
                $obj->type = 'empty';
                $obj->id   = $reader->readLong();
                break;

            case self::CONSTRUCTOR_FORBIDDEN:
                $obj->type  = 'forbidden';
                $obj->id    = $reader->readLong();
                $obj->title = $reader->readString();
                break;

            case self::CONSTRUCTOR_CHAT:
                $obj->type = 'chat';
                self::parseChat($reader, $obj);
                break;

            case self::CONSTRUCTOR_CHAT_LAYER123:  // 0x41cbf256 — same as CHAT but no flags2 word
                $obj->type = 'chat';
                self::parseChatLayer123($reader, $obj);
                break;

            case self::CONSTRUCTOR_CHANNEL_LAYER216: // 0xfe685355 — TL_channel_layer216 (actual server)
                $obj->type = 'channel';
                self::parseChannelLayer216($reader, $obj);
                break;

            case self::CONSTRUCTOR_CHANNEL:          // 0x1c32b11c — TDLib master / newer
                $obj->type = 'channel';
                self::parseChannel($reader, $obj);
                break;

            case self::CONSTRUCTOR_CHANNEL_LEGACY:   // 0x0aadfc8f — old schema (3 flag words)
                $obj->type = 'channel';
                self::parseChannelLegacy($reader, $obj);
                break;

            case self::CONSTRUCTOR_CHANNEL_FORBIDDEN:
                $obj->type = 'channel';
                self::parseChannelForbidden($reader, $obj);
                break;

            default:
                $obj->type = 'unknown';
                break;
        }

        return $obj;
    }

    // -------------------------------------------------------------------------
    // chat#1c207ca0 — modern format (flags + flags2)
    // -------------------------------------------------------------------------
    private static function parseChat(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $flags2 = $r->readInt();

        $obj->creator = (bool)($flags & (1 << 0));
        $obj->left    = (bool)($flags & (1 << 2));

        $obj->id    = $r->readLong();
        $obj->title = $r->readString();

        // photo:ChatPhoto — selalu ada
        TLSkipHelper::skipChatPhoto($r);

        $obj->participantsCount = $r->readInt(); // participants_count
        $obj->date    = $r->readInt();            // date
        $r->readInt();                            // version

        // migrated_to:flags.6?InputChannel
        if ($flags & (1 << 6)) TLSkipHelper::skipInputChannel($r);

        // admin_rights:flags.14?ChatAdminRights
        if ($flags & (1 << 14)) TLSkipHelper::skipChatAdminRights($r);

        // default_banned_rights:flags.18?ChatBannedRights
        if ($flags & (1 << 18)) TLSkipHelper::skipChatBannedRights($r);
    }

    // -------------------------------------------------------------------------
    // chat#41cbf256 (TL_chat_layer123) — same as 1c207ca0 but NO flags2 word
    // Server sends this constructor for basic groups as of current layer 214
    // -------------------------------------------------------------------------
    private static function parseChatLayer123(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt(); // only ONE flags word

        $obj->creator = (bool)($flags & (1 << 0));
        $obj->left    = (bool)($flags & (1 << 2));

        $obj->id    = $r->readLong();
        $obj->title = $r->readString();

        TLSkipHelper::skipChatPhoto($r);

        $obj->participantsCount = $r->readInt();
        $obj->date = $r->readInt();
        $r->readInt(); // version

        if ($flags & (1 << 6))  TLSkipHelper::skipInputChannel($r);
        if ($flags & (1 << 14)) TLSkipHelper::skipChatAdminRights($r);
        if ($flags & (1 << 18)) TLSkipHelper::skipChatBannedRights($r);
    }

    // -------------------------------------------------------------------------
    // channel#fe685355  TL_channel_layer216 — what the server actually sends
    // flags:# flags2:#  (2 flag words)
    // DIFFERENCE vs 0x1c32b11c: stories_max_id (flags2.4) = plain int32
    // Source: TLRPC.java TL_channel_layer216::readParams
    // -------------------------------------------------------------------------
    private static function parseChannelLayer216(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $flags2 = $r->readInt();

        $obj->creator   = (bool)($flags & (1 << 0));
        $obj->left      = (bool)($flags & (1 << 2));
        $obj->broadcast = (bool)($flags & (1 << 5));
        $obj->verified  = (bool)($flags & (1 << 7));
        $obj->megagroup = (bool)($flags & (1 << 8));

        $obj->id = $r->readLong();

        // access_hash:flags.13?long
        if ($flags & (1 << 13)) $obj->accessHash = $r->readLong();

        $obj->title = $r->readString();

        // username:flags.6?string
        if ($flags & (1 << 6)) $obj->username = $r->readString();

        // photo:ChatPhoto — always present
        TLSkipHelper::skipChatPhoto($r);

        $obj->date = $r->readInt();

        // restriction_reason:flags.9?Vector<RestrictionReason>
        if ($flags & (1 << 9)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipRestrictionReason($x)
        );

        // admin_rights:flags.14?ChatAdminRights
        if ($flags & (1 << 14)) TLSkipHelper::skipChatAdminRights($r);

        // banned_rights:flags.15?ChatBannedRights
        if ($flags & (1 << 15)) TLSkipHelper::skipChatBannedRights($r);

        // default_banned_rights:flags.18?ChatBannedRights
        if ($flags & (1 << 18)) TLSkipHelper::skipChatBannedRights($r);

        // participants_count:flags.17?int
        if ($flags & (1 << 17)) $obj->participantsCount = $r->readInt();

        // --- flags2 fields ---
        // usernames:flags2.0?Vector<Username>
        if ($flags2 & (1 << 0)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipUsername($x)
        );
        // stories_max_id:flags2.4?int  ← plain int32 in layer216 (NOT RecentStory struct)
        if ($flags2 & (1 << 4)) $r->readInt();
        // color:flags2.7?PeerColor
        if ($flags2 & (1 << 7)) TLSkipHelper::skipPeerColor($r);
        // profile_color:flags2.8?PeerColor
        if ($flags2 & (1 << 8)) TLSkipHelper::skipPeerColor($r);
        // emoji_status:flags2.9?EmojiStatus
        if ($flags2 & (1 << 9)) TLSkipHelper::skipEmojiStatus($r);
        // level:flags2.10?int
        if ($flags2 & (1 << 10)) $r->readInt();
        // subscription_until_date:flags2.11?int
        if ($flags2 & (1 << 11)) $r->readInt();
        // bot_verification_icon:flags2.13?long
        if ($flags2 & (1 << 13)) $r->readLong();
        // send_paid_messages_stars:flags2.14?long
        if ($flags2 & (1 << 14)) $r->readLong();
        // linked_monoforum_id:flags2.18?long
        if ($flags2 & (1 << 18)) $r->readLong();
    }

    // -------------------------------------------------------------------------
    // channel#1c32b11c  (TDLib master / newer layers)
    // flags:# flags2:#  (2 flag words only)
    // stories_max_id:flags2.4?RecentStory  (struct with constructor)
    // -------------------------------------------------------------------------
    private static function parseChannel(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $flags2 = $r->readInt();

        $obj->creator   = (bool)($flags & (1 << 0));
        $obj->left      = (bool)($flags & (1 << 2));
        $obj->broadcast = (bool)($flags & (1 << 5));
        $obj->verified  = (bool)($flags & (1 << 7));
        $obj->megagroup = (bool)($flags & (1 << 8));

        $obj->id = $r->readLong();

        // access_hash:flags.13?long
        if ($flags & (1 << 13)) $obj->accessHash = $r->readLong();

        $obj->title = $r->readString();

        // username:flags.6?string
        if ($flags & (1 << 6)) $obj->username = $r->readString();

        // photo:ChatPhoto — always present
        TLSkipHelper::skipChatPhoto($r);

        $obj->date = $r->readInt();

        // restriction_reason:flags.9?Vector<RestrictionReason>
        if ($flags & (1 << 9)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipRestrictionReason($x)
        );

        // admin_rights:flags.14?ChatAdminRights
        if ($flags & (1 << 14)) TLSkipHelper::skipChatAdminRights($r);

        // banned_rights:flags.15?ChatBannedRights
        if ($flags & (1 << 15)) TLSkipHelper::skipChatBannedRights($r);

        // default_banned_rights:flags.18?ChatBannedRights
        if ($flags & (1 << 18)) TLSkipHelper::skipChatBannedRights($r);

        // participants_count:flags.17?int
        if ($flags & (1 << 17)) $obj->participantsCount = $r->readInt();

        // --- flags2 fields ---
        // usernames:flags2.0?Vector<Username>
        if ($flags2 & (1 << 0)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipUsername($x)
        );
        // stories_max_id:flags2.4?RecentStory
        // recentStory#711d692d flags:# live:flags.0?true max_id:flags.1?int
        if ($flags2 & (1 << 4)) TLSkipHelper::skipRecentStory($r);
        // color:flags2.7?PeerColor
        if ($flags2 & (1 << 7)) TLSkipHelper::skipPeerColor($r);
        // profile_color:flags2.8?PeerColor
        if ($flags2 & (1 << 8)) TLSkipHelper::skipPeerColor($r);
        // emoji_status:flags2.9?EmojiStatus
        if ($flags2 & (1 << 9)) TLSkipHelper::skipEmojiStatus($r);
        // level:flags2.10?int
        if ($flags2 & (1 << 10)) $r->readInt();
        // subscription_until_date:flags2.11?int
        if ($flags2 & (1 << 11)) $r->readInt();
        // bot_verification_icon:flags2.13?long
        if ($flags2 & (1 << 13)) $r->readLong();
        // send_paid_messages_stars:flags2.14?long
        if ($flags2 & (1 << 14)) $r->readLong();
        // linked_monoforum_id:flags2.18?long
        if ($flags2 & (1 << 18)) $r->readLong();
    }

    // -------------------------------------------------------------------------
    // channel#aadfc8f  (legacy — old schema with 3 flag words)
    // -------------------------------------------------------------------------
    private static function parseChannelLegacy(BinaryReader $r, self $obj): void
    {
        $flags  = $r->readInt();
        $flags2 = $r->readInt();
        $flags3 = $r->readInt();

        $obj->creator   = (bool)($flags & (1 << 0));
        $obj->left      = (bool)($flags & (1 << 2));
        $obj->broadcast = (bool)($flags & (1 << 5));
        $obj->verified  = (bool)($flags & (1 << 7));
        $obj->megagroup = (bool)($flags & (1 << 8));

        $obj->id = $r->readLong();

        if ($flags & (1 << 13)) $obj->accessHash = $r->readLong();

        $obj->title = $r->readString();

        if ($flags & (1 << 6)) $obj->username = $r->readString();

        TLSkipHelper::skipChatPhoto($r);

        $obj->date = $r->readInt();

        if ($flags & (1 << 9)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipRestrictionReason($x)
        );
        if ($flags & (1 << 14)) TLSkipHelper::skipChatAdminRights($r);
        if ($flags & (1 << 15)) TLSkipHelper::skipChatBannedRights($r);
        if ($flags & (1 << 18)) TLSkipHelper::skipChatBannedRights($r);
        if ($flags & (1 << 17)) $obj->participantsCount = $r->readInt();

        if ($flags2 & (1 << 0)) TLSkipHelper::skipVector(
            $r, fn($x) => TLSkipHelper::skipUsername($x)
        );
        if ($flags2 & (1 << 4)) $r->readInt(); // stories_max_id was int in legacy
        if ($flags2 & (1 << 7)) TLSkipHelper::skipPeerColor($r);
        if ($flags2 & (1 << 8)) TLSkipHelper::skipPeerColor($r);
        if ($flags2 & (1 << 9)) TLSkipHelper::skipEmojiStatus($r);
        if ($flags2 & (1 << 10)) $r->readInt();
        if ($flags2 & (1 << 11)) $r->readInt();
        if ($flags2 & (1 << 13)) $r->readLong();
        if ($flags2 & (1 << 14)) $r->readLong();
    }

    // -------------------------------------------------------------------------
    // channelForbidden#17d493d5
    // flags:# broadcast:f.5?true megagroup:f.8?true
    // id:long access_hash:long title:string until_date:f.2?int
    // -------------------------------------------------------------------------
    private static function parseChannelForbidden(BinaryReader $r, self $obj): void
    {
        $flags = $r->readInt();

        $obj->broadcast = (bool)($flags & (1 << 5));
        $obj->megagroup = (bool)($flags & (1 << 8));

        $obj->id         = $r->readLong();
        $obj->accessHash = $r->readLong();
        $obj->title      = $r->readString();

        // until_date:flags.2?int
        if ($flags & (1 << 2)) $r->readInt();
    }

    public function getDisplayName(): string
    {
        if ($this->title !== '') return $this->title;
        if ($this->username) return '@' . $this->username;
        return 'Chat#' . $this->id;
    }

    public function isChannel(): bool
    {
        return $this->type === 'channel' && $this->broadcast;
    }

    public function isSupergroup(): bool
    {
        return $this->type === 'channel' && $this->megagroup;
    }
}
