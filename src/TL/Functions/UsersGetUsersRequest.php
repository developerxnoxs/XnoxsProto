<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * users.getUsers#0d91a548 id:Vector<InputUser> = Vector<User>
 *
 * Batch-fetch user info by ID + access_hash.
 * Untuk min-user tanpa access_hash: access_hash=0 tetap bekerja jika user adalah kontak.
 *
 * inputUser#f21158c6 user_id:long access_hash:long
 */
class UsersGetUsersRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0x0d91a548;
    private const INPUT_USER    = 0xf21158c6;
    private const VECTOR_CTOR   = 0x1cb5c415;

    /**
     * @var array<array{id:int, access_hash:int}>
     * Setiap elemen: ['id' => int, 'access_hash' => int]
     */
    private array $users;

    /**
     * @param array<array{id:int, access_hash:int}> $users
     */
    public function __construct(array $users)
    {
        $this->users = $users;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);

        $writer->writeInt(self::VECTOR_CTOR);
        $writer->writeInt(count($this->users));

        foreach ($this->users as $u) {
            $writer->writeInt(self::INPUT_USER);
            $writer->writeLong($u['id']);
            $writer->writeLong($u['access_hash'] ?? 0);
        }
    }

    public function toDict(): array
    {
        return ['_' => 'users.getUsers', 'count' => count($this->users)];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
