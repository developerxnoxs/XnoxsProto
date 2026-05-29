<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * help.getNearestDc#1fb33026 = NearestDc
 * Response: nearestDc#8e1a1775 country:string this_dc:int nearest_dc:int = NearestDc
 */
class HelpGetNearestDcRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0x1fb33026;

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);
    }

    public function toDict(): array
    {
        return ['_' => 'help.getNearestDc'];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
