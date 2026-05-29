<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.inviteToChannel#c9e33d54
 *   channel:InputChannel
 *   users:Vector<InputUser>
 *   = messages.InvitedUsers;
 *
 * CRC verified from TDLib telegram_api.tl (Layer 214).
 * inputChannel#f35aec28 channel_id:long access_hash:long
 * inputUser#f21158c6    user_id:long    access_hash:long
 */
class ChannelsInviteToChannelRequest extends TLObject
{
    const CONSTRUCTOR = 0xc9e33d54;

    /** @param array<array{user_id:int, user_hash:int}> $users */
    public function __construct(
        private int   $channelId,
        private int   $channelAccessHash,
        private array $users    // [ ['user_id'=>int, 'user_hash'=>int], ... ]
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->channelAccessHash);

        // users:Vector<InputUser>
        $writer->writeInt(0x1cb5c415); // vector ctor
        $writer->writeInt(count($this->users));
        foreach ($this->users as $u) {
            // InputUser#f21158c6
            $writer->writeInt(0xf21158c6);
            $writer->writeLong((int)($u['user_id']   ?? 0));
            $writer->writeLong((int)($u['user_hash'] ?? 0));
        }
    }

    public function toDict(): array
    {
        return [
            '_'          => 'channels.inviteToChannel',
            'channel_id' => $this->channelId,
            'users'      => $this->users,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
