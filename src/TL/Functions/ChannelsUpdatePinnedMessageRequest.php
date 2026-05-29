<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.updatePinnedMessage#fc9e28bc
 *   flags:#
 *   silent:flags.0?true  — pin without notification
 *   unpin:flags.1?true   — unpin instead of pin
 *   channel:InputChannel
 *   id:int
 *   = Updates;
 *
 * Use for: channels and supergroups.
 * For DMs/basic groups use MessagesUpdatePinnedMessageRequest.
 */
class ChannelsUpdatePinnedMessageRequest extends TLObject
{
    const CONSTRUCTOR = 0xfc9e28bc;

    const FLAG_SILENT = 0x1;
    const FLAG_UNPIN  = 0x2;

    public function __construct(
        private int  $channelId,
        private int  $channelAccessHash,
        private int  $msgId,
        private bool $silent = false,
        private bool $unpin  = false
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->silent) $flags |= self::FLAG_SILENT;
        if ($this->unpin)  $flags |= self::FLAG_UNPIN;
        $writer->writeInt($flags);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->channelAccessHash);

        $writer->writeInt($this->msgId);
    }

    public function toDict(): array
    {
        return [
            '_'          => 'channels.updatePinnedMessage',
            'channel_id' => $this->channelId,
            'msg_id'     => $this->msgId,
            'unpin'      => $this->unpin,
            'silent'     => $this->silent,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
