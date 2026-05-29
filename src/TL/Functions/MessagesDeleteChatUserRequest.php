<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.deleteChatUser#a2185cab
 *   flags:#
 *   revoke_history:flags.0?true
 *   chat_id:long
 *   user_id:InputUser
 *   = Updates;
 *
 * Digunakan untuk kick user dari basic group (bukan supergroup).
 * Supergroup/channel: gunakan channels.editBanned#96e6cd81.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 *
 * inputUser#f21158c6 user_id:long access_hash:long
 */
class MessagesDeleteChatUserRequest extends TLObject
{
    const CONSTRUCTOR = 0xa2185cab;

    const FLAG_REVOKE_HISTORY = 1 << 0;  // 0x1

    public function __construct(
        private int  $chatId,
        private int  $userId,
        private int  $userHash,
        private bool $revokeHistory = false
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:#
        $flags = $this->revokeHistory ? self::FLAG_REVOKE_HISTORY : 0;
        $writer->writeInt($flags);

        // chat_id:long
        $writer->writeLong($this->chatId);

        // user_id:InputUser#f21158c6
        $writer->writeInt(0xf21158c6);
        $writer->writeLong($this->userId);
        $writer->writeLong($this->userHash);
    }

    public function toDict(): array
    {
        return [
            '_'               => 'messages.deleteChatUser',
            'chat_id'         => $this->chatId,
            'user_id'         => $this->userId,
            'revoke_history'  => $this->revokeHistory,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
