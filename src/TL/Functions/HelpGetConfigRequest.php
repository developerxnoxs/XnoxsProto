<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * help.getConfig#c4f9186b = Config
 * Returns the current server configuration, including the supported API layer.
 */
class HelpGetConfigRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0xc4f9186b;

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);
    }

    public function toDict(): array
    {
        return ['_' => 'help.getConfig'];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
