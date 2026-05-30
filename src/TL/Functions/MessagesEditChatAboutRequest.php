<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.editChatAbout#def60797 peer:InputPeer about:string = Bool;
 *
 * Ubah deskripsi basic group, supergroup, atau channel.
 * Untuk supergroup/channel, gunakan ChannelsEditAboutRequest.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 */
class MessagesEditChatAboutRequest extends TLObject
{
    const CONSTRUCTOR = 0xdef60797;

    public function __construct(
        private InputPeer $peer,
        private string    $about
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $this->peer->serialize($writer);
        $writer->writeString($this->about);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'     => 'messages.editChatAbout',
            'about' => $this->about,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
