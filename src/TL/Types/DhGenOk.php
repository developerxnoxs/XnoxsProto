<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class DhGenOk extends TLObject
{
    const CONSTRUCTOR_ID = 0x3bcbf734;

    public string $nonce;
    public string $serverNonce;
    public string $newNonceHash1;

    public function __construct(string $nonce, string $serverNonce, string $newNonceHash1)
    {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->newNonceHash1 = $newNonceHash1;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $newNonceHash1 = $reader->read(16);
        
        return new self($nonce, $serverNonce, $newNonceHash1);
    }

    public function toDict(): array
    {
        return [
            '_' => 'dh_gen_ok',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'new_nonce_hash1' => bin2hex($this->newNonceHash1)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= $this->newNonceHash1;
        
        return $r;
    }
}
