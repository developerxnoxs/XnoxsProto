<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;

/**
 * auth.loginTokenSuccess#390d5c5e authorization:auth.Authorization = auth.LoginToken
 *
 * Returned by auth.exportLoginToken (or auth.importLoginToken) once the QR
 * code has been scanned and accepted by an already-logged-in Telegram app.
 * The embedded `authorization` contains the full auth.Authorization object.
 */
class AuthLoginTokenSuccess
{
    const CONSTRUCTOR_ID = 0x390d5c5e;

    public AuthAuthorization $authorization;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj = new self();

        // auth.authorization constructor
        $authCtor = $reader->readInt();
        $obj->authorization = AuthAuthorization::fromReader($reader);

        return $obj;
    }
}
