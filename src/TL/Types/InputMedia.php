<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryWriter;

/**
 * InputMedia — membungkus InputFile menjadi tipe media untuk dikirim.
 *
 * TL schema:
 *
 *   inputMediaUploadedPhoto#1e287d04
 *     flags:#  spoiler:flags.2?true
 *     file:InputFile
 *     stickers:flags.0?Vector<InputDocument>
 *     ttl_seconds:flags.1?int
 *
 *   inputMediaUploadedDocument#5b38c6c1
 *     flags:#  nosound_video:flags.3?true  force_file:flags.4?true  spoiler:flags.5?true
 *     file:InputFile
 *     thumb:flags.2?InputFile
 *     mime_type:string
 *     attributes:Vector<DocumentAttribute>
 *     stickers:flags.0?Vector<InputDocument>
 *     ttl_seconds:flags.1?int
 *
 * DocumentAttribute constructors:
 *   documentAttributeFilename#15590068   file_name:string
 *   documentAttributeVideo#ef02a9d5      flags:# round_message:f.0?true supports_streaming:f.1?true nosound:f.3?true duration:double w:int h:int
 *   documentAttributeAudio#9852f9c6      flags:# voice:f.10?true duration:int title:f.0?string performer:f.1?string waveform:f.2?bytes
 */
class InputMedia
{
    const UPLOADED_PHOTO    = 0x1e287d04;
    const UPLOADED_DOCUMENT = 0x5b38c6c1;

    const ATTR_FILENAME = 0x15590068;
    const ATTR_VIDEO    = 0xef02a9d5;
    const ATTR_AUDIO    = 0x9852f9c6;

    private int       $type;
    private InputFile $file;
    private string    $mimeType    = '';
    private array     $attributes  = [];

    private function __construct(int $type, InputFile $file)
    {
        $this->type = $type;
        $this->file = $file;
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    /** Foto — ditampilkan inline di chat */
    public static function photo(InputFile $file): self
    {
        return new self(self::UPLOADED_PHOTO, $file);
    }

    /**
     * Dokumen generik (PDF, ZIP, dll.)
     * @param string $filename  Nama file yang terlihat di chat
     */
    public static function document(InputFile $file, string $mimeType, string $filename): self
    {
        $m = new self(self::UPLOADED_DOCUMENT, $file);
        $m->mimeType   = $mimeType;
        $m->attributes = [
            ['type' => self::ATTR_FILENAME, 'filename' => $filename],
        ];
        return $m;
    }

    /**
     * Video — ditampilkan sebagai player inline
     * @param float $duration  Durasi dalam detik
     * @param int   $w         Lebar frame
     * @param int   $h         Tinggi frame
     */
    public static function video(
        InputFile $file,
        string    $mimeType,
        string    $filename,
        float     $duration = 0.0,
        int       $w = 0,
        int       $h = 0,
        bool      $supportsStreaming = true
    ): self {
        $m = new self(self::UPLOADED_DOCUMENT, $file);
        $m->mimeType   = $mimeType;
        $m->attributes = [
            ['type' => self::ATTR_VIDEO, 'duration' => $duration, 'w' => $w, 'h' => $h, 'streaming' => $supportsStreaming],
            ['type' => self::ATTR_FILENAME, 'filename' => $filename],
        ];
        return $m;
    }

    /**
     * Voice note — ditampilkan sebagai pesan suara (bubble biru)
     * @param int $duration Durasi dalam detik
     */
    public static function voice(InputFile $file, int $duration = 0): self
    {
        $m             = new self(self::UPLOADED_DOCUMENT, $file);
        $m->mimeType   = 'audio/ogg';
        $m->attributes = [
            ['type' => self::ATTR_AUDIO, 'duration' => $duration, 'voice' => true, 'title' => '', 'performer' => ''],
        ];
        return $m;
    }

    /**
     * Audio — ditampilkan sebagai player audio
     * @param int    $duration   Durasi dalam detik
     * @param string $title      Judul lagu (opsional)
     * @param string $performer  Nama artis (opsional)
     */
    public static function audio(
        InputFile $file,
        string    $mimeType,
        string    $filename,
        int       $duration = 0,
        string    $title = '',
        string    $performer = ''
    ): self {
        $m = new self(self::UPLOADED_DOCUMENT, $file);
        $m->mimeType   = $mimeType;
        $m->attributes = [
            ['type' => self::ATTR_AUDIO, 'duration' => $duration, 'title' => $title, 'performer' => $performer],
            ['type' => self::ATTR_FILENAME, 'filename' => $filename],
        ];
        return $m;
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function serialize(BinaryWriter $writer): void
    {
        if ($this->type === self::UPLOADED_PHOTO) {
            $this->serializePhoto($writer);
        } else {
            $this->serializeDocument($writer);
        }
    }

    private function serializePhoto(BinaryWriter $writer): void
    {
        $writer->writeInt(self::UPLOADED_PHOTO);
        $writer->writeInt(0); // flags = 0 (no spoiler, no stickers, no ttl)
        $this->file->serialize($writer);
        // stickers: not set (flags.0 = 0)
        // ttl_seconds: not set (flags.1 = 0)
    }

    private function serializeDocument(BinaryWriter $writer): void
    {
        $writer->writeInt(self::UPLOADED_DOCUMENT);

        // flags: supports_streaming via video attr, no forced flags here
        $flags = 0;
        $writer->writeInt($flags);

        $this->file->serialize($writer);
        // thumb: not set (flags.2 = 0)

        $writer->writeString($this->mimeType);

        // attributes: Vector<DocumentAttribute>
        $writer->writeInt(0x1cb5c415); // vector constructor
        $writer->writeInt(count($this->attributes));
        foreach ($this->attributes as $attr) {
            $this->serializeAttribute($writer, $attr);
        }
        // stickers: not set (flags.0 = 0)
        // ttl_seconds: not set (flags.1 = 0)
    }

    private function serializeAttribute(BinaryWriter $writer, array $attr): void
    {
        switch ($attr['type']) {
            case self::ATTR_FILENAME:
                $writer->writeInt(self::ATTR_FILENAME);
                $writer->writeString($attr['filename']);
                break;

            case self::ATTR_VIDEO:
                $writer->writeInt(self::ATTR_VIDEO);
                $flags = 0;
                if ($attr['streaming'] ?? false) $flags |= (1 << 1);
                $writer->writeInt($flags);
                $writer->writeDouble((float)($attr['duration'] ?? 0.0));
                $writer->writeInt((int)($attr['w'] ?? 0));
                $writer->writeInt((int)($attr['h'] ?? 0));
                break;

            case self::ATTR_AUDIO:
                $writer->writeInt(self::ATTR_AUDIO);
                $title     = $attr['title']     ?? '';
                $performer = $attr['performer'] ?? '';
                $flags = 0;
                if ($title !== '')     $flags |= (1 << 0);
                if ($performer !== '') $flags |= (1 << 1);
                $writer->writeInt($flags);
                $writer->writeInt((int)($attr['duration'] ?? 0));
                if ($title !== '')     $writer->writeString($title);
                if ($performer !== '') $writer->writeString($performer);
                break;
        }
    }
}
