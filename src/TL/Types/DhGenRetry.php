<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class DhGenRetry extends TLObject
{
    const CONSTRUCTOR_ID = 0x46dc1fb9;

    public string $nonce;
    public string $serverNonce;
    public string $newNonceHash2;

    public function __construct(string $nonce, string $serverNonce, string $newNonceHash2)
    {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->newNonceHash2 = $newNonceHash2;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $newNonceHash2 = $reader->read(16);
        
        return new self($nonce, $serverNonce, $newNonceHash2);
    }

    public function toDict(): array
    {
        return [
            '_' => 'dh_gen_retry',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'new_nonce_hash2' => bin2hex($this->newNonceHash2)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= $this->newNonceHash2;
        
        return $r;
    }
}
