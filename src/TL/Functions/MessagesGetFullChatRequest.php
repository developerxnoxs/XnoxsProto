<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.getFullChat#aeb00b34
 *   chat_id:long
 *   = messages.ChatFull;
 *
 * Response: messages.chatFull#e5d7d19c
 *   full_chat:ChatFull   chats:Vector<Chat>   users:Vector<User>
 *
 * Use for basic groups (not supergroups/channels).
 * For supergroups/channels use ChannelsGetFullChannelRequest.
 */
class MessagesGetFullChatRequest extends TLObject
{
    const CONSTRUCTOR = 0xaeb00b34;

    public function __construct(private int $chatId) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->chatId);
    }

    public function toDict(): array
    {
        return ['_' => 'messages.getFullChat', 'chat_id' => $this->chatId];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
