<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class DhGenFail extends TLObject
{
    const CONSTRUCTOR_ID = 0xa69dae02;

    public string $nonce;
    public string $serverNonce;
    public string $newNonceHash3;

    public function __construct(string $nonce, string $serverNonce, string $newNonceHash3)
    {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->newNonceHash3 = $newNonceHash3;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $newNonceHash3 = $reader->read(16);
        
        return new self($nonce, $serverNonce, $newNonceHash3);
    }

    public function toDict(): array
    {
        return [
            '_' => 'dh_gen_fail',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'new_nonce_hash3' => bin2hex($this->newNonceHash3)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= $this->newNonceHash3;
        
        return $r;
    }
}
