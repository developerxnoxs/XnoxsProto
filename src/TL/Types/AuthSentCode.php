<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;

class AuthSentCode
{
    const CONSTRUCTOR_ID = 0x5e002502;
    
    public int $flags;
    public $type;
    public string $phoneCodeHash;
    public $nextType = null;
    public ?int $timeout = null;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj = new self();
        
        $obj->flags = $reader->readInt();
        
        $typeConstructor = $reader->readInt();
        $obj->type = [
            '_constructor' => $typeConstructor,
            'length' => $reader->readInt()
        ];
        
        $obj->phoneCodeHash = $reader->readString();
        
        if ($obj->flags & (1 << 1)) {
            $obj->nextType = $reader->readInt();
        }
        
        if ($obj->flags & (1 << 2)) {
            $obj->timeout = $reader->readInt();
        }
        
        return $obj;
    }
}
