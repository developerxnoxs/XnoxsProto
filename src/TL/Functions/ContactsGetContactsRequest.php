<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * contacts.getContacts#5dd69e12 hash:long = contacts.Contacts;
 *
 * Mengirim hash=0 selalu mengambil daftar fresh.
 * Response: contacts.contacts#eae87e42 atau contacts.contactsNotModified#b74ba9d2
 */
class ContactsGetContactsRequest extends TLObject
{
    const CONSTRUCTOR = 0x5dd69e12;

    private int $hash;

    public function __construct(int $hash = 0)
    {
        $this->hash = $hash;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->hash);
    }

    public function toDict(): array
    {
        return ['_' => 'contacts.getContacts', 'hash' => $this->hash];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
