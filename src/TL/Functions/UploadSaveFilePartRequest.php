<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * upload.saveFilePart#b304a621
 *   file_id:long  file_part:int  bytes:bytes  = Bool
 *
 * Digunakan untuk file < 10 MB (small files).
 */
class UploadSaveFilePartRequest extends TLObject
{
    const CONSTRUCTOR = 0xb304a621;

    private int    $fileId;
    private int    $filePart;
    private string $bytes;

    public function __construct(int $fileId, int $filePart, string $bytes)
    {
        $this->fileId   = $fileId;
        $this->filePart = $filePart;
        $this->bytes    = $bytes;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeLong($this->fileId);
        $writer->writeInt($this->filePart);
        $writer->writeBytes($this->bytes);
    }

    public function toDict(): array
    {
        return [
            '_'         => 'upload.saveFilePart',
            'file_id'   => $this->fileId,
            'file_part' => $this->filePart,
            'bytes_len' => strlen($this->bytes),
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
