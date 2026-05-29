<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.toggleSlowMode#edd49ef0 channel:InputChannel seconds:int = Updates;
 *
 * Aktifkan atau nonaktifkan slow mode di supergroup.
 * seconds = 0 → nonaktifkan slow mode.
 *
 * Nilai seconds yang valid: 0, 10, 30, 60, 300, 900, 3600
 * (0=off, 10s, 30s, 1m, 5m, 15m, 1h)
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 */
class ChannelsToggleSlowModeRequest extends TLObject
{
    const CONSTRUCTOR = 0xedd49ef0;

    public function __construct(
        private int $channelId,
        private int $accessHash,
        private int $seconds
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->accessHash);

        // seconds:int
        $writer->writeInt($this->seconds);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'          => 'channels.toggleSlowMode',
            'channel_id' => $this->channelId,
            'seconds'    => $this->seconds,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
