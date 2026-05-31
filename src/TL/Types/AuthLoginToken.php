<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;

/**
 * auth.loginToken#629f1980 expires:int token:bytes = auth.LoginToken
 *
 * Returned by auth.exportLoginToken when the QR code has not yet been scanned.
 * The `token` field must be base64url-encoded and embedded in a
 * tg://login?token=<base64url> URI, then rendered as a QR code.
 */
class AuthLoginToken
{
    const CONSTRUCTOR_ID = 0x629f1980;

    public int    $expires;
    public string $token;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj          = new self();
        $obj->expires = $reader->readInt();
        $obj->token   = $reader->readBytes();
        return $obj;
    }
}
