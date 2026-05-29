<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.editMessage#48f71778 flags:# no_webpage:flags.1?true invert_media:flags.16?true
 *   peer:InputPeer id:int message:flags.11?string ... = Updates
 */
class MessagesEditMessageRequest extends TLObject
{
    const CONSTRUCTOR = 0x48f71778;

    private InputPeer $peer;
    private int       $msgId;
    private ?string   $text;

    public function __construct(InputPeer $peer, int $msgId, ?string $text = null)
    {
        $this->peer  = $peer;
        $this->msgId = $msgId;
        $this->text  = $text;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->text !== null) $flags |= (1 << 11);
        $writer->writeInt($flags);

        $this->peer->serialize($writer);
        $writer->writeInt($this->msgId);

        if ($this->text !== null) $writer->writeString($this->text);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'messages.editMessage', 'id' => $this->msgId, 'message' => $this->text];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
