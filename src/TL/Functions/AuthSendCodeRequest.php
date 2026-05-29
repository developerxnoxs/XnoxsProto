<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

class AuthSendCodeRequest extends TLObject
{
    const CONSTRUCTOR = 0xa677244f;
    
    private string $phoneNumber;
    private int $apiId;
    private string $apiHash;
    private array $settings;

    public function __construct(string $phoneNumber, int $apiId, string $apiHash)
    {
        $this->phoneNumber = $phoneNumber;
        $this->apiId = $apiId;
        $this->apiHash = $apiHash;
        $this->settings = [
            '_' => 'codeSettings',
            'allow_flashcall' => false,
            'current_number' => false,
            'allow_app_hash' => false
        ];
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeString($this->phoneNumber);
        $writer->writeInt($this->apiId);
        $writer->writeString($this->apiHash);
        
        $writer->writeInt(0xdebebe83);
        
        $flags = 0;
        $writer->writeInt($flags);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_' => 'auth.sendCode',
            'phone_number' => $this->phoneNumber,
            'api_id' => $this->apiId,
            'api_hash' => $this->apiHash
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
