<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * channels.deleteMessages#84c1fd4e channel:InputChannel id:Vector<int> = messages.AffectedMessages
 */
class ChannelsDeleteMessagesRequest extends TLObject
{
    const CONSTRUCTOR = 0x84c1fd4e;

    private InputPeer $channel;
    private array     $ids;

    public function __construct(InputPeer $channel, array $ids)
    {
        $this->channel = $channel;
        $this->ids     = $ids;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel from InputPeer (channel)
        // inputChannel#f35aec28 channel_id:long access_hash:long
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channel->getId());
        $writer->writeLong($this->channel->getAccessHash());

        // Vector<int>
        $writer->writeInt(0x1cb5c415);
        $writer->writeInt(count($this->ids));
        foreach ($this->ids as $id) {
            $writer->writeInt($id);
        }
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'channels.deleteMessages', 'id' => $this->ids];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
