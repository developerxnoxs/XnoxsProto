<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.importChatInvite#6c50051c hash:string = Updates;
 *
 * Used to join a group/channel via invite link (t.me/joinchat/HASH or t.me/+HASH).
 */
class MessagesImportChatInviteRequest extends TLObject
{
    const CONSTRUCTOR = 0x6c50051c;

    private string $hash;

    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeString($this->hash);
    }

    public function toDict(): array
    {
        return ['_' => 'messages.importChatInvite', 'hash' => $this->hash];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
