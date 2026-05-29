<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.sendMessage#dff8042c (layer 162+)
 *
 * TL schema:
 *   messages.sendMessage#dff8042c
 *     flags:#
 *     no_webpage:flags.1?true
 *     silent:flags.5?true
 *     background:flags.6?true
 *     clear_draft:flags.7?true
 *     noforwards:flags.14?true
 *     invert_media:flags.16?true
 *     peer:InputPeer
 *     reply_to:flags.0?InputReplyTo
 *     message:string
 *     random_id:long
 *     reply_markup:flags.2?ReplyMarkup
 *     entities:flags.3?Vector<MessageEntity>
 *     schedule_date:flags.10?int
 *     send_as:flags.13?InputPeer
 *     = Updates;
 */
class MessagesSendMessageRequest extends TLObject
{
    const CONSTRUCTOR = 0xdff8042c;

    private InputPeer $peer;
    private string    $message;
    private int       $randomId;
    private bool      $noWebpage   = false;
    private bool      $silent      = false;
    private bool      $clearDraft  = false;
    private ?int      $replyToMsgId = null;

    public function __construct(InputPeer $peer, string $message, ?int $replyToMsgId = null)
    {
        $this->peer         = $peer;
        $this->message      = $message;
        $this->replyToMsgId = $replyToMsgId;
        $this->randomId     = $this->generateRandomId();
    }

    private function generateRandomId(): int
    {
        $bytes = random_bytes(8);
        return unpack('q', $bytes)[1];
    }

    public function setNoWebpage(bool $v): self   { $this->noWebpage  = $v; return $this; }
    public function setSilent(bool $v): self      { $this->silent     = $v; return $this; }
    public function setClearDraft(bool $v): self  { $this->clearDraft = $v; return $this; }
    public function getRandomId(): int            { return $this->randomId; }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->replyToMsgId !== null) $flags |= (1 << 0);  // reply_to
        if ($this->noWebpage)             $flags |= (1 << 1);  // no_webpage
        if ($this->silent)                $flags |= (1 << 5);  // silent
        if ($this->clearDraft)            $flags |= (1 << 7);  // clear_draft

        $writer->writeInt($flags);

        $this->peer->serialize($writer);

        // reply_to: flags.0 → InputReplyTo (inputReplyToMessage#a6b1e39a)
        if ($this->replyToMsgId !== null) {
            $writer->writeInt(0xa6b1e39a); // inputReplyToMessage constructor
            $writer->writeInt(0);          // flags (no optional fields)
            $writer->writeInt($this->replyToMsgId);
        }

        $writer->writeString($this->message);
        $writer->writeLong($this->randomId);
    }

    public function toDict(): array
    {
        return [
            '_'         => 'messages.sendMessage',
            'peer'      => ['type' => $this->peer->getType(), 'id' => $this->peer->getId()],
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
