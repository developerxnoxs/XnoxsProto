<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * upload.getFile#be5335be flags:# precise:flags.0?true cdn_supported:flags.1?true
 *   location:InputFileLocation offset:long limit:int = upload.File
 *
 * NOTE: Constructor changed from 0xb15a9afc to 0xbe5335be when offset type
 * changed from int32 to int64 (long). TDLib/Pyrogram both use 0xbe5335be.
 */
class UploadGetFileRequest extends TLObject
{
    const CONSTRUCTOR = 0xbe5335be;

    private int    $locationType;
    private int    $id;
    private int    $accessHash;
    private string $fileReference;
    private string $thumbSize;
    private int    $offset;
    private int    $limit;

    /**
     * @param int    $locationType 1=document, 2=photo
     * @param int    $id           Document/Photo ID
     * @param int    $accessHash   Access hash
     * @param string $fileReference File reference bytes
     * @param string $thumbSize    Thumb size ('' for documents)
     * @param int    $offset       Byte offset
     * @param int    $limit        Max bytes (max 1048576 = 1MB)
     */
    public function __construct(
        int    $locationType,
        int    $id,
        int    $accessHash,
        string $fileReference,
        string $thumbSize,
        int    $offset,
        int    $limit = 1048576
    ) {
        $this->locationType  = $locationType;
        $this->id            = $id;
        $this->accessHash    = $accessHash;
        $this->fileReference = $fileReference;
        $this->thumbSize     = $thumbSize;
        $this->offset        = $offset;
        $this->limit         = min($limit, 1048576);
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt(0); // flags (no precise, no cdn_supported)

        if ($this->locationType === 2) {
            // inputPhotoFileLocation#40181ffe
            $writer->writeInt(0x40181ffe);
        } else {
            // inputDocumentFileLocation#bad07584
            $writer->writeInt(0xbad07584);
        }
        $writer->writeLong($this->id);
        $writer->writeLong($this->accessHash);
        $writer->writeBytes($this->fileReference);
        $writer->writeString($this->thumbSize);

        $writer->writeLong($this->offset);
        $writer->writeInt($this->limit);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'              => 'upload.getFile',
            'location_type'  => $this->locationType,
            'id'             => $this->id,
            'access_hash'    => $this->accessHash,
            'file_reference' => $this->fileReference,
            'thumb_size'     => $this->thumbSize,
            'offset'         => $this->offset,
            'limit'          => $this->limit,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
