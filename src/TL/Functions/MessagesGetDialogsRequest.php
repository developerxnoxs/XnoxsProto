<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.getDialogs#a0f4cb4f
 *
 * TL schema (tdlib telegram_api.tl):
 *   messages.getDialogs#a0f4cb4f
 *     flags:#
 *     exclude_pinned:flags.0?true
 *     folder_id:flags.1?int
 *     offset_date:int
 *     offset_id:int
 *     offset_peer:InputPeer
 *     limit:int
 *     hash:long
 *     = messages.Dialogs;
 */
class MessagesGetDialogsRequest extends TLObject
{
    const CONSTRUCTOR = 0xa0f4cb4f;

    private bool    $excludePinned;
    private ?int    $folderId;
    private int     $offsetDate;
    private int     $offsetId;
    private InputPeer $offsetPeer;
    private int     $limit;
    private int     $hash;

    public function __construct(
        int       $limit         = 100,
        int       $offsetDate    = 0,
        int       $offsetId      = 0,
        ?InputPeer $offsetPeer   = null,
        bool      $excludePinned = false,
        ?int      $folderId      = null,
        int       $hash          = 0
    ) {
        $this->limit         = $limit;
        $this->offsetDate    = $offsetDate;
        $this->offsetId      = $offsetId;
        $this->offsetPeer    = $offsetPeer ?? InputPeer::empty();
        $this->excludePinned = $excludePinned;
        $this->folderId      = $folderId;
        $this->hash          = $hash;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->excludePinned)    $flags |= (1 << 0);
        if ($this->folderId !== null) $flags |= (1 << 1);

        $writer->writeInt($flags);

        if ($this->folderId !== null) {
            $writer->writeInt($this->folderId);
        }

        $writer->writeInt($this->offsetDate);
        $writer->writeInt($this->offsetId);
        $this->offsetPeer->serialize($writer);
        $writer->writeInt($this->limit);
        $writer->writeLong($this->hash);
    }

    public function toDict(): array
    {
        return [
            '_'             => 'messages.getDialogs',
            'offset_date'   => $this->offsetDate,
            'offset_id'     => $this->offsetId,
            'limit'         => $this->limit,
            'hash'          => $this->hash,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
