<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.getChats#49e9528f ids:Vector<long> = messages.Chats
 *
 * Ambil info dasar (title, username) untuk daftar grup biasa (chat) berdasarkan ID.
 * Tidak memerlukan access_hash — cocok untuk enrichment Chat#ID dari getDialogs.
 *
 * Response:
 *   messages.chats#64ff9fd5      chats:Vector<Chat>
 *   messages.chatsSlice#9cd81144 count:int chats:Vector<Chat>
 */
class MessagesGetChatsRequest extends TLObject
{
    const CONSTRUCTOR        = 0x49e9528f;
    const RESP_CHATS         = 0x64ff9fd5;
    const RESP_CHATS_SLICE   = 0x9cd81144;

    /** @param int[] $ids Daftar chat_id (grup biasa) */
    public function __construct(private array $ids) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt(0x1cb5c415); // vector ctor
        $writer->writeInt(count($this->ids));
        foreach ($this->ids as $id) {
            $writer->writeLong((int)$id);
        }
    }

    public function toDict(): array
    {
        return ['_' => 'messages.getChats', 'ids' => $this->ids];
    }

    public function toBytes(): string
    {
        $w = new BinaryWriter();
        $this->serialize($w);
        return $w->getValue();
    }
}
