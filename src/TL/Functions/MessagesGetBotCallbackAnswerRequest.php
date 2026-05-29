<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.getBotCallbackAnswer#9342ca07
 *   flags:#
 *   game:flags.1?true
 *   peer:InputPeer
 *   msg_id:int
 *   data:flags.0?bytes
 *   password:flags.2?InputCheckPasswordSRP
 *   = messages.BotCallbackAnswer;
 */
class MessagesGetBotCallbackAnswerRequest extends TLObject
{
    const CONSTRUCTOR = 0x9342ca07;

    private InputPeer $peer;
    private int       $msgId;
    private ?string   $data;
    private bool      $game;

    public function __construct(
        InputPeer $peer,
        int       $msgId,
        ?string   $data = null,
        bool      $game = false
    ) {
        $this->peer  = $peer;
        $this->msgId = $msgId;
        $this->data  = $data;
        $this->game  = $game;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:#
        $flags = 0;
        if ($this->data !== null) $flags |= (1 << 0); // data present
        if ($this->game)          $flags |= (1 << 1); // game flag
        $writer->writeInt($flags);

        // peer:InputPeer
        $this->peer->serialize($writer);

        // msg_id:int
        $writer->writeInt($this->msgId);

        // data:flags.0?bytes
        if ($this->data !== null) {
            $writer->writeBytes($this->data);
        }
    }

    public function toDict(): array
    {
        return [
            '_'      => 'messages.getBotCallbackAnswer',
            'msg_id' => $this->msgId,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
