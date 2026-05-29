<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.migrateChat#a2875319 chat_id:long = Updates;
 *
 * Upgrade basic group ke supergroup.
 * Setelah migrate, chat_id lama tidak bisa dipakai lagi.
 * Gunakan getDialogs() untuk menemukan supergroup baru.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 */
class MessagesMigrateChatRequest extends TLObject
{
    const CONSTRUCTOR = 0xa2875319;

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
        return ['_' => 'messages.migrateChat', 'chat_id' => $this->chatId];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
