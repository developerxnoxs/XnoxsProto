<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * users.getUsers#0d91a548 id:Vector<InputUser> = Vector<User>
 *
 * Pass inputUserSelf#f7c1b13f to get the current authenticated user.
 */
class UsersGetSelfRequest extends TLObject
{
    const CONSTRUCTOR = 0x0d91a548;

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // id:Vector<InputUser> = [inputUserSelf]
        $writer->writeInt(0x1cb5c415); // vector ctor
        $writer->writeInt(1);           // count = 1
        $writer->writeInt(0xf7c1b13f); // inputUserSelf constructor
    }

    public function toDict(): array
    {
        return ['_' => 'users.getUsers', 'id' => ['inputUserSelf']];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
