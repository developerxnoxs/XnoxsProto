<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Types\User;

class AuthAuthorization
{
    const CONSTRUCTOR_ID = 0x2ea2c0d4;
    
    public int $flags;
    public bool $setupPasswordRequired = false;
    public ?int $otherwiseReloginDays = null;
    public ?int $tmpSessions = null;
    public ?string $futureAuthToken = null;
    public User $user;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj = new self();
        
        $obj->flags = $reader->readInt();
        
        if ($obj->flags & (1 << 1)) {
            $obj->setupPasswordRequired = true;
            $obj->otherwiseReloginDays = $reader->readInt();
        }
        
        if ($obj->flags & (1 << 0)) {
            $obj->tmpSessions = $reader->readInt();
        }
        
        if ($obj->flags & (1 << 2)) {
            $obj->futureAuthToken = $reader->readBytes();
        }
        
        $userConstructor = $reader->readInt();
        $obj->user = User::fromReader($reader);
        
        return $obj;
    }
}
