<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.toggleSignatures#418d549c flags:# channel:InputChannel signatures_enabled:flags.0?true = Updates;
 *
 * Aktifkan atau nonaktifkan tanda tangan admin di channel broadcast.
 * Saat aktif, setiap pesan yang diposting admin akan menampilkan nama pengirim.
 * Hanya berlaku untuk channel broadcast, bukan supergroup.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 * Note: CRC berubah dari #1f69b606 (Layer lama) ke #418d549c (Layer 194+).
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 */
class ChannelsToggleSignaturesRequest extends TLObject
{
    const CONSTRUCTOR = 0x418d549c;

    public function __construct(
        private int  $channelId,
        private int  $accessHash,
        private bool $enabled
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:# — bit 0 = signatures_enabled
        $flags = $this->enabled ? 0x1 : 0x0;
        $writer->writeInt($flags);

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
        return [
            '_'          => 'channels.toggleSignatures',
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
