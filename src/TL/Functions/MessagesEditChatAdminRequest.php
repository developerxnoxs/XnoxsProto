<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.editChatAdmin#a85bd1c2
 *   chat_id:long
 *   user_id:InputUser
 *   is_admin:Bool
 *   = Bool;
 *
 * Digunakan untuk promote/demote admin di basic group (bukan supergroup).
 * Supergroup/channel: gunakan channels.editAdmin#9a98ad68.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputUser#f21158c6 user_id:long access_hash:long
 * boolTrue#997275b5
 * boolFalse#bc799737
 */
class MessagesEditChatAdminRequest extends TLObject
{
    const CONSTRUCTOR = 0xa85bd1c2;

    const BOOL_TRUE  = 0x997275b5;
    const BOOL_FALSE = 0xbc799737;

    public function __construct(
        private int  $chatId,
        private int  $userId,
        private int  $userHash,
        private bool $isAdmin
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // chat_id:long
        $writer->writeLong($this->chatId);

        // user_id:InputUser#f21158c6
        $writer->writeInt(0xf21158c6);
        $writer->writeLong($this->userId);
        $writer->writeLong($this->userHash);

        // is_admin:Bool
        $writer->writeInt($this->isAdmin ? self::BOOL_TRUE : self::BOOL_FALSE);
    }

    public function toDict(): array
    {
        return [
            '_'        => 'messages.editChatAdmin',
            'chat_id'  => $this->chatId,
            'user_id'  => $this->userId,
            'is_admin' => $this->isAdmin,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
