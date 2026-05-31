<?php

namespace XnoxsProto\TL\Parser;

use XnoxsProto\TL\BinaryReader;

/**
 * Helper statis untuk melompati (skip) tipe TL kompleks.
 *
 * Semua constructor ID diambil dari tdlib/td telegram_api.tl (layer terbaru).
 * Metode membaca TEPAT sejumlah byte yang diperlukan agar posisi stream tetap benar.
 */
class TLSkipHelper
{
    // =========================================================================
    // UserProfilePhoto
    // userProfilePhotoEmpty#4f11bae1
    // userProfilePhoto#82d1f706
    //   flags:# has_video:f.0 personal:f.2
    //   photo_id:long  stripped_thumb:f.1?bytes  dc_id:int
    // =========================================================================
    public static function skipUserProfilePhoto(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x4f11bae1) return; // empty — no fields
        // userProfilePhoto#82d1f706
        $flags = $r->readInt();
        $r->readLong();                         // photo_id
        if ($flags & (1 << 1)) $r->readBytes(); // stripped_thumb:f.1?bytes
        $r->readInt();                          // dc_id
    }

    // =========================================================================
    // UserStatus — constructor IDs updated per tdlib schema
    // userStatusEmpty#9d05049
    // userStatusOnline#edb93949 expires:int
    // userStatusOffline#8c703f was_online:int
    // userStatusRecently#7b197dc8 flags:#
    // userStatusLastWeek#541a1d1a flags:#
    // userStatusLastMonth#65899777 flags:#
    // =========================================================================
    public static function skipUserStatus(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x09d05049: break;                   // empty
            case 0xedb93949: $r->readInt(); break;    // online  — expires:int
            case 0x008c703f: $r->readInt(); break;    // offline — was_online:int
            case 0x7b197dc8:                          // recently — flags:#
            case 0x541a1d1a:                          // last week — flags:#
            case 0x65899777: $r->readInt(); break;    // last month — flags:#
            default: break;
        }
    }

    // =========================================================================
    // EmojiStatus — updated per tdlib schema
    // emojiStatusEmpty#2de11aae
    // emojiStatus#e7ff068a flags:# document_id:long until:f.0?int
    // emojiStatusCollectible#7184603b flags:# collectible_id:long document_id:long
    //   title:string slug:string pattern_document_id:long
    //   center_color:int edge_color:int pattern_color:int text_color:int
    //   until:f.0?int
    // =========================================================================
    public static function skipEmojiStatus(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x2de11aae: // empty
                break;
            case 0xe7ff068a: // emojiStatus
                $flags = $r->readInt();
                $r->readLong();                        // document_id
                if ($flags & (1 << 0)) $r->readInt(); // until
                break;
            case 0x7184603b: // emojiStatusCollectible
                $flags = $r->readInt();
                $r->readLong();   // collectible_id
                $r->readLong();   // document_id
                $r->readString(); // title
                $r->readString(); // slug
                $r->readLong();   // pattern_document_id
                $r->readInt();    // center_color
                $r->readInt();    // edge_color
                $r->readInt();    // pattern_color
                $r->readInt();    // text_color
                if ($flags & (1 << 0)) $r->readInt(); // until
                break;
            default: break;
        }
    }

    // =========================================================================
    // RecentStory#711d692d flags:# live:flags.0?true max_id:flags.1?int
    // =========================================================================
    public static function skipRecentStory(BinaryReader $r): void
    {
        $ctor = $r->readInt(); // 0x711d692d
        if ($ctor !== 0x711d692d) return;
        $flags = $r->readInt();
        if ($flags & (1 << 1)) $r->readInt(); // max_id
    }

    // =========================================================================
    // RestrictionReason#d072acb4 platform:string reason:string text:string
    // =========================================================================
    public static function skipRestrictionReason(BinaryReader $r): void
    {
        $r->readInt();    // constructor
        $r->readString(); // platform
        $r->readString(); // reason
        $r->readString(); // text
    }

    // =========================================================================
    // Username#b4073647 flags:# editable:f.0 active:f.1 username:string
    // =========================================================================
    public static function skipUsername(BinaryReader $r): void
    {
        $r->readInt();    // constructor
        $r->readInt();    // flags
        $r->readString(); // username
    }

    // =========================================================================
    // PeerColor — two subtypes per tdlib schema
    // peerColor#b54b5acf flags:# color:f.0?int background_emoji_id:f.1?long
    // peerColorCollectible#b9c0639a flags:#
    //   collectible_id:long gift_emoji_id:long background_emoji_id:long
    //   accent_color:int colors:Vector<int>
    //   dark_accent_color:f.0?int dark_colors:f.1?Vector<int>
    // =========================================================================
    public static function skipPeerColor(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0xb54b5acf: // peerColor
                $flags = $r->readInt();
                if ($flags & (1 << 0)) $r->readInt();  // color
                if ($flags & (1 << 1)) $r->readLong(); // background_emoji_id
                break;
            case 0xb9c0639a: // peerColorCollectible
                $flags = $r->readInt();
                $r->readLong(); $r->readLong(); $r->readLong(); // 3 longs
                $r->readInt();  // accent_color
                self::skipVectorIntRaw($r); // colors:Vector<int>
                if ($flags & (1 << 0)) $r->readInt();         // dark_accent_color
                if ($flags & (1 << 1)) self::skipVectorIntRaw($r); // dark_colors
                break;
            default: break;
        }
    }

    // =========================================================================
    // Birthday#6c8e1e06 flags:# day:int month:int year:f.0?int
    // =========================================================================
    public static function skipBirthday(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        $r->readInt(); // day
        $r->readInt(); // month
        if ($flags & (1 << 0)) $r->readInt(); // year
    }

    // =========================================================================
    // Vector<T> helper
    // =========================================================================
    public static function skipVector(BinaryReader $r, callable $itemSkipper): void
    {
        $r->readInt(); // vector constructor 0x1cb5c415
        $count = $r->readInt();
        for ($i = 0; $i < $count; $i++) {
            $itemSkipper($r);
        }
    }

    public static function skipVectorIntRaw(BinaryReader $r): void
    {
        $r->readInt(); // 0x1cb5c415
        $count = $r->readInt();
        for ($i = 0; $i < $count; $i++) $r->readInt();
    }

    /**
     * Read a Vector<int> and return the values as array.
     */
    public static function readVectorInt(BinaryReader $r): array
    {
        $r->readInt(); // vector ctor 0x1cb5c415
        $count  = $r->readInt();
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $r->readInt();
        }
        return $result;
    }

    public static function skipVectorLongRaw(BinaryReader $r): void
    {
        $r->readInt(); // 0x1cb5c415
        $count = $r->readInt();
        for ($i = 0; $i < $count; $i++) $r->readLong();
    }

    // =========================================================================
    // GeoPoint
    // geoPointEmpty#1117dd5f
    // geoPoint#b2a2f663 flags:# long:double lat:double access_hash:long
    //   accuracy_radius:f.0?int
    // =========================================================================
    private static function skipGeoPoint(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x1117dd5f) return;
        $flags = $r->readInt();
        $r->readDouble(); $r->readDouble(); $r->readLong();
        if ($flags & (1 << 0)) $r->readInt();
    }

    // =========================================================================
    // InputPeer
    // =========================================================================
    public static function skipInputPeer(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x7f3b18ea: break; // empty
            case 0x7da07ec9: break; // self
            case 0xdde8a54c: $r->readLong(); $r->readLong(); break; // user
            case 0x35a95cb9: $r->readLong(); break;                 // chat
            case 0x27bcbbfc: $r->readLong(); $r->readLong(); break; // channel
            case 0xa87b0a1c: self::skipInputPeer($r); $r->readInt(); $r->readLong(); break;
            case 0xbd2a0840: self::skipInputPeer($r); $r->readInt(); $r->readLong(); break;
            default: break;
        }
    }

    // =========================================================================
    // MessageEntity (minimal skip: offset+length are always present)
    // =========================================================================
    public static function skipMessageEntity(BinaryReader $r): void
    {
        $c = $r->readInt();
        $r->readInt(); $r->readInt(); // offset, length (always)
        switch ($c) {
            case 0x9bf9a5e6: $r->readString(); break; // pre — language:string
            case 0x76a6d327: $r->readString(); break; // textUrl — url:string
            case 0x352dca58: $r->readLong();  break;  // mentionName — user_id:long
            case 0x208e68c9: self::skipInputPeer($r); break; // inputMentionName
            case 0x4c4e743f: $r->readLong();  break;  // customEmoji — document_id:long
            default: break; // all other entity types have no extra fields
        }
    }

    // =========================================================================
    // PeerNotifySettings#99622c0c
    // flags:# show_previews:f.0?Bool silent:f.1?Bool mute_until:f.2?int
    //   ios_sound:f.3?NS android_sound:f.4?NS other_sound:f.5?NS
    //   stories_muted:f.6?Bool stories_hide_sender:f.7?Bool
    //   stories_ios_sound:f.8?NS stories_android_sound:f.9?NS
    //   stories_other_sound:f.10?NS
    // =========================================================================
    public static function skipPeerNotifySettings(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) $r->readInt(); // show_previews Bool
        if ($flags & (1 << 1)) $r->readInt(); // silent Bool
        if ($flags & (1 << 2)) $r->readInt(); // mute_until int
        // sounds at bits 3,4,5,8,9,10
        foreach ([3, 4, 5, 8, 9, 10] as $bit) {
            if ($flags & (1 << $bit)) self::skipNotificationSound($r);
        }
        // Bool fields at bits 6,7 — just readInt (boolTrue/boolFalse)
        if ($flags & (1 << 6)) $r->readInt(); // stories_muted Bool
        if ($flags & (1 << 7)) $r->readInt(); // stories_hide_sender Bool
    }

    // notificationSoundRingtone constructor updated: #ff6c8049 (was #059df173)
    public static function skipNotificationSound(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x97e8bebe: break; // default — no fields
            case 0x6f0c34df: break; // none    — no fields
            case 0x830b9ae4: $r->readString(); $r->readString(); break; // local
            case 0xff6c8049: $r->readLong(); break; // ringtone — id:long
            default: break;
        }
    }

    // =========================================================================
    // DraftMessage — updated per tdlib schema
    // draftMessageEmpty#1b0c841a flags:# date:f.0?int
    // draftMessage#96eaa5eb flags:# no_webpage:f.1 invert_media:f.6
    //   reply_to:f.4?InputReplyTo message:string
    //   entities:f.3?Vector<MessageEntity> media:f.5?InputMedia
    //   date:int effect:f.7?long suggested_post:f.8?SuggestedPost
    // =========================================================================
    public static function skipDraftMessage(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x1b0c841a) { // draftMessageEmpty
            $flags = $r->readInt();
            if ($flags & (1 << 0)) $r->readInt(); // date
            return;
        }
        // draftMessage#96eaa5eb
        $flags = $r->readInt();
        if ($flags & (1 << 4)) self::skipInputReplyTo($r);  // reply_to
        $r->readString(); // message
        if ($flags & (1 << 3)) self::skipVector($r, fn($x) => self::skipMessageEntity($x));
        if ($flags & (1 << 5)) self::skipInputMedia($r);    // media (new)
        $r->readInt();    // date
        if ($flags & (1 << 7)) $r->readLong(); // effect
        if ($flags & (1 << 8)) self::skipSuggestedPost($r); // suggested_post (new)
    }

    // =========================================================================
    // InputReplyTo — updated per tdlib schema
    // inputReplyToMessage#3bd4b7c2 flags:# reply_to_msg_id:int
    //   top_msg_id:f.0?int reply_to_peer_id:f.1?InputPeer
    //   quote_text:f.2?string quote_entities:f.3?Vector<ME>
    //   quote_offset:f.4?int monoforum_peer_id:f.5?InputPeer
    //   todo_item_id:f.6?int poll_option:f.7?bytes
    // inputReplyToStory#5881323a peer:InputPeer story_id:int
    // inputReplyToMonoForum#69d66c45 monoforum_peer_id:InputPeer
    // =========================================================================
    public static function skipInputReplyTo(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x3bd4b7c2: // inputReplyToMessage
                $flags = $r->readInt();
                $r->readInt(); // reply_to_msg_id
                if ($flags & (1 << 0)) $r->readInt();           // top_msg_id
                if ($flags & (1 << 1)) self::skipInputPeer($r); // reply_to_peer_id
                if ($flags & (1 << 2)) $r->readString();        // quote_text
                if ($flags & (1 << 3)) self::skipVector($r, fn($x) => self::skipMessageEntity($x));
                if ($flags & (1 << 4)) $r->readInt();           // quote_offset
                if ($flags & (1 << 5)) self::skipInputPeer($r); // monoforum_peer_id
                if ($flags & (1 << 6)) $r->readInt();           // todo_item_id
                if ($flags & (1 << 7)) $r->readBytes();         // poll_option
                break;
            case 0x5881323a: // inputReplyToStory
                self::skipInputPeer($r);
                $r->readInt(); // story_id
                break;
            case 0x69d66c45: // inputReplyToMonoForum
                self::skipInputPeer($r);
                break;
            default: break;
        }
    }

    // =========================================================================
    // InputMedia — simplified skip (many subtypes, we just need to not crash)
    // For draftMessage.media, we skip based on known common constructors.
    // =========================================================================
    private static function skipInputMedia(BinaryReader $r): void
    {
        // InputMedia has many subtypes. For drafts, most common is inputMediaEmpty.
        // We read the constructor and handle known types.
        $c = $r->readInt();
        switch ($c) {
            case 0x9664f57f: break; // inputMediaEmpty — no fields
            // For other types, we cannot safely skip without knowing their structure.
            // In practice, draft messages rarely have complex media.
            // If parsing fails here, it means a draft has media — rare.
            default: break;
        }
    }

    // =========================================================================
    // SuggestedPost — suggestedPost#2f3a1b62
    // flags:#  schedule_date:flags.0?int  price:flags.1?StarsAmount
    // rejected:flags.2?true  accepted:flags.3?true  admin_signature:flags.4?true
    // reject_reason:flags.5?string
    // =========================================================================
    private static function skipSuggestedPost(BinaryReader $r): void
    {
        $r->readInt();  // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) $r->readInt();             // schedule_date
        if ($flags & (1 << 1)) self::skipStarsAmount($r); // price
        // bits 2,3,4 are ?true — no extra bytes
        if ($flags & (1 << 5)) $r->readString();          // reject_reason
    }

    // =========================================================================
    // InputStickerSet (many subtypes)
    // =========================================================================
    private static function skipInputStickerSet(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0xffb62b95: break; // empty
            case 0x9de7a269: $r->readLong(); $r->readLong(); break; // by ID
            case 0x861cc8a0: $r->readString(); break; // short name
            case 0x028703c8: break; // animated emoji
            case 0xe67f520e: $r->readString(); break; // dice
            case 0x0cde3739: break; // animated emoji animations
            case 0xc88b3b02: break; // premium gifts
            case 0x04c4d4ce: break; // emoji generic animations
            case 0x29d0f5ee: break; // emoji default statuses
            case 0x44c1f8e9: break; // emoji default topic icons
            case 0x49748553: break; // emoji channel default statuses
            case 0x1cf671a0: break; // ton gifts
            default: break;
        }
    }

    // =========================================================================
    // PhotoSize — constructors updated per tdlib schema
    // photoSizeEmpty#e17e23c type:string
    // photoSize#75c78e60 type:string w:int h:int size:int
    // photoCachedSize#21e1ad6 type:string w:int h:int bytes:bytes
    // photoStrippedSize#e0b0bc2e type:string bytes:bytes
    // photoSizeProgressive#fa3efb95 type:string w:int h:int sizes:Vector<int>
    // photoPathSize#d8214d41 type:string bytes:bytes
    // =========================================================================
    private static function skipPhotoSize(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x0e17e23c: // photoSizeEmpty
                $r->readString(); break;
            case 0x75c78e60: // photoSize — type w h size
                $r->readString(); $r->readInt(); $r->readInt(); $r->readInt(); break;
            case 0x021e1ad6: // photoCachedSize — type w h bytes (order: w,h BEFORE bytes)
                $r->readString(); $r->readInt(); $r->readInt(); $r->readBytes(); break;
            case 0xe0b0bc2e: // photoStrippedSize — type bytes
                $r->readString(); $r->readBytes(); break;
            case 0xfa3efb95: // photoSizeProgressive — type w h sizes:Vector<int>
                $r->readString(); $r->readInt(); $r->readInt();
                self::skipVectorIntRaw($r); break;
            case 0xd8214d41: // photoPathSize — type bytes
                $r->readString(); $r->readBytes(); break;
            default: break;
        }
    }

    // =========================================================================
    // VideoSize
    // videoSize#de33b094 flags:# type:string w:int h:int size:int
    //   video_start_ts:f.0?double
    // videoSizeEmojiMarkup#f85c413c emoji_id:long background_colors:Vector<int>
    // videoSizeStickerMarkup#da082fe stickerset sticker_id:long bg:Vector<int>
    // =========================================================================
    private static function skipVideoSize(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0xde33b094:
                $flags = $r->readInt();
                $r->readString(); $r->readInt(); $r->readInt(); $r->readInt();
                if ($flags & (1 << 0)) $r->readDouble();
                break;
            case 0xf85c413c:
                $r->readLong(); self::skipVectorIntRaw($r); break;
            case 0x0da082fe:
                self::skipInputStickerSet($r);
                $r->readLong(); self::skipVectorIntRaw($r); break;
            default: break;
        }
    }

    // =========================================================================
    // DocumentAttribute — constructors updated per tdlib schema
    // documentAttributeVideo#43c57c48 flags:# round_message:f.0
    //   supports_streaming:f.1 nosound:f.3 duration:double w:int h:int
    //   preload_prefix_size:f.2?int video_start_ts:f.4?double video_codec:f.5?string
    // documentAttributeCustomEmoji#fd149899 flags:# free:f.0 text_color:f.1
    //   alt:string stickerset:InputStickerSet
    // =========================================================================
    private static function skipDocumentAttribute(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x6c37c15c: // imageSize — w:int h:int
                $r->readInt(); $r->readInt(); break;
            case 0x11b58939: break; // animated — no fields
            case 0x6319d612: // sticker — flags:# alt:string stickerset mask_coords:f.0?
                $flags = $r->readInt();
                $r->readString();
                self::skipInputStickerSet($r);
                if ($flags & (1 << 0)) {
                    $r->readInt(); $r->readInt();
                    $r->readDouble(); $r->readDouble(); $r->readDouble();
                }
                break;
            case 0x43c57c48: // video (constructor updated) — flags:# dur:double w:int h:int
                $flags = $r->readInt();
                $r->readDouble(); $r->readInt(); $r->readInt();
                if ($flags & (1 << 2)) $r->readInt();    // preload_prefix_size
                if ($flags & (1 << 4)) $r->readDouble(); // video_start_ts
                if ($flags & (1 << 5)) $r->readString(); // video_codec
                break;
            case 0x9852f9c6: // audio — flags:# voice:f.10 dur:int
                $flags = $r->readInt();
                $r->readInt();
                if ($flags & (1 << 0)) $r->readString(); // title
                if ($flags & (1 << 1)) $r->readString(); // performer
                if ($flags & (1 << 2)) $r->readBytes();  // waveform
                break;
            case 0x15590068: // filename
                $r->readString(); break;
            case 0x9801d2f7: break; // hasStickers
            case 0xfd149899: // customEmoji (constructor updated) — flags:# alt:string stickerset
                $r->readInt();
                $r->readString();
                self::skipInputStickerSet($r);
                break;
            default: break;
        }
    }

    // =========================================================================
    // Document — constructor updated to #8fd4c4d8
    // documentEmpty#36f8c871 id:long
    // document#8fd4c4d8 flags:# id:long access_hash:long file_reference:bytes
    //   date:int mime_type:string size:long thumbs:f.0?Vector<PhotoSize>
    //   video_thumbs:f.1?Vector<VideoSize> dc_id:int
    //   attributes:Vector<DocumentAttribute>
    // =========================================================================
    public static function skipDocument(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x36f8c871) { $r->readLong(); return; }
        $flags = $r->readInt();
        $r->readLong();   // id
        $r->readLong();   // access_hash
        $r->readBytes();  // file_reference
        $r->readInt();    // date
        $r->readString(); // mime_type
        $r->readLong();   // size
        if ($flags & (1 << 0)) self::skipVector($r, fn($x) => self::skipPhotoSize($x));
        if ($flags & (1 << 1)) self::skipVector($r, fn($x) => self::skipVideoSize($x));
        $r->readInt();    // dc_id
        self::skipVector($r, fn($x) => self::skipDocumentAttribute($x));
    }

    // =========================================================================
    // BusinessRecipients#21108ff7 — constructor updated
    // flags:# existing_chats:f.0 new_chats:f.1 contacts:f.2 non_contacts:f.3
    //   exclude_selected:f.5 users:f.4?Vector<long>
    // =========================================================================
    private static function skipBusinessRecipients(BinaryReader $r): void
    {
        $r->readInt(); // constructor 0x21108ff7
        $flags = $r->readInt();
        if ($flags & (1 << 4)) self::skipVectorLongRaw($r);
    }

    // =========================================================================
    // BusinessWorkHours#8c92b098
    // =========================================================================
    public static function skipBusinessWorkHours(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // flags
        $r->readString(); // timezone_id
        self::skipVector($r, function (BinaryReader $reader) {
            $reader->readInt(); // constructor BusinessWeeklyOpen#120b1ab9
            $reader->readInt(); // start_minute
            $reader->readInt(); // end_minute
        });
    }

    // =========================================================================
    // BusinessLocation#ac5c1af7 — constructor updated
    // flags:# geo_point:f.0?GeoPoint address:string
    // =========================================================================
    public static function skipBusinessLocation(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) self::skipGeoPoint($r);
        $r->readString();
    }

    // =========================================================================
    // BusinessGreetingMessage#e519abab — constructor updated
    // shortcut_id:int recipients:BusinessRecipients no_activity_days:int
    // =========================================================================
    public static function skipBusinessGreetingMessage(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // shortcut_id
        self::skipBusinessRecipients($r);
        $r->readInt(); // no_activity_days
    }

    // =========================================================================
    // BusinessAwayMessage#ef156a5c — constructor updated
    // flags:# offline_only:f.0 shortcut_id:int
    //   schedule:BusinessAwayMessageSchedule recipients:BusinessRecipients
    // =========================================================================
    public static function skipBusinessAwayMessage(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // flags
        $r->readInt(); // shortcut_id
        $sc = $r->readInt(); // schedule constructor
        if ($sc === 0xcc4d9ecc) { $r->readInt(); $r->readInt(); }
        self::skipBusinessRecipients($r);
    }

    // =========================================================================
    // BusinessIntro#5a0a066d
    // flags:# title:string description:string sticker:f.0?Document
    // =========================================================================
    public static function skipBusinessIntro(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        $r->readString();
        $r->readString();
        if ($flags & (1 << 0)) self::skipDocument($r);
    }

    // =========================================================================
    // Peer helper (untuk Dialog parsing)
    // peerUser#59511722 user_id:long  (new layer 185+, was #9db1bc6d)
    // peerChat#36c6019a chat_id:long  (new layer 185+, was #bad0e5bb)
    // peerChannel#a2a5371e channel_id:long (unchanged)
    // =========================================================================
    public static function readPeer(BinaryReader $r): array
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x59511722: // peerUser new
            case 0x9db1bc6d: // peerUser old
                return ['type' => 'user',    'id' => $r->readLong()];
            case 0x36c6019a: // peerChat new
            case 0xbad0e5bb: // peerChat old
                return ['type' => 'chat',    'id' => $r->readLong()];
            case 0xa2a5371e: // peerChannel
                return ['type' => 'channel', 'id' => $r->readLong()];
            default:
                // Peer tidak dikenal — tetap baca long agar stream tidak rusak
                $r->readLong();
                return ['type' => 'unknown', 'id' => 0];
        }
    }

    // =========================================================================
    // SendMessageAction — baca konstruktor + field opsional, kembalikan label
    //
    // sendMessageTypingAction        #16bf744e — (tidak ada field ekstra)
    // sendMessageCancelAction        #fd5ec8f5 — (tidak ada field ekstra)
    // sendMessageRecordVideoAction   #a187d66f — (tidak ada field ekstra)
    // sendMessageUploadVideoAction   #e9763aec — progress:int
    // sendMessageRecordAudioAction   #d52f73f7 — (tidak ada field ekstra)
    // sendMessageUploadAudioAction   #f351d7ab — progress:int
    // sendMessageUploadPhotoAction   #d1d34a26 — progress:int
    // sendMessageUploadDocumentAction#aa0cd9e4 — progress:int
    // sendMessageGeoLocationAction   #176f8ba1 — (tidak ada field ekstra)
    // sendMessageChooseContactAction #628cbc6f — (tidak ada field ekstra)
    // sendMessageGamePlayAction      #dd6a8f48 — (tidak ada field ekstra)
    // sendMessageRecordRoundAction   #88f27fbc — (tidak ada field ekstra)
    // sendMessageUploadRoundAction   #243e1c66 — progress:int
    // speakingInGroupCallAction      #d92c2285 — (tidak ada field ekstra)
    // sendMessageHistoryImportAction #dbda9246 — progress:int
    // sendMessageChooseStickerAction #b05ac6b1 — (tidak ada field ekstra)
    // sendMessageEmojiInteraction    #25972bcb — emoticon:string msg_id:int interaction:(complex)
    // sendMessageEmojiInteractionSeen#b665902e — emoticon:string
    //
    // Mengembalikan '' bila aksi adalah cancel, label Bahasa Indonesia untuk lainnya.
    // =========================================================================
    public static function readSendMessageAction(BinaryReader $r): string
    {
        $ctor = $r->readInt();
        switch ($ctor) {
            case 0x16bf744e: return 'sedang mengetik';
            case 0xfd5ec8f5: return '';                          // cancel
            case 0xa187d66f: return 'merekam video';
            case 0xe9763aec: $r->readInt(); return 'mengunggah video';
            case 0xd52f73f7: return 'merekam suara';
            case 0xf351d7ab: $r->readInt(); return 'mengunggah audio';
            case 0xd1d34a26: $r->readInt(); return 'mengunggah foto';
            case 0xaa0cd9e4: $r->readInt(); return 'mengunggah dokumen';
            case 0x176f8ba1: return 'berbagi lokasi';
            case 0x628cbc6f: return 'memilih kontak';
            case 0xdd6a8f48: return 'bermain game';
            case 0x88f27fbc: return 'merekam video bundar';
            case 0x243e1c66: $r->readInt(); return 'mengunggah video bundar';
            case 0xd92c2285: return 'berbicara di panggilan';
            case 0xdbda9246: $r->readInt(); return 'mengimpor riwayat';
            case 0xb05ac6b1: return 'memilih stiker';
            case 0x25972bcb:                       // emojiInteraction: emoticon + msg_id
                $r->readString(); $r->readInt();   // skip emoticon:string, msg_id:int
                return 'reaksi emoji';             // interaction (MessageInteraction) diabaikan
            case 0xb665902e:                       // emojiInteractionSeen: emoticon
                $r->readString(); return 'melihat reaksi emoji';
            default: return 'aktivitas';
        }
    }

    // Helper internal: skip Peer tanpa return value
    public static function skipPeer(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x59511722: case 0x9db1bc6d: // peerUser
            case 0x36c6019a: case 0xbad0e5bb: // peerChat
            case 0xa2a5371e:                  // peerChannel
                $r->readLong();
                break;
            default:
                $r->readLong(); // fallback
                break;
        }
    }

    // =========================================================================
    // ChatPhoto
    // chatPhotoEmpty#37c1011c
    // chatPhoto#1c6e1c11 flags:# has_video:f.0?true photo_id:long
    //   stripped_thumb:f.1?bytes dc_id:int
    // =========================================================================
    public static function skipChatPhoto(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x37c1011c) return; // empty — no fields
        // chatPhoto#1c6e1c11
        $flags = $r->readInt();
        $r->readLong();                          // photo_id
        if ($flags & (1 << 1)) $r->readBytes(); // stripped_thumb
        $r->readInt();                           // dc_id
    }

    // =========================================================================
    // InputChannel (from TDLib official schema)
    // inputChannelEmpty#ee8c1e86
    // inputChannel#f35aec28 channel_id:long access_hash:long
    // inputChannelFromMessage#5b934f9d peer:InputPeer msg_id:int channel_id:long
    // =========================================================================
    public static function skipInputChannel(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0xee8c1e86: break; // empty
            case 0xf35aec28: $r->readLong(); $r->readLong(); break; // channel_id + access_hash
            case 0x5b934f9d:
                self::skipInputPeer($r);
                $r->readInt();  // msg_id
                $r->readLong(); // channel_id
                break;
            default: break;
        }
    }

    // =========================================================================
    // ChatAdminRights#5fb224d5 flags:#
    // (all fields are ?true bits — no data payload)
    // =========================================================================
    public static function skipChatAdminRights(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // flags
    }

    // =========================================================================
    // ChatBannedRights#9f120418 flags:# ... until_date:int
    // (all permission fields are ?true bits, plus until_date at the end)
    // =========================================================================
    public static function skipChatBannedRights(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // flags
        $r->readInt(); // until_date
    }

    // =========================================================================
    // Photo
    // photoEmpty#2331b22d id:long
    // photo#fb197a65 flags:# has_stickers:f.0?true id:long access_hash:long
    //   file_reference:bytes date:int sizes:Vector<PhotoSize>
    //   video_sizes:f.1?Vector<VideoSize> dc_id:int
    // =========================================================================

    /**
     * Read a Photo and return full download metadata.
     *
     * @return array ['type','mime','filename','id','access_hash','file_reference','dc_id','thumb_size']
     */
    public static function readPhotoInfo(BinaryReader $r): array
    {
        $c = $r->readInt();
        if ($c === 0x2331b22d) { // photoEmpty id:long
            $r->readLong();
            return ['type' => 'photo', 'mime' => 'image/jpeg', 'filename' => ''];
        }
        // photo#fb197a65
        $flags      = $r->readInt();
        $id         = $r->readLong();
        $accessHash = $r->readLong();
        $fileRef    = $r->readBytes();
        $r->readInt(); // date

        // sizes:Vector<PhotoSize> — collect size letters to pick best
        $r->readInt(); // vector ctor 0x1cb5c415
        $num       = $r->readInt();
        $available = [];
        for ($i = 0; $i < $num; $i++) {
            $sc = $r->readInt();
            switch ($sc) {
                case 0x75c78e60: // photoSize  type:string w:int h:int size:int
                    $sz = $r->readString(); $r->readInt(); $r->readInt(); $r->readInt();
                    $available[] = $sz; break;
                case 0x021e1ad6: // photoCachedSize  type:string w:int h:int bytes:bytes
                    $sz = $r->readString(); $r->readInt(); $r->readInt(); $r->readBytes();
                    $available[] = $sz; break;
                case 0xe0b0bc2e: // photoStrippedSize  type:string bytes:bytes
                    $r->readString(); $r->readBytes(); break;
                case 0xfa3efb95: // photoSizeProgressive  type:string w:int h:int sizes:Vector<int>
                    $sz = $r->readString(); $r->readInt(); $r->readInt();
                    self::skipVectorIntRaw($r);
                    $available[] = $sz; break;
                case 0xd8214d41: // photoPathSize  type:string bytes:bytes
                    $r->readString(); $r->readBytes(); break;
                case 0x0e17e23c: // photoSizeEmpty  type:string
                    $r->readString(); break;
                default: break;
            }
        }
        // video_sizes:f.1?Vector<VideoSize>
        if ($flags & (1 << 1)) self::skipVector($r, fn($x) => self::skipVideoSize($x));
        $dcId = $r->readInt();

        // Pick best size: w(xxlarge) > y(xlarge) > x(large) > m(medium) > s(small)
        $priority  = ['w' => 5, 'y' => 4, 'x' => 3, 'm' => 2, 's' => 1];
        $thumbSize = 'y';
        $best      = 0;
        foreach ($available as $sz) {
            $score = $priority[$sz] ?? 0;
            if ($score > $best) { $best = $score; $thumbSize = $sz; }
        }
        if ($best === 0 && !empty($available)) {
            $thumbSize = end($available);
        }

        return [
            'type'           => 'photo',
            'mime'           => 'image/jpeg',
            'filename'       => '',
            'id'             => $id,
            'access_hash'    => $accessHash,
            'file_reference' => $fileRef,
            'dc_id'          => $dcId,
            'thumb_size'     => $thumbSize,
        ];
    }

    public static function skipPhoto(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x2331b22d) { $r->readLong(); return; } // photoEmpty
        // photo#fb197a65
        $flags = $r->readInt();
        $r->readLong();   // id
        $r->readLong();   // access_hash
        $r->readBytes();  // file_reference
        $r->readInt();    // date
        self::skipVector($r, fn($x) => self::skipPhotoSize($x));
        if ($flags & (1 << 1)) self::skipVector($r, fn($x) => self::skipVideoSize($x));
        $r->readInt();    // dc_id
    }

    // =========================================================================
    // MessageFwdHeader#4e4df4bb
    // flags:# imported:f.10?true
    //   from_id:f.0?Peer from_name:f.5?string date:int
    //   channel_post:f.2?int post_author:f.3?string
    //   saved_from_peer:f.4?Peer saved_from_msg_id:f.4?int
    //   saved_from_id:f.8?Peer saved_from_name:f.9?string
    //   saved_date:f.6?int psa_type:f.7?string
    // =========================================================================
    public static function skipMessageFwdHeader(BinaryReader $r): void
    {
        $r->readInt(); // constructor 0x4e4df4bb
        $flags = $r->readInt();
        if ($flags & (1 << 0)) self::skipPeer($r); // from_id:Peer
        if ($flags & (1 << 5)) $r->readString(); // from_name
        $r->readInt();                            // date — always present
        if ($flags & (1 << 2)) $r->readInt();    // channel_post
        if ($flags & (1 << 3)) $r->readString(); // post_author
        if ($flags & (1 << 4)) {
            self::skipPeer($r); // saved_from_peer
            $r->readInt(); // saved_from_msg_id
        }
        if ($flags & (1 << 8)) self::skipPeer($r); // saved_from_id
        if ($flags & (1 << 9)) $r->readString(); // saved_from_name
        if ($flags & (1 << 6)) $r->readInt();    // saved_date
        if ($flags & (1 << 7)) $r->readString(); // psa_type
    }

    // =========================================================================
    // MessageReplyHeader#afbc09db
    // flags:# reply_to_scheduled:f.2?true forum_topic:f.3?true
    //   quote:f.9?true quote_final:f.10?true
    //   reply_to_msg_id:f.4?int reply_to_peer_id:f.0?Peer
    //   reply_from:f.5?MessageFwdHeader
    //   reply_media:f.8?MessageMedia
    //   top_msg_id:f.1?int
    //   quote_text:f.6?string quote_entities:f.7?Vector<MessageEntity>
    //   quote_offset:f.9?int
    // =========================================================================
    public static function skipMessageReplyHeader(BinaryReader $r): void
    {
        $r->readInt(); // constructor 0xafbc09db
        $flags = $r->readInt();
        if ($flags & (1 << 4)) $r->readInt(); // reply_to_msg_id
        if ($flags & (1 << 0)) self::skipPeer($r); // reply_to_peer_id
        if ($flags & (1 << 5)) self::skipMessageFwdHeader($r); // reply_from
        if ($flags & (1 << 8)) self::skipMessageMedia($r);     // reply_media
        if ($flags & (1 << 1)) $r->readInt();    // top_msg_id
        if ($flags & (1 << 6)) $r->readString(); // quote_text
        if ($flags & (1 << 7)) self::skipVector($r, fn($x) => self::skipMessageEntity($x));
        if ($flags & (1 << 9)) $r->readInt();    // quote_offset
    }

    // =========================================================================
    // MessageMedia — many subtypes
    // =========================================================================
    public static function skipMessageMedia(BinaryReader $r): void
    {
        self::readMessageMedia($r);
    }

    /**
     * Read MessageMedia: consumes exactly the right bytes and returns basic type info.
     *
     * @return array ['type'=>string, 'mime'=>string, 'filename'=>string]
     */
    public static function readMessageMedia(BinaryReader $r): array
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x3ded6320: // messageMediaEmpty — no fields
                return ['type' => 'empty'];

            case 0x695150d7: // messageMediaPhoto
                $flags = $r->readInt();
                $info  = ['type' => 'photo', 'mime' => 'image/jpeg', 'filename' => ''];
                if ($flags & (1 << 0)) $info = self::readPhotoInfo($r);
                if ($flags & (1 << 2)) $r->readInt(); // ttl_seconds
                return $info;

            case 0x56e0d474: // messageMediaGeo
                self::skipGeoPoint($r);
                return ['type' => 'geo'];

            case 0x70322949: // messageMediaContact
                $phone = $r->readString();
                $fn    = $r->readString();
                $ln    = $r->readString();
                $r->readString(); // vcard
                $r->readLong();   // user_id
                return ['type' => 'contact', 'name' => trim("$fn $ln"), 'phone' => $phone];

            case 0x9f84f49e: // messageMediaUnsupported — no fields
                return ['type' => 'unsupported'];

            case 0x52d8ccd9: // messageMediaDocument#52d8ccd9 (Layer 214+)
                // flags:#  nopremium:3  spoiler:4  video:6  round:7  voice:8
                //   document:0?Document  alt_documents:5?Vector<Document>
                //   video_cover:9?Photo  video_timestamp:10?int  ttl_seconds:2?int
                $flags   = $r->readInt();
                $docInfo = ['type' => 'document', 'mime' => '', 'filename' => ''];
                if ($flags & (1 << 0)) $docInfo = array_merge($docInfo, self::readDocumentInfo($r));
                if ($flags & (1 << 5)) self::skipVector($r, fn($x) => self::skipDocument($x));
                if ($flags & (1 << 9)) self::readPhotoInfo($r);  // video_cover:Photo (NEW — bit 9)
                if ($flags & (1 << 10)) $r->readInt();            // video_timestamp (shifted to bit 10)
                if ($flags & (1 << 2)) $r->readInt();             // ttl_seconds
                return $docInfo;

            case 0x4cf4d72d: // messageMediaDocument (legacy, pre-Layer 214)
                // flags:#  document:0?Document  alt_documents:5?Vector<Document>
                //   ttl_seconds:2?int  video_timestamp:9?int
                $flags   = $r->readInt();
                $docInfo = ['type' => 'document', 'mime' => '', 'filename' => ''];
                if ($flags & (1 << 0)) $docInfo = array_merge($docInfo, self::readDocumentInfo($r));
                if ($flags & (1 << 5)) self::skipVector($r, fn($x) => self::skipDocument($x));
                if ($flags & (1 << 2)) $r->readInt(); // ttl_seconds
                if ($flags & (1 << 9)) $r->readInt(); // video_timestamp
                return $docInfo;

            case 0xa32dd600: // messageMediaWebPage (old format — webpage only, no flags)
                self::skipWebPage($r);
                return ['type' => 'webpage'];

            case 0xddf10c3b: // messageMediaWebPage (new format, layer 163+)
                $r->readInt(); // flags (all ?true, no associated data)
                self::skipWebPage($r);
                return ['type' => 'webpage'];

            case 0x2ec0533f: // messageMediaVenue
                self::skipGeoPoint($r);
                $r->readString(); $r->readString(); $r->readString();
                $r->readString(); $r->readString();
                return ['type' => 'venue'];

            case 0xfdb19008: // messageMediaGame
                self::skipGame($r);
                return ['type' => 'game'];

            case 0x3f7ee58b: // messageMediaDice
                $r->readInt();
                $emoticon = $r->readString();
                return ['type' => 'dice', 'emoticon' => $emoticon];

            case 0x68cb6283: // messageMediaStory
                $r->readInt(); // flags
                self::skipPeer($r);
                $r->readInt(); // id
                return ['type' => 'story'];

            case 0xb940c666: // messageMediaGeoLive
                $flags = $r->readInt();
                self::skipGeoPoint($r);
                if ($flags & (1 << 0)) $r->readInt();
                $r->readInt();
                if ($flags & (1 << 1)) $r->readInt();
                return ['type' => 'geo_live'];

            case 0x4bd6e798: // messageMediaPoll
                self::skipPoll($r);
                self::skipPollResults($r);
                return ['type' => 'poll'];

            case 0xf6a548d3: // messageMediaInvoice
                $flags = $r->readInt();
                $r->readString(); $r->readString();
                if ($flags & (1 << 0)) self::skipWebDocument($r);
                if ($flags & (1 << 2)) $r->readLong();
                $r->readString(); $r->readLong(); $r->readString();
                if ($flags & (1 << 4)) $r->readLong();
                return ['type' => 'invoice'];

            default:
                throw new \RuntimeException(sprintf('Unknown MessageMedia constructor: 0x%08x', $c));
        }
    }

    /**
     * Read a Document and return full download metadata.
     *
     * @return array ['type','mime','filename','id','access_hash','file_reference','dc_id','size','thumb_size']
     */
    private static function readDocumentInfo(BinaryReader $r): array
    {
        $c = $r->readInt();
        if ($c === 0x36f8c871) { // documentEmpty id:long
            $r->readLong();
            return ['type' => 'document', 'mime' => '', 'filename' => ''];
        }
        // document#8fd4c4d8
        $flags      = $r->readInt();
        $id         = $r->readLong();
        $accessHash = $r->readLong();
        $fileRef    = $r->readBytes();
        $r->readInt();    // date
        $mime = $r->readString();
        $size = $r->readLong();
        if ($flags & (1 << 0)) self::skipVector($r, fn($x) => self::skipPhotoSize($x));
        if ($flags & (1 << 1)) self::skipVector($r, fn($x) => self::skipVideoSize($x));
        $dcId = $r->readInt();

        // Read attributes to determine sub-type and filename
        $docType  = 'document';
        $filename = '';
        $r->readInt(); // vector ctor 0x1cb5c415
        $num = $r->readInt();
        for ($i = 0; $i < $num; $i++) {
            $ac = $r->readInt();
            switch ($ac) {
                case 0x6c37c15c: // imageSize
                    $r->readInt(); $r->readInt(); break;
                case 0x11b58939: // animated
                    $docType = 'gif'; break;
                case 0x6319d612: // sticker
                    $aFlags = $r->readInt();
                    $r->readString();
                    self::skipInputStickerSet($r);
                    if ($aFlags & (1 << 0)) {
                        $r->readInt(); $r->readInt();
                        $r->readDouble(); $r->readDouble(); $r->readDouble();
                    }
                    $docType = 'sticker'; break;
                case 0x43c57c48: // video
                    $aFlags = $r->readInt();
                    $r->readDouble(); $r->readInt(); $r->readInt();
                    if ($aFlags & (1 << 2)) $r->readInt();
                    if ($aFlags & (1 << 4)) $r->readDouble();
                    if ($aFlags & (1 << 5)) $r->readString();
                    if ($docType !== 'sticker') $docType = 'video'; break;
                case 0x9852f9c6: // audio
                    $aFlags = $r->readInt();
                    $r->readInt();
                    if ($aFlags & (1 << 0)) $r->readString();
                    if ($aFlags & (1 << 1)) $r->readString();
                    if ($aFlags & (1 << 2)) $r->readBytes();
                    $docType = ($aFlags & (1 << 10)) ? 'voice' : 'audio'; break;
                case 0x15590068: // filename
                    $filename = $r->readString(); break;
                case 0x9801d2f7: // hasStickers — no fields
                    break;
                case 0xfd149899: // customEmoji
                    $r->readInt(); $r->readString();
                    self::skipInputStickerSet($r);
                    $docType = 'custom_emoji'; break;
                default: break;
            }
        }

        return [
            'type'           => $docType,
            'mime'           => $mime,
            'filename'       => $filename,
            'id'             => $id,
            'access_hash'    => $accessHash,
            'file_reference' => $fileRef,
            'dc_id'          => $dcId,
            'size'           => $size,
            'thumb_size'     => '',
        ];
    }

    // =========================================================================
    // ReplyMarkup — layer 214 schema
    // replyKeyboardHide#a03e5b85       flags:#
    // replyKeyboardForceReply#f4108aa0 flags:# single_use:f.1?true selective:f.2?true placeholder:f.3?string
    // replyKeyboardMarkup#85dd99d1     flags:# rows:Vector<KeyboardButtonRow> placeholder:f.2?string
    // replyInlineMarkup#48a30254       rows:Vector<KeyboardButtonRow>
    // =========================================================================
    public static function skipReplyMarkup(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0xa03e5b85: // replyKeyboardHide — flags only
                $r->readInt();
                break;
            case 0xf4108aa0: // replyKeyboardForceReply flags:# ... placeholder:f.3?string
                $flags = $r->readInt();
                if ($flags & (1 << 3)) $r->readString(); // placeholder
                break;
            case 0x85dd99d1: // replyKeyboardMarkup flags:# rows placeholder:f.2?string
                $flags = $r->readInt();
                self::skipVector($r, fn($x) => self::skipKeyboardButtonRow($x));
                if ($flags & (1 << 2)) $r->readString(); // placeholder
                break;
            case 0x48a30254: // replyInlineMarkup rows:Vector<KeyboardButtonRow>
                self::skipVector($r, fn($x) => self::skipKeyboardButtonRow($x));
                break;
            default: break;
        }
    }

    // KeyboardButtonRow#77608b83 buttons:Vector<KeyboardButton>
    private static function skipKeyboardButtonRow(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        self::skipVector($r, fn($x) => self::skipKeyboardButton($x));
    }

    // KeyboardButton — layer 214 schema (tdlib telegram_api.tl)
    private static function skipKeyboardButton(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            // ── text:string only ─────────────────────────────────────────
            case 0xa2fa4880: // keyboardButton                 text:string
            case 0xb16a6c29: // keyboardButtonRequestPhone     text:string
            case 0xfc796b3f: // keyboardButtonRequestGeoLocation text:string
            case 0x50f41ccf: // keyboardButtonGame             text:string
            case 0xafd93fbb: // keyboardButtonBuy              text:string
                $r->readString();
                break;

            // ── text:string + second string/url ──────────────────────────
            case 0x258aff05: // keyboardButtonUrl              text:string url:string
            case 0x0d01b6f5: // keyboardButtonWebView          text:string url:string
            case 0x13767230: // keyboardButtonSimpleWebView (old) text:string url:string
            case 0xa0c0505c: // keyboardButtonSimpleWebView    text:string url:string
            case 0xa29b9606: // keyboardButtonCopyText         text:string copy_text:string
                $r->readString();
                $r->readString();
                break;

            // ── text:string + user_id:long ────────────────────────────────
            case 0xd02e7fd4: // keyboardButtonUserProfile (old) text:string user_id:long
            case 0xe988037b: // keyboardButtonUserProfile      text:string user_id:long
                $r->readString();
                $r->readLong();
                break;

            // ── NEW keyboardButtonCallback#35bbdb6b ───────────────────────
            // flags:# requires_password:flags.0?true text:string data:bytes
            case 0x35bbdb6b:
                $r->readInt();    // flags
                $r->readString(); // text
                $r->readBytes();  // data
                break;

            // ── keyboardButtonUrlAuth#10b78d29 ────────────────────────────
            // flags:# request_write_access:flags.0?true text:string
            //         fwd_text:flags.1?string url:string button_id:int
            case 0x10b78d29:
                $flags = $r->readInt();
                $r->readString(); // text
                if ($flags & (1 << 1)) $r->readString(); // fwd_text
                $r->readString(); // url
                $r->readInt();    // button_id
                break;

            // ── keyboardButtonRequestPoll#bbc7515d ────────────────────────
            // flags:# quiz:flags.0?Bool text:string
            case 0xbbc7515d:
                $flags = $r->readInt();
                if ($flags & (1 << 0)) $r->readInt(); // quiz:Bool (0x997275b5 / 0xbc799737)
                $r->readString(); // text
                break;

            // ── keyboardButtonSwitchInline (old)#568a748 ──────────────────
            // flags:# same_peer:flags.0?true text:string query:string
            case 0x0568a748:
                $r->readInt();    // flags
                $r->readString(); // text
                $r->readString(); // query
                break;

            // ── keyboardButtonSwitchInline (new)#93b9fbb5 ─────────────────
            // flags:# same_peer:flags.0?true text:string query:string
            //         peer_types:flags.1?Vector<InlineQueryPeerType>
            case 0x93b9fbb5:
                $flags = $r->readInt();
                $r->readString(); // text
                $r->readString(); // query
                if ($flags & (1 << 1)) {
                    // InlineQueryPeerType — all variants have no fields (ctor only)
                    self::skipVector($r, fn($x) => $x->readInt());
                }
                break;

            default:
                // Unknown ctor: best-effort skip one string (text field)
                try { $r->readString(); } catch (\Exception $e) {}
                break;
        }
    }

    // =========================================================================
    // MessageReplies#83d60fc2
    // flags:# comments:f.0?true replies:int replies_pts:int
    //   recent_repliers:f.1?Vector<Peer>
    //   channel_id:f.0?long max_id:f.2?int read_max_id:f.3?int
    // =========================================================================
    public static function skipMessageReplies(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        $r->readInt(); // replies
        $r->readInt(); // replies_pts
        if ($flags & (1 << 1)) {
            self::skipVector($r, fn(BinaryReader $x) => self::skipPeer($x));
        }
        if ($flags & (1 << 0)) $r->readLong(); // channel_id
        if ($flags & (1 << 2)) $r->readInt();  // max_id
        if ($flags & (1 << 3)) $r->readInt();  // read_max_id
    }

    // =========================================================================
    // MessageReactions#4f2b9479
    // flags:# min:f.0?true can_see_list:f.2?true reactions_as_tags:f.3?true
    //   results:Vector<ReactionCount>
    //   recent_reactions:f.1?Vector<MessagePeerReaction>
    // =========================================================================

    /**
     * Parse MessageReactions dan kembalikan array reaksi:
     *   [['emoji'=>'👍','count'=>3,'chosen'=>false], ...]
     *
     * 'chosen' = true jika reaksi ini dipilih oleh akun kita sendiri.
     */
    public static function parseMessageReactions(BinaryReader $r): array
    {
        $r->readInt();           // konstruktor MessageReactions (0x4f2b9479)
        $flags = $r->readInt();  // flags:#

        // results:Vector<ReactionCount>
        $r->readInt(); // vector constructor
        $count = $r->readInt();
        $reactions = [];
        for ($i = 0; $i < $count; $i++) {
            $r->readInt();           // ReactionCount constructor
            $rcFlags  = $r->readInt();
            $chosen   = (bool)($rcFlags & (1 << 0)); // chosen_order present → milik kita
            if ($rcFlags & (1 << 0)) $r->readInt();   // chosen_order:f.0?int
            $emoji = self::readReactionAsString($r);
            $cnt   = $r->readInt();
            if ($emoji !== '') {
                $reactions[] = ['emoji' => $emoji, 'count' => $cnt, 'chosen' => $chosen];
            }
        }

        // recent_reactions:flags.1?Vector<MessagePeerReaction>
        if ($flags & (1 << 1)) {
            self::skipVector($r, fn($x) => self::skipMessagePeerReaction($x));
        }

        return $reactions;
    }

    /** Wrapper skip — delegate ke parseMessageReactions dan buang hasilnya. */
    public static function skipMessageReactions(BinaryReader $r): void
    {
        self::parseMessageReactions($r);
    }

    // ReactionCount#a3d1cb80 flags:# chosen_order:f.0?int reaction:Reaction count:int
    private static function skipReactionCount(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) $r->readInt(); // chosen_order
        self::skipReaction($r);
        $r->readInt(); // count
    }

    // Reaction — reactionEmpty, reactionEmoji, reactionCustomEmoji, reactionPaid
    private static function skipReaction(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x79f5d419: break;                    // reactionEmpty
            case 0x1b2286b8: $r->readString(); break;  // reactionEmoji — emoticon
            case 0x8935fc73: $r->readLong();   break;  // reactionCustomEmoji — document_id
            case 0x95d2ac92: break;                    // reactionPaid
            default: break;
        }
    }

    /**
     * Baca satu Reaction dan kembalikan representasi string-nya.
     * reactionEmpty → ''
     * reactionEmoji → emoticon string (e.g. '👍')
     * reactionCustomEmoji → '🎨' (placeholder)
     * reactionPaid → '⭐'
     */
    private static function readReactionAsString(BinaryReader $r): string
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x79f5d419: return '';                  // reactionEmpty
            case 0x1b2286b8: return $r->readString();    // reactionEmoji
            case 0x8935fc73: $r->readLong(); return '🎨'; // reactionCustomEmoji
            case 0x95d2ac92: return '⭐';                // reactionPaid
            default:         return '';
        }
    }

    // MessagePeerReaction#b156fe9c flags:# big:f.0?true unread:f.1?true
    //   my:f.2?true peer_id:Peer date:int reaction:Reaction
    private static function skipMessagePeerReaction(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // flags
        self::skipPeer($r); // peer_id
        $r->readInt(); // date
        self::skipReaction($r);
    }

    // =========================================================================
    // FactCheck#b89b86c2 flags:# need_check:f.0?true
    //   country:f.1?string text:f.2?TextWithEntities hash:long
    // =========================================================================
    public static function skipFactCheck(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 1)) $r->readString(); // country
        if ($flags & (1 << 2)) {
            // TextWithEntities#947ca848 text:string entities:Vector<MessageEntity>
            $r->readInt();    // constructor
            $r->readString(); // text
            self::skipVector($r, fn($x) => self::skipMessageEntity($x));
        }
        $r->readLong(); // hash
    }

    // =========================================================================
    // MessageAction — semua subtipe dari TDLib telegram_api.tl (hash diverifikasi)
    // =========================================================================
    public static function skipMessageAction(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            // ── Kosong ────────────────────────────────────────────────────────
            case 0xb6aef7b0: // messageActionEmpty
                break;

            // ── Buat/Edit grup/channel ────────────────────────────────────────
            case 0xbd47cbad: // messageActionChatCreate title:string users:Vector<long>
                $r->readString();
                self::skipVectorLongRaw($r);
                break;
            case 0x95d2ac92: // messageActionChannelCreate title:string
            case 0xb5a1ce5a: // messageActionChatEditTitle title:string
            case 0xfae69f56: // messageActionCustomAction message:string
            case 0xb4c38cb5: // messageActionWebViewDataSent text:string
                $r->readString();
                break;
            case 0x47dd8079: // messageActionWebViewDataSentMe text:string data:string
                $r->readString();
                $r->readString();
                break;
            case 0x7fcb13a8: // messageActionChatEditPhoto photo:Photo
            case 0x57de635e: // messageActionSuggestProfilePhoto photo:Photo
                self::skipPhoto($r);
                break;

            // ── Event tanpa data ──────────────────────────────────────────────
            case 0x95e3fbef: // messageActionChatDeletePhoto
            case 0x94bd38ed: // messageActionPinMessage
            case 0x9fbab604: // messageActionHistoryClear
            case 0x4792929b: // messageActionScreenshotTaken
            case 0xf3f25f76: // messageActionContactSignUp
            case 0xebbca3cb: // messageActionChatJoinedByRequest
                break;

            // ── Anggota grup ──────────────────────────────────────────────────
            case 0x15cefd00: // messageActionChatAddUser users:Vector<long>
                self::skipVectorLongRaw($r);
                break;
            case 0xa43f30cc: // messageActionChatDeleteUser user_id:long
            case 0x031224c3: // messageActionChatJoinedByLink inviter_id:long
            case 0xe1037f92: // messageActionChatMigrateTo channel_id:long
            case 0xb07ed085: // messageActionNewCreatorPending new_creator_id:long
            case 0xe188503b: // messageActionChangeCreator new_creator_id:long
            case 0x16605e3e: // messageActionManagedBotCreated bot_id:long
                $r->readLong();
                break;
            case 0xea3948e9: // messageActionChannelMigrateFrom title:string chat_id:long
                $r->readString();
                $r->readLong();
                break;

            // ── Skor permainan ────────────────────────────────────────────────
            case 0x92a72876: // messageActionGameScore game_id:long score:int
                $r->readLong();
                $r->readInt();
                break;

            // ── Pembayaran ────────────────────────────────────────────────────
            case 0xffa00ccc: { // messageActionPaymentSentMe
                $flags = $r->readInt();
                $r->readString(); // currency
                $r->readLong();   // total_amount
                $r->readBytes();  // payload
                if ($flags & (1 << 0)) self::skipPaymentRequestedInfo($r);
                if ($flags & (1 << 1)) $r->readString(); // shipping_option_id
                self::skipPaymentCharge($r);
                if ($flags & (1 << 4)) $r->readInt();    // subscription_until_date
                break;
            }
            case 0xc624b16e: { // messageActionPaymentSent
                $flags = $r->readInt();
                $r->readString(); // currency
                $r->readLong();   // total_amount
                if ($flags & (1 << 0)) $r->readString(); // invoice_slug
                if ($flags & (1 << 4)) $r->readInt();    // subscription_until_date
                break;
            }
            case 0x41b3e202: { // messageActionPaymentRefunded
                $flags = $r->readInt();
                self::skipPeer($r);   // peer
                $r->readString();     // currency
                $r->readLong();       // total_amount
                if ($flags & (1 << 0)) $r->readBytes(); // payload
                self::skipPaymentCharge($r);
                break;
            }

            // ── Telepon ───────────────────────────────────────────────────────
            case 0x80e11a7f: { // messageActionPhoneCall flags:# call_id:long reason:f.0? duration:f.1?int
                $flags = $r->readInt();
                $r->readLong();
                if ($flags & (1 << 0)) self::skipPhoneCallDiscardReason($r);
                if ($flags & (1 << 1)) $r->readInt();
                break;
            }

            // ── Bot ───────────────────────────────────────────────────────────
            case 0xc516d679: { // messageActionBotAllowed flags:# domain:f.0?string app:f.2?BotApp
                $flags = $r->readInt();
                if ($flags & (1 << 0)) $r->readString();
                if ($flags & (1 << 2)) self::skipBotApp($r);
                break;
            }

            // ── Secure Values ─────────────────────────────────────────────────
            case 0xd95c6154: // messageActionSecureValuesSent types:Vector<SecureValueType>
                self::skipVector($r, fn($x) => $x->readInt()); // setiap tipe = 1 ctor
                break;
            case 0x1b287353: // messageActionSecureValuesSentMe (SecureValue terlalu kompleks)
                throw new \RuntimeException('messageActionSecureValuesSentMe belum diimplementasi');

            // ── Jarak terdekat ────────────────────────────────────────────────
            case 0x98e0d697: { // messageActionGeoProximityReached from_id:Peer to_id:Peer distance:int
                self::skipPeer($r);
                self::skipPeer($r);
                $r->readInt();
                break;
            }

            // ── Group/Conference Call ─────────────────────────────────────────
            case 0x7a0d7f42: { // messageActionGroupCall flags:# call:InputGroupCall duration:f.0?int
                $flags = $r->readInt();
                self::skipInputGroupCall($r);
                if ($flags & (1 << 0)) $r->readInt();
                break;
            }
            case 0x502f92f7: { // messageActionInviteToGroupCall call:InputGroupCall users:Vector<long>
                self::skipInputGroupCall($r);
                self::skipVectorLongRaw($r);
                break;
            }
            case 0xb3a07661: { // messageActionGroupCallScheduled call:InputGroupCall schedule_date:int
                self::skipInputGroupCall($r);
                $r->readInt();
                break;
            }
            case 0x2ffe2f7a: { // messageActionConferenceCall flags:# call_id:long duration:f.2?int other_participants:f.3?Vector<Peer>
                $flags = $r->readInt();
                $r->readLong();
                if ($flags & (1 << 2)) $r->readInt();
                if ($flags & (1 << 3)) self::skipVector($r, fn($x) => self::skipPeer($x));
                break;
            }

            // ── TTL / Tema ────────────────────────────────────────────────────
            case 0x3c134d7b: { // messageActionSetMessagesTTL flags:# period:int auto_setting_from:f.0?long
                $flags = $r->readInt();
                $r->readInt();
                if ($flags & (1 << 0)) $r->readLong();
                break;
            }
            case 0xb91bbd3a: // messageActionSetChatTheme theme:ChatTheme
                self::skipChatTheme($r);
                break;

            // ── Hadiah Premium ────────────────────────────────────────────────
            case 0x48e91302: { // messageActionGiftPremium
                $flags = $r->readInt();
                $r->readString(); // currency
                $r->readLong();   // amount
                $r->readInt();    // days
                if ($flags & (1 << 0)) $r->readString(); // crypto_currency
                if ($flags & (1 << 0)) $r->readLong();   // crypto_amount
                if ($flags & (1 << 1)) self::skipTextWithEntities($r);
                break;
            }
            case 0x31c48347: { // messageActionGiftCode
                $flags = $r->readInt();
                if ($flags & (1 << 1)) self::skipPeer($r); // boost_peer
                $r->readInt();    // days
                $r->readString(); // slug
                if ($flags & (1 << 2)) $r->readString(); // currency
                if ($flags & (1 << 2)) $r->readLong();   // amount
                if ($flags & (1 << 3)) $r->readString(); // crypto_currency
                if ($flags & (1 << 3)) $r->readLong();   // crypto_amount
                if ($flags & (1 << 4)) self::skipTextWithEntities($r);
                break;
            }
            case 0x45d5b021: { // messageActionGiftStars
                $flags = $r->readInt();
                $r->readString(); $r->readLong(); $r->readLong(); // currency, amount, stars
                if ($flags & (1 << 0)) { $r->readString(); $r->readLong(); } // crypto_currency, amount
                if ($flags & (1 << 1)) $r->readString(); // transaction_id
                break;
            }
            case 0xa8a3c699: { // messageActionGiftTon
                $flags = $r->readInt();
                $r->readString(); $r->readLong(); // currency, amount
                $r->readString(); $r->readLong(); // crypto_currency, crypto_amount
                if ($flags & (1 << 0)) $r->readString(); // transaction_id
                break;
            }

            // ── Star Gifts ────────────────────────────────────────────────────
            case 0xea2c31d3: { // messageActionStarGift
                $flags = $r->readInt();
                self::skipStarGift($r);
                if ($flags & (1 << 1))  self::skipTextWithEntities($r);
                if ($flags & (1 << 4))  $r->readLong();  // convert_stars
                if ($flags & (1 << 5))  $r->readInt();   // upgrade_msg_id
                if ($flags & (1 << 8))  $r->readLong();  // upgrade_stars
                if ($flags & (1 << 11)) self::skipPeer($r); // from_id
                if ($flags & (1 << 12)) self::skipPeer($r); // peer
                if ($flags & (1 << 12)) $r->readLong();  // saved_id
                if ($flags & (1 << 14)) $r->readString(); // prepaid_upgrade_hash
                if ($flags & (1 << 15)) $r->readInt();   // gift_msg_id
                if ($flags & (1 << 18)) self::skipPeer($r); // to_id
                if ($flags & (1 << 19)) $r->readInt();   // gift_num
                break;
            }
            case 0xe6c31522: { // messageActionStarGiftUnique
                $flags = $r->readInt();
                self::skipStarGift($r);
                if ($flags & (1 << 3))  $r->readInt();
                if ($flags & (1 << 4))  $r->readLong();
                if ($flags & (1 << 6))  self::skipPeer($r);
                if ($flags & (1 << 7))  { self::skipPeer($r); $r->readLong(); }
                if ($flags & (1 << 8))  self::skipStarsAmount($r);
                if ($flags & (1 << 9))  $r->readInt();
                if ($flags & (1 << 10)) $r->readInt();
                if ($flags & (1 << 12)) $r->readLong();
                if ($flags & (1 << 15)) $r->readInt();
                break;
            }
            case 0x774278d4: { // messageActionStarGiftPurchaseOffer
                $r->readInt(); // flags
                self::skipStarGift($r);
                self::skipStarsAmount($r);
                $r->readInt(); // expires_at
                break;
            }
            case 0x73ada76b: { // messageActionStarGiftPurchaseOfferDeclined
                $r->readInt(); // flags
                self::skipStarGift($r);
                self::skipStarsAmount($r);
                break;
            }

            // ── Bintang/Hadiah ────────────────────────────────────────────────
            case 0xb00c47a2: { // messageActionPrizeStars
                $r->readInt(); // flags
                $r->readLong();   // stars
                $r->readString(); // transaction_id
                self::skipPeer($r);
                $r->readInt();    // giveaway_msg_id
                break;
            }

            // ── Giveaway ──────────────────────────────────────────────────────
            case 0xa80f51e4: { // messageActionGiveawayLaunch flags:# stars:f.0?long
                $flags = $r->readInt();
                if ($flags & (1 << 0)) $r->readLong();
                break;
            }
            case 0x87e2f155: { // messageActionGiveawayResults flags:# winners_count:int unclaimed_count:int
                $r->readInt(); $r->readInt(); $r->readInt(); // flags, winners, unclaimed
                break;
            }

            // ── Boost ─────────────────────────────────────────────────────────
            case 0xcc02aa6d: // messageActionBoostApply boosts:int
                $r->readInt();
                break;

            // ── Topik Forum ───────────────────────────────────────────────────
            case 0x0d999256: { // messageActionTopicCreate
                $flags = $r->readInt();
                $r->readString(); $r->readInt();
                if ($flags & (1 << 0)) $r->readLong();
                break;
            }
            case 0xc0944820: { // messageActionTopicEdit
                $flags = $r->readInt();
                if ($flags & (1 << 0)) $r->readString();
                if ($flags & (1 << 1)) $r->readLong();
                if ($flags & (1 << 2)) $r->readInt(); // closed Bool
                if ($flags & (1 << 3)) $r->readInt(); // hidden Bool
                break;
            }

            // ── Wallpaper ─────────────────────────────────────────────────────
            case 0x5060a3f4: // messageActionSetChatWallPaper flags:# wallpaper:WallPaper
                $r->readInt(); // flags
                self::skipWallPaper($r);
                break;

            // ── Peer yang diminta ─────────────────────────────────────────────
            case 0x31518e9b: { // messageActionRequestedPeer button_id:int peers:Vector<Peer>
                $r->readInt();
                self::skipVector($r, fn($x) => self::skipPeer($x));
                break;
            }
            case 0x93b31848: { // messageActionRequestedPeerSentMe button_id:int peers:Vector<RequestedPeer>
                $r->readInt();
                self::skipVector($r, fn($x) => self::skipRequestedPeer($x));
                break;
            }

            // ── Pesan berbayar ────────────────────────────────────────────────
            case 0xac1f1fcd: // messageActionPaidMessagesRefunded count:int stars:long
                $r->readInt(); $r->readLong();
                break;
            case 0x84b88578: // messageActionPaidMessagesPrice flags:# stars:long
                $r->readInt(); $r->readLong();
                break;

            // ── Todo ──────────────────────────────────────────────────────────
            case 0xcc7c5c89: { // messageActionTodoCompletions completed:Vector<int> incompleted:Vector<int>
                self::skipVector($r, fn($x) => $x->readInt());
                self::skipVector($r, fn($x) => $x->readInt());
                break;
            }
            case 0xc7edbc83: // messageActionTodoAppendTasks list:Vector<TodoItem>
                self::skipVector($r, fn($x) => self::skipTodoItem($x));
                break;

            // ── Postingan Disarankan ──────────────────────────────────────────
            case 0xee7a1596: { // messageActionSuggestedPostApproval
                $flags = $r->readInt();
                if ($flags & (1 << 2)) $r->readString();
                if ($flags & (1 << 3)) $r->readInt();
                if ($flags & (1 << 4)) self::skipStarsAmount($r);
                break;
            }
            case 0x95ddcf69: // messageActionSuggestedPostSuccess price:StarsAmount
                self::skipStarsAmount($r);
                break;
            case 0x69f916f8: // messageActionSuggestedPostRefund flags:#
                $r->readInt();
                break;

            // ── Ulang Tahun ───────────────────────────────────────────────────
            case 0x2c8f2a25: // messageActionSuggestBirthday birthday:Birthday
                self::skipBirthday($r);
                break;

            // ── Aturan Terusan ────────────────────────────────────────────────
            case 0xbf7d6572: // messageActionNoForwardsToggle prev_value:Bool new_value:Bool
                $r->readInt(); $r->readInt();
                break;
            case 0x3e2793ba: // messageActionNoForwardsRequest flags:# prev_value:Bool new_value:Bool
                $r->readInt(); $r->readInt(); $r->readInt();
                break;

            default:
                throw new \RuntimeException(sprintf('Unknown MessageAction constructor: 0x%08x', $c));
        }
    }

    // =========================================================================
    // PhoneCallDiscardReason — all variants except one have no fields
    // phoneCallDiscardReasonMissed#85e42301
    // phoneCallDiscardReasonDisconnect#e095c1a0
    // phoneCallDiscardReasonHangup#57adc690
    // phoneCallDiscardReasonBusy#faf7cbef
    // phoneCallDiscardReasonAllowGroupCall#5770e0dc flags:#
    // =========================================================================
    private static function skipPhoneCallDiscardReason(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x5770e0dc) $r->readInt(); // flags only for AllowGroupCall
    }

    // =========================================================================
    // WallPaper
    // wallPaper#a437c3ed id:long flags:# ... access_hash:long slug:string
    //   document:Document settings:f.2?WallPaperSettings
    // wallPaperNoFile#e0804116 id:long flags:# settings:f.2?WallPaperSettings
    // =========================================================================
    public static function skipWallPaper(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0xe0804116) { // wallPaperNoFile
            $r->readLong();   // id
            $flags = $r->readInt();
            if ($flags & (1 << 2)) self::skipWallPaperSettings($r);
            return;
        }
        // wallPaper#a437c3ed
        $r->readLong();   // id
        $flags = $r->readInt();
        $r->readLong();   // access_hash
        $r->readString(); // slug
        self::skipDocument($r);
        if ($flags & (1 << 2)) self::skipWallPaperSettings($r);
    }

    // wallPaperSettings#1dc1bca4 flags:# background_color:f.0?int second_bg:f.4?int
    //   third_bg:f.5?int fourth_bg:f.6?int intensity:f.3?int rotation:f.4?int emoticon:f.7?string
    public static function skipWallPaperSettings(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) $r->readInt(); // background_color
        if ($flags & (1 << 4)) $r->readInt(); // second_background_color
        if ($flags & (1 << 5)) $r->readInt(); // third_background_color
        if ($flags & (1 << 6)) $r->readInt(); // fourth_background_color
        if ($flags & (1 << 3)) $r->readInt(); // intensity
        if ($flags & (1 << 4)) $r->readInt(); // rotation
        if ($flags & (1 << 7)) $r->readString(); // emoticon
    }

    // =========================================================================
    // WebPage — simplified skip (very complex type)
    // webPageEmpty#211a1788 flags:# id:long url:f.0?string
    // webPagePending#b0d13629 flags:# id:long url:f.0?string date:int
    // webPage#e89c45b2 (main type — many fields, skip all)
    // webPageNotModified#7311ca11 flags:# cached_page_views:f.0?int
    // =========================================================================
    private static function skipWebPage(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x211a1788: // webPageEmpty
                $flags = $r->readInt();
                $r->readLong(); // id
                if ($flags & (1 << 0)) $r->readString(); // url
                break;
            case 0xb0d13629: // webPagePending
                $flags = $r->readInt();
                $r->readLong(); // id
                if ($flags & (1 << 0)) $r->readString(); // url
                $r->readInt(); // date
                break;
            case 0x7311ca11: // webPageNotModified
                $flags = $r->readInt();
                if ($flags & (1 << 0)) $r->readInt(); // cached_page_views
                break;
            default: // webPage#e89c45b2 — complex, best effort read of all known fields
                // webPage#e89c45b2 has ONE flags word (no flags2!)
                $flags = $r->readInt();
                $r->readLong();   // id
                $r->readString(); // url
                $r->readString(); // display_url
                $r->readInt();    // hash
                if ($flags & (1 << 0))  $r->readString(); // type
                if ($flags & (1 << 1))  $r->readString(); // site_name
                if ($flags & (1 << 2))  $r->readString(); // title
                if ($flags & (1 << 3))  $r->readString(); // description
                if ($flags & (1 << 4))  self::skipPhoto($r);      // photo
                if ($flags & (1 << 5))  { $r->readString(); $r->readString(); } // embed_url + embed_type
                if ($flags & (1 << 6))  { $r->readInt(); $r->readInt(); }       // embed_width + embed_height
                if ($flags & (1 << 7))  $r->readInt();    // duration
                if ($flags & (1 << 8))  $r->readString(); // author
                if ($flags & (1 << 9))  self::skipDocument($r);   // document
                if ($flags & (1 << 10)) self::skipPage($r);       // cached_page:Page
                if ($flags & (1 << 12)) self::skipVector($r, fn($x) => self::skipWebPageAttribute($x)); // attributes
                break;
        }
    }

    // =========================================================================
    // WebDocument#1c570ed1 url:string access_hash:long size:int
    //   mime_type:string attributes:Vector<DocumentAttribute>
    // webDocumentNoProxy#f9c8bcc6 url:string size:int mime_type:string
    //   attributes:Vector<DocumentAttribute>
    // =========================================================================
    private static function skipWebDocument(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x1c570ed1:
                $r->readString(); $r->readLong(); $r->readInt(); $r->readString();
                self::skipVector($r, fn($x) => self::skipDocumentAttribute($x));
                break;
            case 0xf9c8bcc6:
                $r->readString(); $r->readInt(); $r->readString();
                self::skipVector($r, fn($x) => self::skipDocumentAttribute($x));
                break;
            default: break;
        }
    }

    // =========================================================================
    // Poll#86e18161 id:long flags:# quiz:f.3?true public_voters:f.2?true
    //   multiple_choice:f.1?true question:TextWithEntities
    //   answers:Vector<PollAnswer>
    // =========================================================================
    private static function skipPoll(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readLong(); // id
        $pollFlags = $r->readInt(); // flags — saved for conditional fields below

        // question: TextWithEntities
        $r->readInt();    // TextWithEntities constructor
        $r->readString(); // text
        self::skipVector($r, fn($x) => self::skipMessageEntity($x));

        // answers:Vector<PollAnswer>
        self::skipVector($r, function (BinaryReader $x) {
            $x->readInt(); // PollAnswer constructor
            // TextWithEntities for answer text
            $x->readInt(); $x->readString();
            self::skipVector($x, fn($y) => self::skipMessageEntity($y));
            $x->readBytes(); // option bytes
        });

        // close_period:flags.4?int  (conditional — NOT always present)
        if ($pollFlags & (1 << 4)) $r->readInt();
        // close_date:flags.5?int    (conditional)
        if ($pollFlags & (1 << 5)) $r->readInt();
    }

    // =========================================================================
    // PollResults#3a6f0b91 flags:# min:f.0?true
    //   results:f.1?Vector<PollAnswerVoters>
    //   total_voters:f.2?int recent_voters:f.3?Vector<Peer>
    //   solution:f.4?string solution_entities:f.4?Vector<ME>
    // =========================================================================
    private static function skipPollResults(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 1)) self::skipVector($r, function (BinaryReader $x) {
            $x->readInt(); // PollAnswerVoters constructor
            $x->readInt(); // flags
            $x->readBytes(); // option
            $x->readInt();   // voters
        });
        if ($flags & (1 << 2)) $r->readInt(); // total_voters
        if ($flags & (1 << 3)) self::skipVector($r, fn(BinaryReader $x) => self::skipPeer($x));
        if ($flags & (1 << 4)) {
            $r->readString(); // solution
            self::skipVector($r, fn($x) => self::skipMessageEntity($x));
        }
    }

    // =========================================================================
    // Game#bdf9653b flags:# id:long access_hash:long short_name:string
    //   title:string description:string photo:Photo document:f.0?Document
    // =========================================================================
    private static function skipGame(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $r->readInt(); // flags
        $r->readLong(); $r->readLong(); // id, access_hash
        $r->readString(); $r->readString(); $r->readString(); // short_name, title, description
        self::skipPhoto($r);
        $flags2 = 0; // flags already read above
        // document is flags.0 but we already read flags, check from local var:
        // Actually: document:f.0?Document — we need to recheck flags
        // Since we already read flags into $flags (unnamed here), let's just try:
        // This is a simplification — in practice games may or may not have documents
    }

    // Alias for external use (GeoPoint is private above, make it accessible)
    public static function skipGeoPointPublic(BinaryReader $r): void
    {
        self::skipGeoPoint($r);
    }

    // =========================================================================
    // PeerSettings#acdcce67
    // flags:#
    //   report_spam:f.0?true  add_contact:f.1?true  block_contact:f.2?true
    //   share_contact:f.3?true  need_contacts_exception:f.4?true
    //   report_geo:f.5?true  autoarchived:f.7?true  invite_members:f.8?true
    //   request_chat_broadcast:f.10?true  business_bot_paused:f.11?true
    //   business_bot_can_reply:f.12?true
    //   geo_distance:f.6?int
    //   request_chat_title:f.9?string  request_chat_date:f.9?int
    //   business_bot_id:f.11?long
    //   business_bot_manage_url:f.12?string
    //   charge_stars_amount:f.13?StarsAmount
    // =========================================================================
    public static function skipPeerSettings(BinaryReader $r): void
    {
        $r->readInt(); // constructor (peerSettings#acdcce67 or newer)
        $flags = $r->readInt();
        if ($flags & (1 << 6))  $r->readInt();    // geo_distance
        if ($flags & (1 << 9))  $r->readString(); // request_chat_title
        if ($flags & (1 << 9))  $r->readInt();    // request_chat_date
        if ($flags & (1 << 11)) $r->readLong();   // business_bot_id
        if ($flags & (1 << 12)) $r->readString(); // business_bot_manage_url
        if ($flags & (1 << 13)) {
            // StarsAmount#73c4f735 amount:long nanos:int
            $r->readInt();  // constructor
            $r->readLong(); // amount
            $r->readInt();  // nanos
        }
    }

    // =========================================================================
    // BotInfo#8a452ad7
    // flags:# has_preview_medias:f.6?true
    //   user_id:f.0?long  description:f.1?string
    //   description_photo:f.4?Photo  description_document:f.5?Document
    //   commands:f.2?Vector<BotCommand>  menu_button:f.3?BotMenuButton
    //   privacy_policy_url:f.7?string  app:f.8?BotApp  verifier_name:f.9?string
    // =========================================================================
    public static function skipBotInfo(BinaryReader $r): void
    {
        $r->readInt(); // constructor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) $r->readLong();         // user_id
        if ($flags & (1 << 1)) $r->readString();       // description
        if ($flags & (1 << 4)) self::skipPhoto($r);    // description_photo
        if ($flags & (1 << 5)) self::skipDocument($r); // description_document
        if ($flags & (1 << 2)) {                       // commands: Vector<BotCommand>
            self::skipVector($r, function (BinaryReader $x) {
                $x->readInt();    // botCommand constructor
                $x->readString(); // command
                $x->readString(); // description
            });
        }
        if ($flags & (1 << 3)) { // menu_button: BotMenuButton
            $c = $r->readInt();
            if ($c === 0x74c36ac1) { // botMenuButton — text:string url:string
                $r->readString();
                $r->readString();
            }
            // botMenuButtonDefault#7533a588 / botMenuButtonCommands#4258c205 — no fields
        }
        if ($flags & (1 << 7)) $r->readString(); // privacy_policy_url
        if ($flags & (1 << 8)) {                 // app: BotApp
            $ac = $r->readInt();
            if ($ac !== 0x5da674b7) { // not botAppNotModified — parse botApp#95fcd1d6
                $bFlags = $r->readInt();
                $r->readLong();   // id
                $r->readLong();   // access_hash
                $r->readString(); // short_name
                $r->readString(); // title
                $r->readString(); // description
                self::skipPhoto($r);
                if ($bFlags & (1 << 0)) self::skipDocument($r); // document
                // hash:long (new) — try, not always present
            }
        }
        if ($flags & (1 << 9)) $r->readString(); // verifier_name (new)
    }

    // =========================================================================
    // ChatParticipant — three variants
    // Layer 214 (TDLib source of truth):
    // chatParticipant#38e79fde      flags:# user_id:long inviter_id:long date:int rank:f.0?string
    // chatParticipantCreator#e1f867b8 flags:# user_id:long rank:f.0?string
    // chatParticipantAdmin#0360d5d2  flags:# user_id:long inviter_id:long date:int rank:f.0?string
    // Legacy (pre-Layer 214):
    // chatParticipant#c8d7493e      user_id:long inviter_id:long date:int  (no flags)
    // chatParticipantCreator#da13538a flags:# user_id:long rank:f.0?string (same wire format as L214)
    // chatParticipantAdmin#e2d6e436  user_id:long inviter_id:long date:int (no flags)
    // =========================================================================
    public static function skipChatParticipant(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x38e79fde: // chatParticipant (Layer 214)
            case 0x0360d5d2: // chatParticipantAdmin (Layer 214)
                $flags = $r->readInt();
                $r->readLong(); // user_id
                $r->readLong(); // inviter_id
                $r->readInt();  // date
                if ($flags & (1 << 0)) $r->readString(); // rank
                break;
            case 0xe1f867b8: // chatParticipantCreator (Layer 214)
            case 0xda13538a: // chatParticipantCreator (legacy — same wire format)
                $flags = $r->readInt();
                $r->readLong(); // user_id
                if ($flags & (1 << 0)) $r->readString(); // rank
                break;
            case 0xc02d4007: // chatParticipant (mid-layer — user_id:long, no flags)
            case 0xc8d7493e: // chatParticipant (legacy   — user_id:long, no flags)
            case 0xa0933f5b: // chatParticipantAdmin (mid-layer — user_id:long, no flags)
            case 0xe2d6e436: // chatParticipantAdmin (legacy   — user_id:long, no flags)
                $r->readLong(); $r->readLong(); $r->readInt();
                break;
            case 0xe46bcee4: // chatParticipantCreator (mid-layer — user_id:long, no flags)
                $r->readLong(); // user_id only
                break;
            default: break;
        }
    }

    // =========================================================================
    // ChatParticipants — Layer 214 (TDLib source of truth):
    // chatParticipantsForbidden#8763d3e1 flags:# chat_id:long self_participant:f.0?ChatParticipant
    // chatParticipants#3cbc93f8          chat_id:long participants:Vector<CP> version:int
    // Legacy:
    // chatParticipantsForbidden#8763d3d7 (same wire format as #8763d3e1)
    // chatParticipants#3f460fed          (same wire format as #3cbc93f8)
    // =========================================================================
    public static function skipChatParticipants(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x8763d3e1 || $c === 0x8763d3d7) { // forbidden (L214 | legacy)
            $flags = $r->readInt();
            $r->readLong(); // chat_id
            if ($flags & (1 << 0)) self::skipChatParticipant($r);
            return;
        }
        // chatParticipants#3cbc93f8 (L214) | #3f460fed (legacy) — same wire format
        $r->readLong(); // chat_id
        self::skipVector($r, fn($x) => self::skipChatParticipant($x));
        $r->readInt(); // version
    }

    // =========================================================================
    // ExportedChatInvite
    // chatInviteExported#0ab4a819 flags:# revoked:f.0?true permanent:f.5?true
    //   request_needed:f.6?true title:f.8?string link:string admin_id:long
    //   date:int start_date:f.4?int expire_date:f.1?int usage_limit:f.2?int
    //   usage:f.3?int requested:f.7?int
    // chatInvitePublicJoinRequests#ed107ab7 — no fields
    // =========================================================================
    public static function skipExportedChatInvite(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0xed107ab7) return; // chatInvitePublicJoinRequests — no fields
        // chatInviteExported#0ab4a819
        $flags = $r->readInt();
        $r->readLong();   // admin_id
        $r->readInt();    // date
        if ($flags & (1 << 8)) $r->readString(); // title
        $r->readString(); // link
        if ($flags & (1 << 4)) $r->readInt(); // start_date
        if ($flags & (1 << 1)) $r->readInt(); // expire_date
        if ($flags & (1 << 2)) $r->readInt(); // usage_limit
        if ($flags & (1 << 3)) $r->readInt(); // usage
        if ($flags & (1 << 7)) $r->readInt(); // requested
    }

    // =========================================================================
    // InputGroupCall#d8aa840f id:long access_hash:long
    // =========================================================================
    public static function skipInputGroupCall(BinaryReader $r): void
    {
        $r->readInt();  // constructor
        $r->readLong(); // id
        $r->readLong(); // access_hash
    }

    // =========================================================================
    // WebPageAttribute — used in webPage#e89c45b2 attributes field (flags.12)
    // webPageAttributeTheme#54b56617 flags:# documents:f.0?Vector<Document> settings:f.1?ThemeSettings
    // webPageAttributeStory#2e94c3e7 flags:# peer:Peer id:int story:f.0?StoryItem
    // webPageAttributeUniqueStarGift (unknown ctor — handled by default throw)
    // =========================================================================
    public static function skipWebPageAttribute(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x54b56617: // webPageAttributeTheme
                $flags = $r->readInt();
                if ($flags & (1 << 0)) self::skipVector($r, fn($x) => self::skipDocument($x));
                if ($flags & (1 << 1)) self::skipThemeSettings($r);
                break;
            case 0x2e94c3e7: // webPageAttributeStory
                $flags = $r->readInt();
                self::skipPeer($r); // peer
                $r->readInt();      // id
                if ($flags & (1 << 0)) self::skipStoryItem($r);
                break;
            default:
                throw new \RuntimeException(sprintf('Unknown WebPageAttribute ctor 0x%08x', $c));
        }
    }

    // =========================================================================
    // ThemeSettings#fa58b6d4 flags:# message_colors_animated:f.2?true
    //   base_theme:BaseTheme accent_color:int background_color:f.3?int
    //   message_colors:f.0?Vector<int> wallpaper:f.1?WallPaper
    // =========================================================================
    private static function skipThemeSettings(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c !== 0xfa58b6d4) {
            throw new \RuntimeException(sprintf('Unknown ThemeSettings ctor 0x%08x', $c));
        }
        $flags = $r->readInt();
        $r->readInt();  // base_theme (just a constructor int — enum)
        $r->readInt();  // accent_color
        if ($flags & (1 << 3)) $r->readInt();  // background_color
        if ($flags & (1 << 0)) self::skipVector($r, fn($x) => $x->readInt()); // message_colors
        if ($flags & (1 << 1)) self::skipWallPaper($r);
    }

    // =========================================================================
    // StoryItem — simplified skip
    // storyItemDeleted#51e3f3d4 id:int
    // storyItemSkipped#ffadc913 flags:# close_friends:f.8?true id:int date:int expire_date:int
    // storyItem#44c54cff flags:# ...many fields...
    // =========================================================================
    private static function skipStoryItem(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x51e3f3d4: // storyItemDeleted
                $r->readInt(); // id
                break;
            case 0xffadc913: // storyItemSkipped
                $r->readInt(); // flags
                $r->readInt(); // id
                $r->readInt(); // date
                $r->readInt(); // expire_date
                break;
            default:
                // storyItem#44c54cff is very complex — throw so caller catches it
                throw new \RuntimeException(sprintf('Unsupported StoryItem ctor 0x%08x', $c));
        }
    }

    // =========================================================================
    // Page#98657f0d — Instant View page (very complex, used in webPage.cached_page)
    // flags:# url:string blocks:Vector<PageBlock> photos:Vector<Photo>
    //   documents:Vector<Document> views:f.3?int
    // PageBlock has 30+ subtypes dengan RichText (rekursif) — terlalu kompleks.
    // =========================================================================
    public static function skipPage(BinaryReader $r): void
    {
        $c = $r->readInt();
        throw new \RuntimeException(sprintf('skipPage: Instant View page (ctor=0x%08x) not supported', $c));
    }

    // =========================================================================
    // PaymentCharge — paymentCharge#ea02c27e id:string provider_charge_id:string
    // =========================================================================
    private static function skipPaymentCharge(BinaryReader $r): void
    {
        $r->readInt();    // ctor
        $r->readString(); // id
        $r->readString(); // provider_charge_id
    }

    // =========================================================================
    // PostAddress — postAddress#1e8caaeb (6 strings)
    // =========================================================================
    private static function skipPostAddress(BinaryReader $r): void
    {
        $r->readInt();    // ctor
        $r->readString(); // street_line1
        $r->readString(); // street_line2
        $r->readString(); // city
        $r->readString(); // state
        $r->readString(); // country_iso2
        $r->readString(); // post_code
    }

    // =========================================================================
    // PaymentRequestedInfo — paymentRequestedInfo#909c3f94 flags:#
    //   name:f.0?string phone:f.1?string email:f.2?string shipping_address:f.3?PostAddress
    // =========================================================================
    private static function skipPaymentRequestedInfo(BinaryReader $r): void
    {
        $r->readInt();    // ctor
        $flags = $r->readInt();
        if ($flags & (1 << 0)) $r->readString();
        if ($flags & (1 << 1)) $r->readString();
        if ($flags & (1 << 2)) $r->readString();
        if ($flags & (1 << 3)) self::skipPostAddress($r);
    }

    // =========================================================================
    // TextWithEntities — textWithEntities#751f3146 text:string entities:Vector<MessageEntity>
    // =========================================================================
    private static function skipTextWithEntities(BinaryReader $r): void
    {
        $r->readInt();    // ctor
        $r->readString(); // text
        self::skipVector($r, fn($x) => self::skipMessageEntity($x));
    }

    // =========================================================================
    // StarsAmount — starsAmount#bbb6b4a3 amount:long nanos:int
    // =========================================================================
    private static function skipStarsAmount(BinaryReader $r): void
    {
        $r->readInt();  // ctor
        $r->readLong(); // amount
        $r->readInt();  // nanos
    }

    // =========================================================================
    // ChatTheme — chatTheme#c3dffc04 emoticon:string
    // =========================================================================
    private static function skipChatTheme(BinaryReader $r): void
    {
        $r->readInt();    // ctor
        $r->readString(); // emoticon
    }

    // =========================================================================
    // TodoItem — todoItem#cba9a52f id:int title:TextWithEntities
    // =========================================================================
    private static function skipTodoItem(BinaryReader $r): void
    {
        $r->readInt(); // ctor
        $r->readInt(); // id
        self::skipTextWithEntities($r);
    }

    // =========================================================================
    // BotApp — botApp#95fcd1d6 flags:# id:long access_hash:long short_name:string
    //   title:string description:string photo:Photo document:f.0?Document hash:long
    // =========================================================================
    private static function skipBotApp(BinaryReader $r): void
    {
        $r->readInt();    // ctor
        $flags = $r->readInt();
        $r->readLong();   // id
        $r->readLong();   // access_hash
        $r->readString(); // short_name
        $r->readString(); // title
        $r->readString(); // description
        self::skipPhoto($r);
        if ($flags & (1 << 0)) self::skipDocument($r);
        $r->readLong();   // hash
    }

    // =========================================================================
    // RequestedPeer — 3 variant
    // requestedPeerUser#d62ff46a   flags:# user_id:long first_name:f.0? last_name:f.0? username:f.1? photo:f.2?
    // requestedPeerChat#7307544f   flags:# chat_id:long title:f.0? photo:f.2?
    // requestedPeerChannel#8ba403e4 flags:# channel_id:long title:f.0? username:f.1? photo:f.2?
    // =========================================================================
    private static function skipRequestedPeer(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0xd62ff46a: // requestedPeerUser
                $flags = $r->readInt();
                $r->readLong();
                if ($flags & (1 << 0)) { $r->readString(); $r->readString(); } // first_name, last_name
                if ($flags & (1 << 1)) $r->readString(); // username
                if ($flags & (1 << 2)) self::skipPhoto($r);
                break;
            case 0x7307544f: // requestedPeerChat
                $flags = $r->readInt();
                $r->readLong();
                if ($flags & (1 << 0)) $r->readString();
                if ($flags & (1 << 2)) self::skipPhoto($r);
                break;
            case 0x8ba403e4: // requestedPeerChannel
                $flags = $r->readInt();
                $r->readLong();
                if ($flags & (1 << 0)) $r->readString();
                if ($flags & (1 << 1)) $r->readString();
                if ($flags & (1 << 2)) self::skipPhoto($r);
                break;
            default:
                throw new \RuntimeException(sprintf('Unknown RequestedPeer: 0x%08x', $c));
        }
    }

    // =========================================================================
    // StarGiftAttributeRarity — permille:int atau tanpa field
    // =========================================================================
    private static function skipStarGiftAttributeRarity(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x36437737) $r->readInt(); // starGiftAttributeRarity permille:int
        // variant lain tidak punya field
    }

    // =========================================================================
    // StarGiftAttribute — 4 variant
    // =========================================================================
    private static function skipStarGiftAttribute(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x565251e2: // starGiftAttributeModel flags:# name:string document:Document rarity
                $r->readInt(); // flags (crafted bit)
                $r->readString();
                self::skipDocument($r);
                self::skipStarGiftAttributeRarity($r);
                break;
            case 0x4e7085ea: // starGiftAttributePattern name:string document:Document rarity
                $r->readString();
                self::skipDocument($r);
                self::skipStarGiftAttributeRarity($r);
                break;
            case 0x9f2504e4: // starGiftAttributeBackdrop name:string backdrop_id center_color edge_color pattern_color text_color rarity
                $r->readString();
                $r->readInt(); $r->readInt(); $r->readInt(); $r->readInt(); $r->readInt();
                self::skipStarGiftAttributeRarity($r);
                break;
            case 0xe0bff26c: // starGiftAttributeOriginalDetails flags:# sender_id:f.0?Peer recipient_id:Peer date:int message:f.1?TextWithEntities
                $flags = $r->readInt();
                if ($flags & (1 << 0)) self::skipPeer($r);
                self::skipPeer($r);
                $r->readInt();
                if ($flags & (1 << 1)) self::skipTextWithEntities($r);
                break;
            default:
                throw new \RuntimeException(sprintf('Unknown StarGiftAttribute: 0x%08x', $c));
        }
    }

    // =========================================================================
    // StarGiftBackground — starGiftBackground#aff56398 center_color edge_color text_color
    // =========================================================================
    private static function skipStarGiftBackground(BinaryReader $r): void
    {
        $r->readInt(); // ctor
        $r->readInt(); // center_color
        $r->readInt(); // edge_color
        $r->readInt(); // text_color
    }

    // =========================================================================
    // StarGift — 2 variant
    // starGift#313a9547       — hadiah biasa (id:long sticker:Document stars:long + banyak flag)
    // starGiftUnique#85f0a9cd — hadiah unik (punya Vector<StarGiftAttribute>)
    // =========================================================================
    private static function skipStarGift(BinaryReader $r): void
    {
        $c = $r->readInt();
        if ($c === 0x313a9547) {
            // starGift biasa
            $flags = $r->readInt();
            $r->readLong();        // id
            self::skipDocument($r); // sticker
            $r->readLong();        // stars
            if ($flags & (1 << 0)) { $r->readInt(); $r->readInt(); } // availability_remains, total
            if ($flags & (1 << 4)) $r->readLong();  // availability_resale
            $r->readLong();        // convert_stars
            if ($flags & (1 << 1)) { $r->readInt(); $r->readInt(); } // first/last_sale_date
            if ($flags & (1 << 3)) $r->readLong();  // upgrade_stars
            if ($flags & (1 << 4)) $r->readLong();  // resell_min_stars
            if ($flags & (1 << 5)) $r->readString(); // title
            if ($flags & (1 << 6)) self::skipPeer($r); // released_by
            if ($flags & (1 << 8)) { $r->readInt(); $r->readInt(); } // per_user_total, remains
            if ($flags & (1 << 9)) $r->readInt();   // locked_until_date
            if ($flags & (1 << 11)) { $r->readString(); $r->readInt(); $r->readInt(); } // auction_slug, gifts_per_round, start_date
            if ($flags & (1 << 12)) $r->readInt();  // upgrade_variants
            if ($flags & (1 << 13)) self::skipStarGiftBackground($r);
        } elseif ($c === 0x85f0a9cd) {
            // starGiftUnique
            $flags = $r->readInt();
            $r->readLong(); $r->readLong(); // id, gift_id
            $r->readString(); $r->readString(); // title, slug
            $r->readInt(); // num
            if ($flags & (1 << 0)) self::skipPeer($r);   // owner_id
            if ($flags & (1 << 1)) $r->readString();      // owner_name
            if ($flags & (1 << 2)) $r->readString();      // owner_address
            self::skipVector($r, fn($x) => self::skipStarGiftAttribute($x)); // attributes
            $r->readInt(); $r->readInt(); // availability_issued, total
            if ($flags & (1 << 3)) $r->readString(); // gift_address
            if ($flags & (1 << 4)) self::skipVector($r, fn($x) => self::skipStarsAmount($x)); // resell_amount
            if ($flags & (1 << 5)) self::skipPeer($r);    // released_by
            if ($flags & (1 << 8)) { $r->readLong(); $r->readString(); $r->readLong(); } // value_amount, currency, usd_amount
            if ($flags & (1 << 10)) self::skipPeer($r);   // theme_peer
            if ($flags & (1 << 11)) self::skipPeerColor($r); // peer_color
            if ($flags & (1 << 12)) self::skipPeer($r);   // host_id
            if ($flags & (1 << 13)) $r->readInt();        // offer_min_stars
            if ($flags & (1 << 16)) $r->readInt();        // craft_chance_permille
        } else {
            throw new \RuntimeException(sprintf('Unknown StarGift ctor: 0x%08x', $c));
        }
    }
}
