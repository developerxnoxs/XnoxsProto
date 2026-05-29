<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\Helpers\Helpers;

class PQInnerData extends TLObject
{
    const CONSTRUCTOR_ID = 0x83c95aec;

    public string $pq;
    public string $p;
    public string $q;
    public string $nonce;
    public string $serverNonce;
    public string $newNonce;

    public function __construct(string $pq, string $p, string $q, string $nonce, string $serverNonce, string $newNonce)
    {
        $this->pq = $pq;
        $this->p = $p;
        $this->q = $q;
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->newNonce = $newNonce;
    }

    public function toDict(): array
    {
        return [
            '_' => 'PQInnerData',
            'pq' => bin2hex($this->pq),
            'p' => bin2hex($this->p),
            'q' => bin2hex($this->q),
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'new_nonce' => bin2hex($this->newNonce)
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        
        $writer->writeInt(self::CONSTRUCTOR_ID);
        $writer->writeBytes($this->pq);
        $writer->writeBytes($this->p);
        $writer->writeBytes($this->q);
        $writer->write($this->nonce);
        $writer->write($this->serverNonce);
        $writer->write($this->newNonce);
        
        return $writer->getValue();
    }
}
