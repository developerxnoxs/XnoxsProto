<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * auth.importAuthorization#a57a7dad id:long bytes:bytes = auth.Authorization
 *
 * Used to authenticate on a different DC using exported credentials.
 */
class AuthImportAuthorizationRequest extends TLObject
{
    const CONSTRUCTOR = 0xa57a7dad;

    private int    $id;
    private string $bytes;

    public function __construct(int $id, string $bytes)
    {
        $this->id    = $id;
        $this->bytes = $bytes;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->id);
        $writer->writeBytes($this->bytes);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'auth.importAuthorization', 'id' => $this->id];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
