<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * users.getFullUser#b60f5918
 *   id:InputUser
 *   = users.UserFull;
 *
 * Response: users.userFull#3b6d152e
 *   full_user:UserFull   chats:Vector<Chat>   users:Vector<User>
 *
 * InputUser constructors:
 *   inputUserSelf#f7c1b13f              — current user
 *   inputUser#f21158c6 user_id:long access_hash:long
 */
class UsersGetFullUserRequest extends TLObject
{
    const CONSTRUCTOR = 0xb60f5918;

    const INPUT_USER_SELF = 0xf7c1b13f;
    const INPUT_USER      = 0xf21158c6;

    private bool $isSelf;
    private int  $userId;
    private int  $accessHash;

    public static function self(): self
    {
        $r = new self();
        $r->isSelf     = true;
        $r->userId     = 0;
        $r->accessHash = 0;
        return $r;
    }

    public static function byId(int $userId, int $accessHash): self
    {
        $r = new self();
        $r->isSelf     = false;
        $r->userId     = $userId;
        $r->accessHash = $accessHash;
        return $r;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        if ($this->isSelf) {
            $writer->writeInt(self::INPUT_USER_SELF);
        } else {
            $writer->writeInt(self::INPUT_USER);
            $writer->writeLong($this->userId);
            $writer->writeLong($this->accessHash);
        }
    }

    public function toDict(): array
    {
        return [
            '_'       => 'users.getFullUser',
            'user_id' => $this->isSelf ? 'self' : $this->userId,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
