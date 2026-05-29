<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * auth.checkPassword#d18b4d16 password:InputCheckPasswordSRP = auth.Authorization
 */
class AuthCheckPasswordRequest extends TLObject
{
    const CONSTRUCTOR = 0xd18b4d16;

    private int    $srpId;
    private string $A;
    private string $M1;

    public function __construct(int $srpId, string $A, string $M1)
    {
        $this->srpId = $srpId;
        $this->A     = $A;
        $this->M1    = $M1;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        // inputCheckPasswordSRP#d27ff082 srp_id:long A:bytes M1:bytes
        $writer->writeInt(0xd27ff082);
        $writer->writeLong($this->srpId);
        $writer->writeBytes($this->A);
        $writer->writeBytes($this->M1);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'      => 'auth.checkPassword',
            'srp_id' => $this->srpId,
            'A'      => $this->A,
            'M1'     => $this->M1,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
