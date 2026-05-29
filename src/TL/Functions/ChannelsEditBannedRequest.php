<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * channels.editBanned#96e6cd81
 *   channel:InputChannel
 *   participant:InputPeer
 *   banned_rights:ChatBannedRights
 *   = Updates;
 *
 * CRC berubah dari #96e6a7d9 (lama) → #96e6cd81 (TDLib / Layer 214).
 * Verified dari: https://github.com/tdlib/td/blob/master/td/generate/scheme/telegram_api.tl
 *
 * chatBannedRights#9f120418 flags:#
 *   view_messages:flags.0?true      0x000001  — cannot view messages (banned/kicked)
 *   send_messages:flags.1?true      0x000002  — cannot send messages (muted)
 *   send_media:flags.2?true         0x000004
 *   send_stickers:flags.3?true      0x000008
 *   send_gifs:flags.4?true          0x000010
 *   send_games:flags.5?true         0x000020
 *   send_inline:flags.6?true        0x000040
 *   embed_links:flags.7?true        0x000080
 *   send_polls:flags.8?true         0x000100
 *   change_info:flags.10?true       0x000400
 *   invite_users:flags.15?true      0x008000
 *   pin_messages:flags.17?true      0x020000
 *   manage_topics:flags.18?true     0x040000
 *   send_photos:flags.19?true       0x080000
 *   send_videos:flags.20?true       0x100000
 *   send_roundvideos:flags.21?true  0x200000
 *   send_audios:flags.22?true       0x400000
 *   send_docs:flags.23?true         0x800000
 *   send_plain:flags.24?true        0x1000000
 *   until_date:int                  0 = forever, unix ts = until
 *   = ChatBannedRights;
 */
class ChannelsEditBannedRequest extends TLObject
{
    const CONSTRUCTOR = 0x96e6cd81;

    const BAN_VIEW_MESSAGES  = 0x000001;
    const BAN_SEND_MESSAGES  = 0x000002;
    const BAN_SEND_MEDIA     = 0x000004;
    const BAN_SEND_STICKERS  = 0x000008;
    const BAN_SEND_GIFS      = 0x000010;
    const BAN_SEND_GAMES     = 0x000020;
    const BAN_SEND_INLINE    = 0x000040;
    const BAN_EMBED_LINKS    = 0x000080;
    const BAN_SEND_POLLS     = 0x000100;
    const BAN_CHANGE_INFO    = 0x000400;
    const BAN_INVITE_USERS   = 0x008000;
    const BAN_PIN_MESSAGES   = 0x020000;
    const BAN_SEND_PHOTOS    = 0x080000;
    const BAN_SEND_VIDEOS    = 0x100000;
    const BAN_SEND_AUDIOS    = 0x400000;
    const BAN_SEND_DOCS      = 0x800000;
    const BAN_SEND_PLAIN     = 0x1000000;

    const BAN_ALL_MEDIA =
        self::BAN_SEND_MESSAGES | self::BAN_SEND_MEDIA |
        self::BAN_SEND_STICKERS | self::BAN_SEND_GIFS  |
        self::BAN_SEND_GAMES    | self::BAN_SEND_INLINE |
        self::BAN_EMBED_LINKS   | self::BAN_SEND_POLLS  |
        self::BAN_SEND_PHOTOS   | self::BAN_SEND_VIDEOS |
        self::BAN_SEND_AUDIOS   | self::BAN_SEND_DOCS   |
        self::BAN_SEND_PLAIN;

    public function __construct(
        private int      $channelId,
        private int      $channelAccessHash,
        private InputPeer $participant,
        private int      $bannedRightsFlags,
        private int      $untilDate = 0
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->channelAccessHash);

        // participant: InputPeer
        $this->participant->serialize($writer);

        // chatBannedRights#9f120418
        $writer->writeInt(0x9f120418);
        $writer->writeInt($this->bannedRightsFlags);
        $writer->writeInt($this->untilDate);
    }

    public function toDict(): array
    {
        return [
            '_'            => 'channels.editBanned',
            'channel_id'   => $this->channelId,
            'banned_flags' => $this->bannedRightsFlags,
            'until_date'   => $this->untilDate,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
