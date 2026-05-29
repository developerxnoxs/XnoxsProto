<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * photos.uploadProfilePhoto#388a3b5 flags:# fallback:flags.3?true file:flags.0?InputFile = photos.Photo
 */
class PhotosUploadProfilePhotoRequest extends TLObject
{
    const CONSTRUCTOR = 0x0388a3b5;

    private int    $fileId;
    private int    $fileParts;
    private string $fileName;
    private string $md5;

    public function __construct(int $fileId, int $fileParts, string $fileName, string $md5 = '')
    {
        $this->fileId    = $fileId;
        $this->fileParts = $fileParts;
        $this->fileName  = $fileName;
        $this->md5       = $md5;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt(1); // flags: bit 0 = file present

        // InputFile#f52ff27f id:long parts:int name:string md5_checksum:string
        $writer->writeInt(0xf52ff27f);
        $writer->writeLong($this->fileId);
        $writer->writeInt($this->fileParts);
        $writer->writeString($this->fileName);
        $writer->writeString($this->md5);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'          => 'photos.uploadProfilePhoto',
            'file_id'    => $this->fileId,
            'file_parts' => $this->fileParts,
            'file_name'  => $this->fileName,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
