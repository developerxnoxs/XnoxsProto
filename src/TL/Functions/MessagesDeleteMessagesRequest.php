<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.deleteMessages#e58e95d2 flags:# revoke:flags.0?true id:Vector<int> = messages.AffectedMessages
 *
 * For channel messages use messages.deleteScheduledMessages or channels.deleteMessages instead.
 */
class MessagesDeleteMessagesRequest extends TLObject
{
    const CONSTRUCTOR = 0xe58e95d2;

    private array $ids;
    private bool  $revoke;

    public function __construct(array $ids, bool $revoke = true)
    {
        $this->ids    = $ids;
        $this->revoke = $revoke;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt($this->revoke ? 1 : 0); // flags bit 0 = revoke

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
        return ['_' => 'messages.deleteMessages', 'revoke' => $this->revoke, 'id' => $this->ids];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
