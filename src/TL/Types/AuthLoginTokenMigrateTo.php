<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;

/**
 * auth.loginTokenMigrateTo#68e9916 dc_id:int token:bytes = auth.LoginToken
 *
 * Returned when the authorising device is on a different DC.
 * The client must:
 *   1. Connect to dc_id
 *   2. Call auth.importLoginToken with the given token
 */
class AuthLoginTokenMigrateTo
{
    const CONSTRUCTOR_ID = 0x068e9916;

    public int    $dcId;
    public string $token;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj        = new self();
        $obj->dcId  = $reader->readInt();
        $obj->token = $reader->readBytes();
        return $obj;
    }
}
