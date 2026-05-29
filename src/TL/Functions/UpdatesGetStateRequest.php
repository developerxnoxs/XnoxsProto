<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * updates.getState#edd4882a = updates.State;
 *
 * Response: updates.state#a56c2a3e
 *   pts:int  qts:int  date:int  seq:int  unread_count:int
 */
class UpdatesGetStateRequest extends TLObject
{
    const CONSTRUCTOR          = 0xedd4882a;
    const RESPONSE_CONSTRUCTOR = 0xa56c2a3e;

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
    }

    public function toDict(): array
    {
        return ['_' => 'updates.getState'];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
