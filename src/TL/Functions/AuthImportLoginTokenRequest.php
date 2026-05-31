<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * auth.importLoginToken#95ac5ce4 token:bytes = auth.LoginToken
 *
 * Called when auth.exportLoginToken returns auth.loginTokenMigrateTo,
 * meaning the login must be finalised on a different DC.
 * Connect to the indicated DC first, then call this with the new token.
 */
class AuthImportLoginTokenRequest extends TLObject
{
    const CONSTRUCTOR = 0x95ac5ce4;

    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeBytes($this->token);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'     => 'auth.importLoginToken',
            'token' => base64_encode($this->token),
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
