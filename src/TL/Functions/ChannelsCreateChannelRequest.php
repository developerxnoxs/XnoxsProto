<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.createChannel#91006707 flags:#
 *   broadcast:flags.0?true       — channel broadcast (bukan megagroup)
 *   megagroup:flags.1?true       — supergroup
 *   for_import:flags.3?true      — untuk import pesan
 *   forum:flags.5?true           — aktifkan mode forum/topik
 *   title:string
 *   about:string
 *   geo_point:flags.4?InputGeoPoint
 *   address:flags.4?string
 *   ttl_period:flags.6?int
 *   = Updates;
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 */
class ChannelsCreateChannelRequest extends TLObject
{
    const CONSTRUCTOR = 0x91006707;

    const FLAG_BROADCAST = 1 << 0; // 0x1 — channel broadcast
    const FLAG_MEGAGROUP = 1 << 1; // 0x2 — supergroup
    const FLAG_FORUM     = 1 << 5; // 0x20 — forum/topik mode

    /**
     * @param string $title      Judul channel/supergroup
     * @param string $about      Deskripsi (bisa kosong)
     * @param bool   $megagroup  true = supergroup, false = broadcast channel
     * @param bool   $forum      true = aktifkan mode topik/forum (hanya untuk megagroup)
     */
    public function __construct(
        private string $title,
        private string $about     = '',
        private bool   $megagroup = false,
        private bool   $forum     = false
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:#
        $flags = 0;
        if ($this->megagroup) {
            $flags |= self::FLAG_MEGAGROUP;
        } else {
            $flags |= self::FLAG_BROADCAST;
        }
        if ($this->forum && $this->megagroup) {
            $flags |= self::FLAG_FORUM;
        }
        $writer->writeInt($flags);

        // title:string
        $writer->writeString($this->title);

        // about:string
        $writer->writeString($this->about);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'         => 'channels.createChannel',
            'title'     => $this->title,
            'about'     => $this->about,
            'megagroup' => $this->megagroup,
            'forum'     => $this->forum,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
