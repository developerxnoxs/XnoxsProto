<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.getHistory#4423e6c5
 *
 * TL schema:
 *   messages.getHistory#4423e6c5
 *     peer:InputPeer
 *     offset_id:int
 *     offset_date:int
 *     add_offset:int
 *     limit:int
 *     max_id:int
 *     min_id:int
 *     hash:long
 *     = messages.Messages;
 *
 * Response:
 *   messages.messages#8c718e87          — full list
 *   messages.messagesSlice#3a54685e     — partial list
 *   messages.channelMessages#c776ba4e   — channel history
 *   messages.messagesNotModified#74535f21
 */
class MessagesGetHistoryRequest extends TLObject
{
    const CONSTRUCTOR = 0x4423e6c5;

    private InputPeer $peer;
    private int       $offsetId;
    private int       $offsetDate;
    private int       $addOffset;
    private int       $limit;
    private int       $maxId;
    private int       $minId;
    private int       $hash;

    public function __construct(
        InputPeer $peer,
        int       $limit      = 20,
        int       $offsetId   = 0,
        int       $offsetDate = 0,
        int       $addOffset  = 0,
        int       $maxId      = 0,
        int       $minId      = 0,
        int       $hash       = 0
    ) {
        $this->peer       = $peer;
        $this->limit      = $limit;
        $this->offsetId   = $offsetId;
        $this->offsetDate = $offsetDate;
        $this->addOffset  = $addOffset;
        $this->maxId      = $maxId;
        $this->minId      = $minId;
        $this->hash       = $hash;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $this->peer->serialize($writer);
        $writer->writeInt($this->offsetId);
        $writer->writeInt($this->offsetDate);
        $writer->writeInt($this->addOffset);
        $writer->writeInt($this->limit);
        $writer->writeInt($this->maxId);
        $writer->writeInt($this->minId);
        $writer->writeLong($this->hash);
    }

    public function toDict(): array
    {
        return [
            '_'           => 'messages.getHistory',
            'offset_id'   => $this->offsetId,
            'offset_date' => $this->offsetDate,
            'add_offset'  => $this->addOffset,
            'limit'       => $this->limit,
            'max_id'      => $this->maxId,
            'min_id'      => $this->minId,
            'hash'        => $this->hash,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
