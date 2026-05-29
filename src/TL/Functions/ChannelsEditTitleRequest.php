<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.editTitle#566decd0 channel:InputChannel title:string = Updates;
 *
 * Ubah judul channel atau supergroup.
 * Untuk basic group gunakan messages.editChatTitle#73783ffd.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 */
class ChannelsEditTitleRequest extends TLObject
{
    const CONSTRUCTOR = 0x566decd0;

    public function __construct(
        private int    $channelId,
        private int    $accessHash,
        private string $title
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->accessHash);

        // title:string
        $writer->writeString($this->title);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'          => 'channels.editTitle',
            'channel_id' => $this->channelId,
            'title'      => $this->title,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
