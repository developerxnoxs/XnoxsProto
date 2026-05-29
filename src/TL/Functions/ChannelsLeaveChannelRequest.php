<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.leaveChannel#f836aa95 channel:InputChannel = Updates;
 */
class ChannelsLeaveChannelRequest extends TLObject
{
    const CONSTRUCTOR = 0xf836aa95;

    private int $channelId;
    private int $accessHash;

    public function __construct(int $channelId, int $accessHash)
    {
        $this->channelId  = $channelId;
        $this->accessHash = $accessHash;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt(0xf35aec28); // inputChannel#f35aec28 (official TDLib schema)
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->accessHash);
    }

    public function toDict(): array
    {
        return [
            '_'           => 'channels.leaveChannel',
            'channel_id'  => $this->channelId,
            'access_hash' => $this->accessHash,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
