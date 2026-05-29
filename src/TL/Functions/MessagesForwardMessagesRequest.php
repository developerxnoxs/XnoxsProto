<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.forwardMessages#13704a7c  (updated constructor — layer 200+)
 *   flags:#
 *   silent:flags.5?true
 *   background:flags.6?true
 *   with_my_score:flags.8?true
 *   drop_author:flags.11?true
 *   drop_media_captions:flags.12?true
 *   noforwards:flags.14?true
 *   allow_paid_floodskip:flags.19?true
 *   from_peer:InputPeer
 *   id:Vector<int>
 *   random_id:Vector<long>
 *   to_peer:InputPeer
 *   top_msg_id:flags.9?int
 *   reply_to:flags.22?InputReplyTo
 *   schedule_date:flags.10?int
 *   schedule_repeat_period:flags.24?int
 *   send_as:flags.13?InputPeer
 *   quick_reply_shortcut:flags.17?InputQuickReplyShortcut
 *   effect:flags.18?long
 *   video_timestamp:flags.20?int
 *   allow_paid_stars:flags.21?long
 *   suggested_post:flags.23?SuggestedPost
 *   = Updates;
 * Old constructor 0xc4f600c9 rejected by server with INPUT_METHOD_INVALID.
 */
class MessagesForwardMessagesRequest extends TLObject
{
    const CONSTRUCTOR = 0x13704a7c;

    private InputPeer $fromPeer;
    private array     $ids;
    private InputPeer $toPeer;
    private bool      $dropAuthor;
    private bool      $silent;

    public function __construct(
        InputPeer $fromPeer,
        array     $ids,
        InputPeer $toPeer,
        bool      $dropAuthor = false,
        bool      $silent     = false
    ) {
        $this->fromPeer   = $fromPeer;
        $this->ids        = $ids;
        $this->toPeer     = $toPeer;
        $this->dropAuthor = $dropAuthor;
        $this->silent     = $silent;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:#
        $flags = 0;
        if ($this->silent)     $flags |= (1 << 5);
        if ($this->dropAuthor) $flags |= (1 << 11);
        $writer->writeInt($flags);

        // from_peer:InputPeer
        $this->fromPeer->serialize($writer);

        // id:Vector<int>
        $writer->writeInt(0x1cb5c415); // vector ctor
        $writer->writeInt(count($this->ids));
        foreach ($this->ids as $id) {
            $writer->writeInt($id);
        }

        // random_id:Vector<long> — one per message
        $writer->writeInt(0x1cb5c415); // vector ctor
        $writer->writeInt(count($this->ids));
        foreach ($this->ids as $_) {
            $writer->writeLong(unpack('P', random_bytes(8))[1]);
        }

        // to_peer:InputPeer
        $this->toPeer->serialize($writer);
    }

    public function toDict(): array
    {
        return [
            '_'   => 'messages.forwardMessages',
            'ids' => $this->ids,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
