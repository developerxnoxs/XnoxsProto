<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * auth.exportAuthorization#e5bfffcd dc_id:int = auth.ExportedAuthorization
 *
 * Response: auth.exportedAuthorization#b5f66a1c id:long bytes:bytes
 */
class AuthExportAuthorizationRequest extends TLObject
{
    const CONSTRUCTOR = 0xe5bfffcd;

    private int $dcId;

    public function __construct(int $dcId)
    {
        $this->dcId = $dcId;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt($this->dcId);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'auth.exportAuthorization', 'dc_id' => $this->dcId];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
