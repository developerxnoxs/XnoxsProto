<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.toggleJoinToSend#e4cb9580 channel:InputChannel enabled:Bool = Updates;
 *
 * Wajibkan user untuk join supergroup sebelum bisa mengirim pesan.
 * Saat diaktifkan, user yang belum join tidak bisa kirim pesan meski sudah di chat.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 * boolTrue#997275b5 / boolFalse#bc799737
 */
class ChannelsToggleJoinToSendRequest extends TLObject
{
    const CONSTRUCTOR = 0xe4cb9580;

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
            '_'          => 'channels.toggleJoinToSend',
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
