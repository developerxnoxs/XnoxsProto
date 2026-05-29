<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.deleteChannel#c0111fe3 channel:InputChannel = Updates;
 *
 * Hapus/bubarkan channel atau supergroup secara permanen.
 * Hanya bisa dilakukan oleh creator/owner.
 * Untuk basic group gunakan messages.deleteChat#83247d11.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 */
class ChannelsDeleteChannelRequest extends TLObject
{
    const CONSTRUCTOR = 0xc0111fe3;

    public function __construct(
        private int $channelId,
        private int $accessHash
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->accessHash);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'channels.deleteChannel', 'channel_id' => $this->channelId];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
