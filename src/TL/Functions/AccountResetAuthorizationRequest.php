<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * account.resetAuthorization#df77f3bc hash:long = Bool
 */
class AccountResetAuthorizationRequest extends TLObject
{
    const CONSTRUCTOR = 0xdf77f3bc;

    private int $hash;

    public function __construct(int $hash)
    {
        $this->hash = $hash;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->hash);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'account.resetAuthorization', 'hash' => $this->hash];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
