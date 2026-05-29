<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.toggleJoinRequest#4c2985b6 channel:InputChannel enabled:Bool = Updates;
 *
 * Wajibkan persetujuan admin sebelum user bisa bergabung ke supergroup/channel.
 * Saat diaktifkan, request join harus di-approve admin terlebih dahulu.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 * boolTrue#997275b5 / boolFalse#bc799737
 */
class ChannelsToggleJoinRequestRequest extends TLObject
{
    const CONSTRUCTOR = 0x4c2985b6;

    public function __construct(
        private int  $channelId,
        private int  $accessHash,
        private bool $enabled
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->accessHash);

        // enabled:Bool
        $writer->writeBool($this->enabled);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'          => 'channels.toggleJoinRequest',
            'channel_id' => $this->channelId,
            'enabled'    => $this->enabled,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
