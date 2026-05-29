<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * contacts.resolveUsername#f93ccba3 username:string = contacts.ResolvedPeer
 *
 * Response: contacts.resolvedPeer#7f077ad9
 *   peer:Peer
 *   chats:Vector<Chat>
 *   users:Vector<User>
 */
class ContactsResolveUsernameRequest extends TLObject
{
    const CONSTRUCTOR = 0xf93ccba3;

    private string $username;

    public function __construct(string $username)
    {
        // Strip leading @ jika ada
        $this->username = ltrim($username, '@');
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeString($this->username);
    }

    public function toDict(): array
    {
        return ['_' => 'contacts.resolveUsername', 'username' => $this->username];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
