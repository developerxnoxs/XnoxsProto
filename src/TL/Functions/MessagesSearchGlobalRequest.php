<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.searchGlobal#4bc6589a
 *   flags:#
 *   broadcasts_only:flags.1?true   (channels only)
 *   groups_only:flags.2?true
 *   users_only:flags.3?true
 *   folder_id:flags.0?int          (archived chats folder)
 *   q:string
 *   filter:MessagesFilter
 *   min_date:int
 *   max_date:int
 *   offset_rate:int
 *   offset_peer:InputPeer
 *   offset_id:int
 *   limit:int
 *   = messages.Messages;
 *
 * Response: messages.Messages — same as messages.search
 * offset_rate from previous response is used for pagination.
 */
class MessagesSearchGlobalRequest extends TLObject
{
    const CONSTRUCTOR = 0x4bc6589a;

    const FILTER_EMPTY    = 0x57e2f66c;
    const FILTER_PHOTOS   = 0x9609a51c;
    const FILTER_VIDEO    = 0x9fc00e65;
    const FILTER_DOCUMENT = 0x56e9f0e4;
    const FILTER_VOICE    = 0x50f5c392;
    const FILTER_MUSIC    = 0x3751b49e;

    private InputPeer $offsetPeer;

    public function __construct(
        private string $query,
        private int    $limit      = 20,
        private int    $filter     = self::FILTER_EMPTY,
        private int    $minDate    = 0,
        private int    $maxDate    = 0,
        private int    $offsetRate = 0,
        private int    $offsetId   = 0
    ) {
        $this->offsetPeer = InputPeer::empty();
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags = 0 (no broadcasts_only, no groups_only, no users_only, no folder_id)
        $writer->writeInt(0);

        $writer->writeString($this->query);

        // filter
        $writer->writeInt($this->filter);

        $writer->writeInt($this->minDate);
        $writer->writeInt($this->maxDate);
        $writer->writeInt($this->offsetRate);
        $this->offsetPeer->serialize($writer);
        $writer->writeInt($this->offsetId);
        $writer->writeInt($this->limit);
    }

    public function toDict(): array
    {
        return [
            '_'     => 'messages.searchGlobal',
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
