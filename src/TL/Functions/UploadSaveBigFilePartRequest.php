<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * upload.saveBigFilePart#de7b673d
 *   file_id:long  file_part:int  file_total_parts:int  bytes:bytes  = Bool
 *
 * Digunakan untuk file >= 10 MB (big files).
 */
class UploadSaveBigFilePartRequest extends TLObject
{
    const CONSTRUCTOR = 0xde7b673d;

    private int    $fileId;
    private int    $filePart;
    private int    $fileTotalParts;
    private string $bytes;

    public function __construct(int $fileId, int $filePart, int $fileTotalParts, string $bytes)
    {
        $this->fileId          = $fileId;
        $this->filePart        = $filePart;
        $this->fileTotalParts  = $fileTotalParts;
        $this->bytes           = $bytes;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->fileId);
        $writer->writeInt($this->filePart);
        $writer->writeInt($this->fileTotalParts);
        $writer->writeBytes($this->bytes);
    }

    public function toDict(): array
    {
        return [
            '_'                => 'upload.saveBigFilePart',
            'file_id'          => $this->fileId,
            'file_part'        => $this->filePart,
            'file_total_parts' => $this->fileTotalParts,
            'bytes_len'        => strlen($this->bytes),
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
