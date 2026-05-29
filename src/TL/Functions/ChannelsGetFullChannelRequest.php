<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.getFullChannel#8736a09
 *   channel:InputChannel
 *   = messages.ChatFull;
 *
 * Response: messages.chatFull#e5d7d19c
 *   full_chat:ChannelFull   chats:Vector<Chat>   users:Vector<User>
 *
 * Use for channels and supergroups.
 * For basic groups use MessagesGetFullChatRequest.
 */
class ChannelsGetFullChannelRequest extends TLObject
{
    const CONSTRUCTOR = 0x08736a09;

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

    public function toDict(): array
    {
        return [
            '_'          => 'channels.getFullChannel',
            'channel_id' => $this->channelId,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
