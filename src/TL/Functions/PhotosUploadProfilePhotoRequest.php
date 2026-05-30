<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputFile;

/**
 * photos.uploadProfilePhoto#388a3b5 flags:# fallback:flags.3?true file:flags.0?InputFile = photos.Photo
 */
class PhotosUploadProfilePhotoRequest extends TLObject
{
    const CONSTRUCTOR = 0x0388a3b5;

    private InputFile $inputFile;

    public function __construct(InputFile $inputFile)
    {
        $this->inputFile = $inputFile;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt(1); // flags: bit 0 = file present

        // Delegasikan serialisasi ke InputFile (mendukung small & big)
        $this->inputFile->serialize($writer);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'          => 'photos.uploadProfilePhoto',
            'file_id'    => $this->inputFile->getFileId(),
            'file_parts' => $this->inputFile->getParts(),
            'file_name'  => $this->inputFile->getName(),
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
