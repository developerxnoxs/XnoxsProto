<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryWriter;

/**
 * InputPeer — menentukan tujuan/sumber pesan.
 *
 * Constructor IDs dari TL schema Telegram:
 *   inputPeerEmpty    = 0x7f3b18ea
 *   inputPeerSelf     = 0x7da07ec9
 *   inputPeerUser     = 0xdde8a54c  (user_id:long  access_hash:long)
 *   inputPeerChat     = 0x35a95cb9  (chat_id:long)
 *   inputPeerChannel  = 0x27bcbbfc  (channel_id:long  access_hash:long)
 */
class InputPeer
{
    public const EMPTY_   = 0x7f3b18ea;
    public const SELF     = 0x7da07ec9;
    public const USER     = 0xdde8a54c;
    public const CHAT     = 0x35a95cb9;
    public const CHANNEL  = 0x27bcbbfc;

    private int $type;
    private int $id         = 0;
    private int $accessHash = 0;

    private function __construct(int $type, int $id = 0, int $accessHash = 0)
    {
        $this->type       = $type;
        $this->id         = $id;
        $this->accessHash = $accessHash;
    }

    /** inputPeerEmpty — untuk offset_peer pada getDialogs pertama */
    public static function empty(): self
    {
        return new self(self::EMPTY_);
    }

    /** Saved Messages (kirim ke diri sendiri) */
    public static function self(): self
    {
        return new self(self::SELF);
    }

    /** Kirim ke user biasa (perlu user_id + access_hash) */
    public static function user(int $userId, int $accessHash): self
    {
        return new self(self::USER, $userId, $accessHash);
    }

    /** Kirim ke group biasa */
    public static function chat(int $chatId): self
    {
        return new self(self::CHAT, $chatId);
    }

    /** Kirim ke supergroup / channel */
    public static function channel(int $channelId, int $accessHash): self
    {
        return new self(self::CHANNEL, $channelId, $accessHash);
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt($this->type);

        switch ($this->type) {
            case self::EMPTY_:
            case self::SELF:
                // tidak ada field tambahan
                break;

            case self::USER:
                $writer->writeLong($this->id);
                $writer->writeLong($this->accessHash);
                break;

            case self::CHAT:
                $writer->writeLong($this->id);
                break;

            case self::CHANNEL:
                $writer->writeLong($this->id);
                $writer->writeLong($this->accessHash);
                break;
        }
    }

    public function getId(): int         { return $this->id; }
    public function getAccessHash(): int { return $this->accessHash; }
    public function getType(): int       { return $this->type; }
}
