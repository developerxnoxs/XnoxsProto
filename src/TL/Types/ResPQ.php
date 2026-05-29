<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class ResPQ extends TLObject
{
    const CONSTRUCTOR_ID = 0x05162463;

    public string $nonce;
    public string $serverNonce;
    public string $pq;
    public array $serverPublicKeyFingerprints;

    public function __construct(array $data = [])
    {
        $this->nonce = $data['nonce'] ?? str_repeat("\0", 16);
        $this->serverNonce = $data['server_nonce'] ?? str_repeat("\0", 16);
        $this->pq = $data['pq'] ?? '';
        $this->serverPublicKeyFingerprints = $data['server_public_key_fingerprints'] ?? [];
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $pq = $reader->readBytes();
        
        $vectorId = $reader->readInt();
        if ($vectorId !== 0x1cb5c415) {
            throw new \RuntimeException('Expected vector constructor');
        }
        
        $count = $reader->readInt();
        $fingerprints = [];
        for ($i = 0; $i < $count; $i++) {
            $fingerprints[] = $reader->readLong();
        }
        
        return new self([
            'nonce' => $nonce,
            'server_nonce' => $serverNonce,
            'pq' => $pq,
            'server_public_key_fingerprints' => $fingerprints
        ]);
    }

    public function toDict(): array
    {
        return [
            '_' => 'ResPQ',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'pq' => bin2hex($this->pq),
            'server_public_key_fingerprints' => $this->serverPublicKeyFingerprints
        ];
    }

    public function toBytes(): string
    {
        throw new \RuntimeException('ResPQ is a response type, not meant to be serialized');
    }
}
