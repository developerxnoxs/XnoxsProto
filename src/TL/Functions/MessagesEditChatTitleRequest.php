<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.editChatTitle#73783ffd chat_id:long title:string = Updates;
 *
 * Ubah judul basic group.
 * Untuk supergroup/channel gunakan channels.editTitle#566decd0.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 */
class MessagesEditChatTitleRequest extends TLObject
{
    const CONSTRUCTOR = 0x73783ffd;

    public function __construct(
        private int    $chatId,
        private string $title
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->chatId);
        $writer->writeString($this->title);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'       => 'messages.editChatTitle',
            'chat_id' => $this->chatId,
            'title'   => $this->title,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
