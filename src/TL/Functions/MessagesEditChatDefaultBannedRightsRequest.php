<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.editChatDefaultBannedRights#a5866b41
 *   peer:InputPeer
 *   banned_rights:ChatBannedRights
 *   = Updates;
 *
 * Set default permission anggota untuk grup biasa atau supergroup.
 * Flag yang di-set berarti DILARANG. Flag tidak di-set berarti DIIZINKAN.
 *
 * Contoh: larang semua anggota kirim stiker dan GIF:
 *   $bannedRights = MessagesEditChatDefaultBannedRightsRequest::BAN_SEND_STICKERS
 *                 | MessagesEditChatDefaultBannedRightsRequest::BAN_SEND_GIFS;
 *
 * chatBannedRights#9f120418 flags:# ... until_date:int
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 */
class MessagesEditChatDefaultBannedRightsRequest extends TLObject
{
    const CONSTRUCTOR = 0xa5866b41;

    const BAN_SEND_MESSAGES  = 0x000002; // Larang kirim teks (mute)
    const BAN_SEND_MEDIA     = 0x000004; // Larang kirim semua media
    const BAN_SEND_STICKERS  = 0x000008; // Larang kirim stiker
    const BAN_SEND_GIFS      = 0x000010; // Larang kirim GIF
    const BAN_SEND_GAMES     = 0x000020; // Larang main game Telegram
    const BAN_SEND_INLINE    = 0x000040; // Larang pakai inline bot
    const BAN_EMBED_LINKS    = 0x000080; // Larang kirim link
    const BAN_SEND_POLLS     = 0x000100; // Larang buat polling
    const BAN_CHANGE_INFO    = 0x000400; // Larang ubah info grup
    const BAN_INVITE_USERS   = 0x008000; // Larang undang anggota baru
    const BAN_PIN_MESSAGES   = 0x020000; // Larang pin pesan
    const BAN_MANAGE_TOPICS  = 0x040000; // Larang kelola topik (forum)
    const BAN_SEND_PHOTOS    = 0x080000; // Larang kirim foto
    const BAN_SEND_VIDEOS    = 0x100000; // Larang kirim video
    const BAN_SEND_AUDIOS    = 0x400000; // Larang kirim audio
    const BAN_SEND_DOCS      = 0x800000; // Larang kirim dokumen

    /** Semua larangan kecuali view_messages dan change_info */
    const BAN_ALL_SEND =
        self::BAN_SEND_MESSAGES | self::BAN_SEND_MEDIA   |
        self::BAN_SEND_STICKERS | self::BAN_SEND_GIFS    |
        self::BAN_SEND_GAMES    | self::BAN_SEND_INLINE   |
        self::BAN_EMBED_LINKS   | self::BAN_SEND_POLLS    |
        self::BAN_SEND_PHOTOS   | self::BAN_SEND_VIDEOS   |
        self::BAN_SEND_AUDIOS   | self::BAN_SEND_DOCS;

    public function __construct(
        private InputPeer $peer,
        private int       $bannedRightsFlags,
        private int       $untilDate = 0
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // peer:InputPeer
        $this->peer->serialize($writer);

        // chatBannedRights#9f120418 flags:# ... until_date:int
        $writer->writeInt(0x9f120418);
        $writer->writeInt($this->bannedRightsFlags);
        $writer->writeInt($this->untilDate);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'            => 'messages.editChatDefaultBannedRights',
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
