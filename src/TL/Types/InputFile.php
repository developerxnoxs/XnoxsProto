<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryWriter;

/**
 * InputFile — referensi file yang sudah diupload ke server Telegram.
 *
 * TL schema:
 *   inputFile#f52ff27f       id:long parts:int name:string md5_checksum:string  (< 10 MB)
 *   inputFileBig#fa4f0bb5   id:long parts:int name:string                       (>= 10 MB)
 */
class InputFile
{
    const SMALL = 0xf52ff27f;
    const BIG   = 0xfa4f0bb5;

    private int    $constructor;
    private int    $fileId;
    private int    $parts;
    private string $name;
    private string $md5Checksum;

    private function __construct(
        int    $constructor,
        int    $fileId,
        int    $parts,
        string $name,
        string $md5Checksum = ''
    ) {
        $this->constructor  = $constructor;
        $this->fileId       = $fileId;
        $this->parts        = $parts;
        $this->name         = $name;
        $this->md5Checksum  = $md5Checksum;
    }

    /** File kecil (< 10 MB) */
    public static function small(int $fileId, int $parts, string $name, string $md5Checksum): self
    {
        return new self(self::SMALL, $fileId, $parts, $name, $md5Checksum);
    }

    /** File besar (>= 10 MB) */
    public static function big(int $fileId, int $parts, string $name): self
    {
        return new self(self::BIG, $fileId, $parts, $name, '');
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt($this->constructor);
        $writer->writeLong($this->fileId);
        $writer->writeInt($this->parts);
        $writer->writeString($this->name);

        if ($this->constructor === self::SMALL) {
            $writer->writeString($this->md5Checksum);
        }
    }

    public function getName(): string        { return $this->name; }
    public function getFileId(): int         { return $this->fileId; }
    public function getParts(): int          { return $this->parts; }
    public function getMd5Checksum(): string { return $this->md5Checksum; }
    public function isBig(): bool            { return $this->constructor === self::BIG; }
}
