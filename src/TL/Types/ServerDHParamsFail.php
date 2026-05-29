<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class ServerDHParamsFail extends TLObject
{
    const CONSTRUCTOR_ID = 0x79cb045d;

    public string $nonce;
    public string $serverNonce;
    public string $newNonceHash;

    public function __construct(string $nonce, string $serverNonce, string $newNonceHash)
    {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->newNonceHash = $newNonceHash;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $newNonceHash = $reader->read(16);
        
        return new self($nonce, $serverNonce, $newNonceHash);
    }

    public function toDict(): array
    {
        return [
            '_' => 'server_DH_params_fail',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'new_nonce_hash' => bin2hex($this->newNonceHash)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= $this->newNonceHash;
        
        return $r;
    }
}
