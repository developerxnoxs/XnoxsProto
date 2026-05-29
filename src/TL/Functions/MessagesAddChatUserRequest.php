<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.addChatUser#cbc6d107
 *   chat_id:long
 *   user_id:InputUser
 *   fwd_limit:int
 *   = messages.InvitedUsers;
 *
 * Tambahkan user ke basic group.
 * Untuk supergroup/channel gunakan channels.inviteToChannel#c9e33d54.
 *
 * fwd_limit — berapa pesan terakhir yang bisa dilihat oleh user yang baru masuk.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputUser#f21158c6 user_id:long access_hash:long
 */
class MessagesAddChatUserRequest extends TLObject
{
    const CONSTRUCTOR = 0xcbc6d107;

    public function __construct(
        private int $chatId,
        private int $userId,
        private int $userHash,
        private int $fwdLimit = 100
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

        // fwd_limit:int
        $writer->writeInt($this->fwdLimit);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'         => 'messages.addChatUser',
            'chat_id'   => $this->chatId,
            'user_id'   => $this->userId,
            'fwd_limit' => $this->fwdLimit,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
