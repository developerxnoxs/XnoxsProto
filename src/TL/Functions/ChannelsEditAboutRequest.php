<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.editAbout#13e27f1e channel:InputChannel about:string = Bool;
 *
 * Ubah deskripsi/bio channel atau supergroup.
 * Basic group tidak memiliki deskripsi mandiri — migrate dulu ke supergroup.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 */
class ChannelsEditAboutRequest extends TLObject
{
    const CONSTRUCTOR = 0x13e27f1e;

    public function __construct(
        private int    $channelId,
        private int    $accessHash,
        private string $about
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->accessHash);

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
            '_'          => 'channels.editAbout',
            'channel_id' => $this->channelId,
            'about'      => $this->about,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
