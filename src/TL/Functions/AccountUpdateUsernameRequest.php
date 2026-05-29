<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * account.updateUsername#3e0bdd7c username:string = User
 */
class AccountUpdateUsernameRequest extends TLObject
{
    const CONSTRUCTOR = 0x3e0bdd7c;

    private string $username;

    public function __construct(string $username)
    {
        $this->username = $username;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeString($this->username);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'account.updateUsername', 'username' => $this->username];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
