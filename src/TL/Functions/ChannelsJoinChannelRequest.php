<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.joinChannel#24b524c5 channel:InputChannel = Updates;
 *
 * InputChannel constructors (from TDLib official schema):
 *   inputChannel#f35aec28         channel_id:long access_hash:long
 *   inputChannelFromMessage#5b934f9d  peer:InputPeer msg_id:int channel_id:long
 *   inputChannelEmpty#ee8c1e86
 */
class ChannelsJoinChannelRequest extends TLObject
{
    const CONSTRUCTOR = 0x24b524c5;

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
            '_'           => 'channels.joinChannel',
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
