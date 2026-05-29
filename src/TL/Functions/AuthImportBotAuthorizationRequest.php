<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * auth.importBotAuthorization#67a3ff2c flags:int api_id:int api_hash:string bot_auth_token:string = auth.Authorization
 */
class AuthImportBotAuthorizationRequest extends TLObject
{
    const CONSTRUCTOR = 0x67a3ff2c;

    private int    $apiId;
    private string $apiHash;
    private string $botToken;

    public function __construct(int $apiId, string $apiHash, string $botToken)
    {
        $this->apiId    = $apiId;
        $this->apiHash  = $apiHash;
        $this->botToken = $botToken;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt(0); // flags
        $writer->writeInt($this->apiId);
        $writer->writeString($this->apiHash);
        $writer->writeString($this->botToken);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'        => 'auth.importBotAuthorization',
            'api_id'   => $this->apiId,
            'api_hash' => $this->apiHash,
            'bot_auth_token' => $this->botToken,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
