<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.updatePinnedMessage#d2aaf7ec
 *   flags:#
 *   silent:flags.0?true      — pin without notification
 *   unpin:flags.1?true       — unpin instead of pin
 *   pm_oneside:flags.2?true  — only for sender (DM)
 *   peer:InputPeer
 *   id:int
 *   = Updates;
 *
 * Use for: DMs, basic groups.
 * For channels/supergroups use ChannelsUpdatePinnedMessageRequest.
 */
class MessagesUpdatePinnedMessageRequest extends TLObject
{
    const CONSTRUCTOR = 0xd2aaf7ec;

    const FLAG_SILENT    = 0x1;
    const FLAG_UNPIN     = 0x2;
    const FLAG_PM_ONESIDE = 0x4;

    public function __construct(
        private InputPeer $peer,
        private int       $msgId,
        private bool      $silent    = false,
        private bool      $unpin     = false,
        private bool      $pmOneside = false
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->silent)    $flags |= self::FLAG_SILENT;
        if ($this->unpin)     $flags |= self::FLAG_UNPIN;
        if ($this->pmOneside) $flags |= self::FLAG_PM_ONESIDE;
        $writer->writeInt($flags);

        $this->peer->serialize($writer);
        $writer->writeInt($this->msgId);
    }

    public function toDict(): array
    {
        return [
            '_'      => 'messages.updatePinnedMessage',
            'peer'   => $this->peer->toArray(),
            'msg_id' => $this->msgId,
            'unpin'  => $this->unpin,
            'silent' => $this->silent,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
