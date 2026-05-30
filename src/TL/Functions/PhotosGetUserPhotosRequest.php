<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * photos.getUserPhotos#91cd32a8
 *   user_id:InputUser  offset:int  max_id:long  limit:int  = photos.Photos
 */
class PhotosGetUserPhotosRequest extends TLObject
{
    const CONSTRUCTOR = 0x91cd32a8;

    private int $offset;
    private int $maxId;
    private int $limit;

    public function __construct(int $offset = 0, int $maxId = 0, int $limit = 100)
    {
        $this->offset = $offset;
        $this->maxId  = $maxId;
        $this->limit  = $limit;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // user_id = inputUserSelf#f7c1b13f (tidak ada field tambahan)
        $writer->writeInt(0xf7c1b13f);

        $writer->writeInt($this->offset);
        $writer->writeLong($this->maxId);
        $writer->writeInt($this->limit);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'      => 'photos.getUserPhotos',
            'offset' => $this->offset,
            'max_id' => $this->maxId,
            'limit'  => $this->limit,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
