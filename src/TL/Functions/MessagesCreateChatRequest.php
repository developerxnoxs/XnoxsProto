<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * messages.createChat#92ceddd4 flags:#
 *   users:Vector<InputUser>
 *   title:string
 *   ttl_period:flags.0?int
 *   = Updates;
 *
 * Membuat grup biasa (basic group) baru.
 * Untuk supergroup gunakan channels.createChannel#91006707.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 162+, termasuk 214).
 *
 * inputUser#f21158c6 user_id:long access_hash:long
 */
class MessagesCreateChatRequest extends TLObject
{
    const CONSTRUCTOR = 0x92ceddd4;

    /**
     * @param string                                   $title  Judul grup (1–255 karakter)
     * @param array<array{user_id:int, user_hash:int}> $users  Daftar user yang diundang
     */
    public function __construct(
        private string $title,
        private array  $users
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:# — bit 0 = ttl_period present (kita tidak pakai)
        $writer->writeInt(0);

        // users:Vector<InputUser>
        $writer->writeInt(0x1cb5c415); // vector ctor
        $writer->writeInt(count($this->users));
        foreach ($this->users as $u) {
            $writer->writeInt(0xf21158c6); // InputUser#f21158c6
            $writer->writeLong((int)($u['user_id']   ?? 0));
            $writer->writeLong((int)($u['user_hash'] ?? 0));
        }

        // title:string
        $writer->writeString($this->title);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'     => 'messages.createChat',
            'title' => $this->title,
            'users' => $this->users,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
