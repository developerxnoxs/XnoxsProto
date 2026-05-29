<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.startBot#e6df7378
 *   bot:InputUser
 *   peer:InputPeer
 *   random_id:long
 *   start_param:string
 *   = Updates;
 *
 * InputUser constructors:
 *   inputUserSelf#f7c1b13f
 *   inputUser#7b8e7de6    user_id:long access_hash:long
 */
class MessagesStartBotRequest extends TLObject
{
    const CONSTRUCTOR = 0xe6df7378;

    private int       $botUserId;
    private int       $botAccessHash;
    private InputPeer $peer;
    private string    $startParam;

    public function __construct(
        int       $botUserId,
        int       $botAccessHash,
        InputPeer $peer,
        string    $startParam = ''
    ) {
        $this->botUserId      = $botUserId;
        $this->botAccessHash  = $botAccessHash;
        $this->peer           = $peer;
        $this->startParam     = $startParam;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // bot:InputUser → inputUser#7b8e7de6
        $writer->writeInt(0x7b8e7de6);
        $writer->writeLong($this->botUserId);
        $writer->writeLong($this->botAccessHash);

        // peer:InputPeer
        $this->peer->serialize($writer);

        // random_id:long
        $writer->writeLong(unpack('P', random_bytes(8))[1]);

        // start_param:string
        $writer->writeString($this->startParam);
    }

    public function toDict(): array
    {
        return [
            '_'           => 'messages.startBot',
            'bot_user_id' => $this->botUserId,
            'start_param' => $this->startParam,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
