<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class ServerDHInnerData extends TLObject
{
    const CONSTRUCTOR_ID = 0xb5890dba;

    public string $nonce;
    public string $serverNonce;
    public int $g;
    public string $dhPrime;
    public string $gA;
    public int $serverTime;

    public function __construct(
        string $nonce,
        string $serverNonce,
        int $g,
        string $dhPrime,
        string $gA,
        int $serverTime
    ) {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->g = $g;
        $this->dhPrime = $dhPrime;
        $this->gA = $gA;
        $this->serverTime = $serverTime;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $g = $reader->readInt();
        $dhPrime = $reader->readBytes();
        $gA = $reader->readBytes();
        $serverTime = $reader->readInt();
        
        return new self($nonce, $serverNonce, $g, $dhPrime, $gA, $serverTime);
    }

    public function toDict(): array
    {
        return [
            '_' => 'server_DH_inner_data',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'g' => $this->g,
            'dh_prime' => bin2hex($this->dhPrime),
            'g_a' => bin2hex($this->gA),
            'server_time' => $this->serverTime
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= pack('V', $this->g);
        $r .= self::serializeBytes($this->dhPrime);
        $r .= self::serializeBytes($this->gA);
        $r .= pack('V', $this->serverTime);
        
        return $r;
    }
}
