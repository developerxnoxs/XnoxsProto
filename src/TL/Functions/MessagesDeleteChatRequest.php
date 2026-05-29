<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.deleteChat#5bd0ee50 chat_id:long = Bool;
 *
 * Hapus/bubarkan basic group secara permanen.
 * Hanya bisa dilakukan oleh creator grup.
 * Untuk supergroup/channel gunakan channels.deleteChannel#c0111fe3.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 * Catatan: Layer lama (≤65) menggunakan #83247d11 dengan chat_id:int —
 * Layer 214 menggunakan #5bd0ee50 dengan chat_id:long.
 */
class MessagesDeleteChatRequest extends TLObject
{
    const CONSTRUCTOR = 0x5bd0ee50;

    public function __construct(private int $chatId) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->chatId);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'messages.deleteChat', 'chat_id' => $this->chatId];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
