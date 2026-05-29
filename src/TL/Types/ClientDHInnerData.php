<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class ClientDHInnerData extends TLObject
{
    const CONSTRUCTOR_ID = 0x6643b654;

    public string $nonce;
    public string $serverNonce;
    public int $retryId;
    public string $gB;

    public function __construct(
        string $nonce,
        string $serverNonce,
        int $retryId,
        string $gB
    ) {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->retryId = $retryId;
        $this->gB = $gB;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $retryId = $reader->readLong();
        $gB = $reader->readBytes();
        
        return new self($nonce, $serverNonce, $retryId, $gB);
    }

    public function toDict(): array
    {
        return [
            '_' => 'client_DH_inner_data',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'retry_id' => $this->retryId,
            'g_b' => bin2hex($this->gB)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= pack('P', $this->retryId);
        $r .= self::serializeBytes($this->gB);
        
        return $r;
    }
}
