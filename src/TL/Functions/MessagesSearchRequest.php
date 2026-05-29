<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.search#29ee847a
 *   flags:#
 *   peer:InputPeer
 *   q:string
 *   from_id:flags.0?InputPeer          (skip — not used here)
 *   saved_peer_id:flags.2?InputPeer    (skip)
 *   saved_reaction:flags.3?Vector      (skip)
 *   top_msg_id:flags.1?int             (skip)
 *   filter:MessagesFilter
 *   min_date:int
 *   max_date:int
 *   min_id:int
 *   max_id:int
 *   offset_id:int
 *   add_offset:int
 *   limit:int
 *   hash:long
 *   = messages.Messages;
 *
 * Filter constructors (no-param unless noted):
 *   inputMessagesFilterEmpty#57e2f66c        — all messages
 *   inputMessagesFilterPhotos#9609a51c       — photos only
 *   inputMessagesFilterVideo#9fc00e65        — videos only
 *   inputMessagesFilterDocument#56e9f0e4     — documents only
 *   inputMessagesFilterUrl#7ef0dd87          — messages with URLs
 *   inputMessagesFilterGif#ffc86587          — GIFs only
 *   inputMessagesFilterVoice#50f5c392        — voice messages only
 *   inputMessagesFilterMusic#3751b49e        — audio/music only
 */
class MessagesSearchRequest extends TLObject
{
    const CONSTRUCTOR = 0x29ee847a;

    const FILTER_EMPTY    = 0x57e2f66c;
    const FILTER_PHOTOS   = 0x9609a51c;
    const FILTER_VIDEO    = 0x9fc00e65;
    const FILTER_DOCUMENT = 0x56e9f0e4;
    const FILTER_URL      = 0x7ef0dd87;
    const FILTER_GIF      = 0xffc86587;
    const FILTER_VOICE    = 0x50f5c392;
    const FILTER_MUSIC    = 0x3751b49e;

    public function __construct(
        private InputPeer $peer,
        private string    $query,
        private int       $limit     = 20,
        private int       $filter    = self::FILTER_EMPTY,
        private int       $offsetId  = 0,
        private int       $addOffset = 0,
        private int       $maxId     = 0,
        private int       $minId     = 0,
        private int       $minDate   = 0,
        private int       $maxDate   = 0
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags: 0 (no from_id, no saved_peer_id, no saved_reaction, no top_msg_id)
        $writer->writeInt(0);

        $this->peer->serialize($writer);
        $writer->writeString($this->query);

        // filter: MessagesFilter (no-param constructors)
        $writer->writeInt($this->filter);

        $writer->writeInt($this->minDate);
        $writer->writeInt($this->maxDate);
        $writer->writeInt($this->minId);
        $writer->writeInt($this->maxId);
        $writer->writeInt($this->offsetId);
        $writer->writeInt($this->addOffset);
        $writer->writeInt($this->limit);
        $writer->writeLong(0); // hash
    }

    public function toDict(): array
    {
        return [
            '_'     => 'messages.search',
            'query' => $this->query,
            'limit' => $this->limit,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
