<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;
use XnoxsProto\TL\Types\InputMedia;
use XnoxsProto\TL\Types\InputMediaPoll;

/**
 * messages.sendMedia#7bd66041 (layer 162+)
 *
 * TL schema:
 *   messages.sendMedia#7bd66041
 *     flags:#
 *     silent:flags.5?true
 *     background:flags.6?true
 *     clear_draft:flags.7?true
 *     noforwards:flags.14?true
 *     update_stickersets_order:flags.15?true
 *     invert_media:flags.16?true
 *     allow_paid_floodskip:flags.19?true
 *     peer:InputPeer
 *     reply_to:flags.0?InputReplyTo
 *     media:InputMedia
 *     message:string
 *     random_id:long
 *     reply_markup:flags.2?ReplyMarkup
 *     entities:flags.3?Vector<MessageEntity>
 *     schedule_date:flags.10?int
 *     send_as:flags.13?InputPeer
 *     = Updates;
 */
class MessagesSendMediaRequest extends TLObject
{
    const CONSTRUCTOR = 0x7bd66041;

    private InputPeer               $peer;
    private InputMedia|InputMediaPoll $media;
    private string                  $message;
    private int                     $randomId;
    private ?int                    $replyToMsgId;
    private bool                    $silent     = false;
    private bool                    $clearDraft = false;

    public function __construct(
        InputPeer                   $peer,
        InputMedia|InputMediaPoll   $media,
        string     $message      = '',
        ?int       $replyToMsgId = null
    ) {
        $this->peer         = $peer;
        $this->media        = $media;
        $this->message      = $message;
        $this->replyToMsgId = $replyToMsgId;
        $this->randomId     = unpack('q', random_bytes(8))[1];
    }

    public function setSilent(bool $v): self    { $this->silent     = $v; return $this; }
    public function setClearDraft(bool $v): self { $this->clearDraft = $v; return $this; }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->replyToMsgId !== null) $flags |= (1 << 0);
        if ($this->silent)                $flags |= (1 << 5);
        if ($this->clearDraft)            $flags |= (1 << 7);

        $writer->writeInt($flags);
        $this->peer->serialize($writer);

        // reply_to: flags.0 → inputReplyToMessage#3bd4b7c2
        if ($this->replyToMsgId !== null) {
            $writer->writeInt(0x3bd4b7c2);
            $writer->writeInt(0);
            $writer->writeInt($this->replyToMsgId);
        }

        $this->media->serialize($writer);

        $writer->writeString($this->message);
        $writer->writeLong($this->randomId);
    }

    public function toDict(): array
    {
        return [
            '_'         => 'messages.sendMedia',
            'peer'      => ['id' => $this->peer->getId()],
            'message'   => $this->message,
            'random_id' => $this->randomId,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
