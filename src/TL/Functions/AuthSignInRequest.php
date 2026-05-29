<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

class AuthSignInRequest extends TLObject
{
    const CONSTRUCTOR = 0x8d52a951;
    
    private string $phoneNumber;
    private string $phoneCodeHash;
    private string $phoneCode;

    public function __construct(string $phoneNumber, string $phoneCodeHash, string $phoneCode)
    {
        $this->phoneNumber = $phoneNumber;
        $this->phoneCodeHash = $phoneCodeHash;
        $this->phoneCode = $phoneCode;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        
        // Set bit 0 of flags since phone_code is present (flags.0?string in TL schema)
        $flags = 1;
        $writer->writeInt($flags);
        
        $writer->writeString($this->phoneNumber);
        $writer->writeString($this->phoneCodeHash);
        
        // Write phone_code only if flags.0 is set (which we did above)
        $writer->writeString($this->phoneCode);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_' => 'auth.signIn',
            'phone_number' => $this->phoneNumber,
            'phone_code_hash' => $this->phoneCodeHash,
            'phone_code' => $this->phoneCode
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
