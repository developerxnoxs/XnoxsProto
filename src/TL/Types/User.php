<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;

/**
 * user#31774388 (layer 185+, dari tdlib telegram_api.tl)
 *
 * flags:#
 *   boolean bits (no data): self.10 contact.11 mutual_contact.12 deleted.13
 *     bot.14 bot_chat_history.15 bot_nochats.16 verified.17 restricted.18
 *     min.20 bot_inline_geo.21 support.23 scam.24 apply_min_photo.25
 *     fake.26 bot_attach_menu.27 premium.28 attach_menu_enabled.29
 * flags2:#
 *   boolean bits (no data): bot_can_edit.1 close_friend.2 stories_hidden.3
 *     stories_unavailable.4 contact_require_premium.10 bot_business.11
 *     bot_has_main_app.13 bot_forum_view.16 ...
 * id:long
 * access_hash:flags.0?long
 * first_name:flags.1?string
 * last_name:flags.2?string
 * username:flags.3?string
 * phone:flags.4?string
 * photo:flags.5?UserProfilePhoto
 * status:flags.6?UserStatus
 * bot_info_version:flags.14?int
 * restriction_reason:flags.18?Vector<RestrictionReason>
 * bot_inline_placeholder:flags.19?string
 * lang_code:flags.22?string
 * emoji_status:flags.30?EmojiStatus
 * usernames:flags2.0?Vector<Username>
 * stories_max_id:flags2.5?RecentStory
 * color:flags2.8?PeerColor
 * profile_color:flags2.9?PeerColor
 * bot_active_users:flags2.12?int
 * bot_verification_icon:flags2.14?long
 * send_paid_messages_stars:flags2.15?long
 */
class User
{
    const CONSTRUCTOR_ID    = 0x31774388;
    const CONSTRUCTOR_EMPTY = 0xd3bc4b7a; // userEmpty#d3bc4b7a id:long

    public int     $id;
    public ?int    $accessHash = null;
    public ?string $firstName  = null;
    public ?string $lastName   = null;
    public ?string $username   = null;
    public ?string $phone      = null;
    public bool    $self       = false;
    public bool    $contact    = false;
    public bool    $bot        = false;
    public bool    $verified   = false;
    public bool    $premium    = false;
    public bool    $min        = false;

    /**
     * Parse User dari BinaryReader.
     * Constructor sudah DIBACA oleh pemanggil sebelum memanggil metode ini.
     *
     * Membaca SEMUA field agar posisi stream tetap benar dalam Vector<User>.
     */
    public static function fromReader(BinaryReader $reader): self
    {
        $obj = new self();

        // Dua int flags selalu ada
        $flags  = $reader->readInt();
        $flags2 = $reader->readInt();

        // id:long — selalu ada
        $obj->id = $reader->readLong();

        // Boolean-only flags (hanya baca bit, tidak ada data):
        $obj->self     = (bool)($flags & (1 << 10));
        $obj->contact  = (bool)($flags & (1 << 11));
        $obj->bot      = (bool)($flags & (1 << 14));
        $obj->verified = (bool)($flags & (1 << 17));
        $obj->min      = (bool)($flags & (1 << 20)); // min user — tidak punya full info/access_hash
        $obj->premium  = (bool)($flags & (1 << 28));

        // ---- Data fields dari flags ----
        // flags.0  → access_hash:long
        if ($flags & (1 << 0))  $obj->accessHash = $reader->readLong();
        // flags.1  → first_name:string
        if ($flags & (1 << 1))  $obj->firstName  = $reader->readString();
        // flags.2  → last_name:string
        if ($flags & (1 << 2))  $obj->lastName   = $reader->readString();
        // flags.3  → username:string
        if ($flags & (1 << 3))  $obj->username   = $reader->readString();
        // flags.4  → phone:string
        if ($flags & (1 << 4))  $obj->phone      = $reader->readString();
        // flags.5  → photo:UserProfilePhoto
        if ($flags & (1 << 5))  TLSkipHelper::skipUserProfilePhoto($reader);
        // flags.6  → status:UserStatus
        if ($flags & (1 << 6))  TLSkipHelper::skipUserStatus($reader);
        // flags.14 → bot_info_version:int   (flags.14 juga dipakai boolean bot:true, tidak konflik)
        if ($flags & (1 << 14)) $reader->readInt();
        // flags.18 → restriction_reason:Vector<RestrictionReason>
        if ($flags & (1 << 18)) TLSkipHelper::skipVector(
            $reader,
            fn($r) => TLSkipHelper::skipRestrictionReason($r)
        );
        // flags.19 → bot_inline_placeholder:string
        if ($flags & (1 << 19)) $reader->readString();
        // flags.22 → lang_code:string
        if ($flags & (1 << 22)) $reader->readString();
        // flags.30 → emoji_status:EmojiStatus
        if ($flags & (1 << 30)) TLSkipHelper::skipEmojiStatus($reader);

        // ---- Data fields dari flags2 ----
        // flags2.0 → usernames:Vector<Username>
        if ($flags2 & (1 << 0))  TLSkipHelper::skipVector(
            $reader,
            fn($r) => TLSkipHelper::skipUsername($r)
        );
        // flags2.5 → stories_max_id:RecentStory
        if ($flags2 & (1 << 5))  TLSkipHelper::skipRecentStory($reader);
        // flags2.8 → color:PeerColor
        if ($flags2 & (1 << 8))  TLSkipHelper::skipPeerColor($reader);
        // flags2.9 → profile_color:PeerColor
        if ($flags2 & (1 << 9))  TLSkipHelper::skipPeerColor($reader);
        // flags2.12 → bot_active_users:int
        if ($flags2 & (1 << 12)) $reader->readInt();
        // flags2.14 → bot_verification_icon:long
        if ($flags2 & (1 << 14)) $reader->readLong();
        // flags2.15 → send_paid_messages_stars:long
        if ($flags2 & (1 << 15)) $reader->readLong();

        return $obj;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getDisplayName(): string
    {
        $name = $this->getFullName();
        if ($name !== '') return $name;
        if ($this->username) return '@' . $this->username;
        if ($this->phone) return '+' . $this->phone;
        return 'User#' . $this->id;
    }
}
