<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * photos.deletePhotos#87cf7f2f id:Vector<InputPhoto> = Vector<long>
 *
 * inputPhoto#3bb3b94a id:long access_hash:long file_reference:bytes
 */
class PhotosDeletePhotosRequest extends TLObject
{
    const CONSTRUCTOR = 0x87cf7f2f;

    /** @var array[] Array of ['id'=>int, 'access_hash'=>int, 'file_reference'=>string] */
    private array $photos;

    public function __construct(array $photos)
    {
        $this->photos = $photos;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // Vector<InputPhoto>
        $writer->writeInt(0x1cb5c415); // vector constructor
        $writer->writeInt(count($this->photos));

        foreach ($this->photos as $p) {
            // inputPhoto#3bb3b94a
            $writer->writeInt(0x3bb3b94a);
            $writer->writeLong($p['id']);
            $writer->writeLong($p['access_hash']);
            $writer->writeBytes($p['file_reference']);
        }
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'     => 'photos.deletePhotos',
            'count' => count($this->photos),
            'ids'   => array_column($this->photos, 'id'),
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
