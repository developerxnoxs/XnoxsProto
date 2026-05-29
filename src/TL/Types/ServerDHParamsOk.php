<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryReader;

class ServerDHParamsOk extends TLObject
{
    const CONSTRUCTOR_ID = 0xd0e8075c;

    public string $nonce;
    public string $serverNonce;
    public string $encryptedAnswer;

    public function __construct(string $nonce, string $serverNonce, string $encryptedAnswer)
    {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->encryptedAnswer = $encryptedAnswer;
    }

    public static function fromReader(BinaryReader $reader): self
    {
        $nonce = $reader->read(16);
        $serverNonce = $reader->read(16);
        $encryptedAnswer = $reader->readBytes();
        
        return new self($nonce, $serverNonce, $encryptedAnswer);
    }

    public function toDict(): array
    {
        return [
            '_' => 'server_DH_params_ok',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'encrypted_answer' => bin2hex($this->encryptedAnswer)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= self::serializeBytes($this->encryptedAnswer);
        
        return $r;
    }
}
