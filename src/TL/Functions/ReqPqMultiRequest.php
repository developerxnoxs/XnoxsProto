<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

class ReqPqMultiRequest extends TLObject
{
    const CONSTRUCTOR_ID = 0xbe7e8ef1;

    public string $nonce;

    public function __construct(string $nonce)
    {
        if (strlen($nonce) !== 16) {
            throw new \InvalidArgumentException('Nonce must be 16 bytes');
        }
        $this->nonce = $nonce;
    }

    public function toDict(): array
    {
        return [
            '_' => 'ReqPqMulti',
            'nonce' => bin2hex($this->nonce)
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        
        $writer->writeInt(self::CONSTRUCTOR_ID);
        $writer->write($this->nonce);
        
        return $writer->getValue();
    }
}
