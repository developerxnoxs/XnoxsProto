<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * account.updateProfile#78515775 flags:# first_name:flags.0?string last_name:flags.1?string about:flags.2?string = User
 */
class AccountUpdateProfileRequest extends TLObject
{
    const CONSTRUCTOR = 0x78515775;

    private ?string $firstName;
    private ?string $lastName;
    private ?string $about;

    public function __construct(?string $firstName = null, ?string $lastName = null, ?string $about = null)
    {
        $this->firstName = $firstName;
        $this->lastName  = $lastName;
        $this->about     = $about;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = 0;
        if ($this->firstName !== null) $flags |= (1 << 0);
        if ($this->lastName  !== null) $flags |= (1 << 1);
        if ($this->about     !== null) $flags |= (1 << 2);
        $writer->writeInt($flags);

        if ($this->firstName !== null) $writer->writeString($this->firstName);
        if ($this->lastName  !== null) $writer->writeString($this->lastName);
        if ($this->about     !== null) $writer->writeString($this->about);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        $d = ['_' => 'account.updateProfile'];
        if ($this->firstName !== null) $d['first_name'] = $this->firstName;
        if ($this->lastName  !== null) $d['last_name']  = $this->lastName;
        if ($this->about     !== null) $d['about']      = $this->about;
        return $d;
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
