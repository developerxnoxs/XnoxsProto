<?php

namespace XnoxsProto\Upload;

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\TL\Types\InputFile;
use XnoxsProto\TL\Types\InputMedia;
use XnoxsProto\TL\Functions\UploadSaveFilePartRequest;
use XnoxsProto\TL\Functions\UploadSaveBigFilePartRequest;

/**
 * FileUploader — menangani upload file ke server Telegram secara chunked.
 *
 * Alur:
 *   1. Baca file lokal
 *   2. Bagi menjadi chunk 512 KB
 *   3. Kirim setiap chunk via upload.saveFilePart (< 10 MB)
 *      atau upload.saveBigFilePart (>= 10 MB)
 *   4. Return InputFile yang siap dipakai di InputMedia
 */
class FileUploader
{
    /** Ukuran setiap chunk: 512 KB */
    const CHUNK_SIZE = 512 * 1024;

    /** Batas file kecil vs file besar: 10 MB */
    const BIG_FILE_THRESHOLD = 10 * 1024 * 1024;

    private TelegramClient $client;

    /** Callback opsional untuk progress: function(int $part, int $total, int $percent) */
    private $progressCallback = null;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    /**
     * Set callback untuk laporan progress upload.
     *
     * @param callable $cb  function(int $part, int $total, int $percent): void
     */
    public function onProgress(callable $cb): self
    {
        $this->progressCallback = $cb;
        return $this;
    }

    // =========================================================================
    // Public entry point
    // =========================================================================

    /**
     * Upload file dari path lokal ke Telegram.
     *
     * @param  string $path      Path ke file (lokal)
     * @param  string $filename  Nama file yang akan dikirim (default: basename path)
     * @return InputFile         Siap dipakai di InputMedia::photo/document/video/audio
     *
     * @throws \InvalidArgumentException  Jika file tidak ditemukan
     * @throws \RuntimeException          Jika upload gagal
     */
    public function upload(string $path, string $filename = ''): InputFile
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File tidak ditemukan: $path");
        }

        $filename   = $filename !== '' ? $filename : basename($path);
        $fileSize   = filesize($path);
        $totalParts = (int)ceil($fileSize / self::CHUNK_SIZE);
        $isBig      = $fileSize >= self::BIG_FILE_THRESHOLD;

        // File ID unik per upload (signed 64-bit random)
        $fileId = unpack('q', random_bytes(8))[1];

        $sender = $this->client->getSender();
        if ($sender === null) {
            throw new \RuntimeException('MTProto sender belum siap — panggil connect() terlebih dahulu');
        }

        $md5Ctx = $isBig ? null : hash_init('md5');

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            throw new \RuntimeException("Gagal membuka file: $path");
        }

        try {
            for ($part = 0; $part < $totalParts; $part++) {
                $chunk = fread($fp, self::CHUNK_SIZE);
                if ($chunk === false || $chunk === '') {
                    throw new \RuntimeException("Gagal membaca file di bagian $part");
                }

                if ($md5Ctx !== null) {
                    hash_update($md5Ctx, $chunk);
                }

                if ($isBig) {
                    $request = new UploadSaveBigFilePartRequest($fileId, $part, $totalParts, $chunk);
                } else {
                    $request = new UploadSaveFilePartRequest($fileId, $part, $chunk);
                }

                // wrapFirstRequest hanya dieksekusi sekali per sesi
                $request = $this->client->wrapFirstRequest($request);

                try {
                    $this->client->getSender()->send($request);
                } catch (\XnoxsProto\Exceptions\RPCException $e) {
                    throw new \RuntimeException(
                        sprintf('[%d] Upload gagal di bagian %d: %s', $e->errorCode, $part, $e->errorMessage),
                        $e->errorCode,
                        $e
                    );
                }

                if ($this->progressCallback !== null) {
                    $percent = (int)round(($part + 1) / $totalParts * 100);
                    ($this->progressCallback)($part + 1, $totalParts, $percent);
                }
            }
        } finally {
            fclose($fp);
        }

        if ($isBig) {
            return InputFile::big($fileId, $totalParts, $filename);
        }

        $md5 = hash_final($md5Ctx);
        return InputFile::small($fileId, $totalParts, $filename, $md5);
    }

    // =========================================================================
    // Auto-detect media type & build InputMedia
    // =========================================================================

    /**
     * Upload file dan otomatis deteksi tipe media berdasarkan MIME type.
     *
     * Aturan deteksi:
     *   image/jpeg, image/png, image/webp  → photo
     *   video/*                            → video (document dengan atribut video)
     *   audio/*                            → audio (document dengan atribut audio)
     *   image/gif                          → document (GIF animasi)
     *   lainnya                            → document
     *
     * @param  string   $path      Path file lokal
     * @param  string   $caption   Caption pesan (opsional)
     * @param  bool     $forceDoc  Paksa kirim sebagai dokumen (bypass auto-detect)
     * @return array    ['input_file' => InputFile, 'input_media' => InputMedia, 'mime' => string, 'category' => string]
     */
    public function uploadAuto(string $path, bool $forceDoc = false): array
    {
        $filename = basename($path);
        $mime     = $this->detectMime($path);
        $category = $forceDoc ? 'document' : $this->categorize($mime, $filename);

        $inputFile = $this->upload($path, $filename);

        $inputMedia = match ($category) {
            'photo'    => InputMedia::photo($inputFile),
            'video'    => $this->buildVideoMedia($inputFile, $mime, $filename, $path),
            'audio'    => $this->buildAudioMedia($inputFile, $mime, $filename, $path),
            default    => InputMedia::document($inputFile, $mime, $filename),
        };

        return [
            'input_file'  => $inputFile,
            'input_media' => $inputMedia,
            'mime'        => $mime,
            'category'    => $category,
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function categorize(string $mime, string $filename): string
    {
        // Foto — hanya JPEG, PNG, WebP (bukan GIF animasi)
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'photo';
        }

        // Video
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        // Audio
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        // GIF dan lainnya → dokumen
        return 'document';
    }

    private function buildVideoMedia(InputFile $file, string $mime, string $filename, string $path): InputMedia
    {
        // Coba ambil dimensi dan durasi via getimagesize (hanya bekerja untuk beberapa format)
        $w = $h = 0;
        $duration = 0.0;

        // Tidak semua environment punya ffprobe, jadi kita biarkan 0 sebagai fallback
        if (function_exists('shell_exec')) {
            $ff = @shell_exec(sprintf(
                'ffprobe -v quiet -print_format json -show_streams %s 2>/dev/null',
                escapeshellarg($path)
            ));
            if ($ff) {
                $info = json_decode($ff, true);
                foreach ($info['streams'] ?? [] as $stream) {
                    if (($stream['codec_type'] ?? '') === 'video') {
                        $w        = (int)($stream['width']  ?? 0);
                        $h        = (int)($stream['height'] ?? 0);
                        $duration = (float)($stream['duration'] ?? 0.0);
                        break;
                    }
                }
            }
        }

        return InputMedia::video($file, $mime, $filename, $duration, $w, $h, true);
    }

    private function buildAudioMedia(InputFile $file, string $mime, string $filename, string $path): InputMedia
    {
        $duration  = 0;
        $title     = '';
        $performer = '';

        // Coba baca ID3 tags via shell (opsional)
        if (function_exists('shell_exec')) {
            $ff = @shell_exec(sprintf(
                'ffprobe -v quiet -print_format json -show_format %s 2>/dev/null',
                escapeshellarg($path)
            ));
            if ($ff) {
                $info = json_decode($ff, true);
                $tags = $info['format']['tags'] ?? [];
                $title     = $tags['title']  ?? $tags['TITLE']  ?? '';
                $performer = $tags['artist'] ?? $tags['ARTIST'] ?? '';
                $duration  = (int)round((float)($info['format']['duration'] ?? 0));
            }
        }

        return InputMedia::audio($file, $mime, $filename, $duration, $title, $performer);
    }

    /**
     * Deteksi MIME type file menggunakan finfo (lebih akurat) atau fallback ke ekstensi.
     */
    public static function detectMime(string $path): string
    {
        // Coba finfo (paling akurat)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // Coba mime_content_type
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // Fallback: berdasarkan ekstensi
        return self::mimeFromExtension(strtolower(pathinfo($path, PATHINFO_EXTENSION)));
    }

    private static function mimeFromExtension(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            'tiff', 'tif' => 'image/tiff',
            'svg'         => 'image/svg+xml',
            'mp4'         => 'video/mp4',
            'mov'         => 'video/quicktime',
            'avi'         => 'video/x-msvideo',
            'mkv'         => 'video/x-matroska',
            'webm'        => 'video/webm',
            'flv'         => 'video/x-flv',
            'mp3'         => 'audio/mpeg',
            'ogg', 'oga'  => 'audio/ogg',
            'flac'        => 'audio/flac',
            'wav'         => 'audio/wav',
            'm4a'         => 'audio/mp4',
            'aac'         => 'audio/aac',
            'opus'        => 'audio/opus',
            'pdf'         => 'application/pdf',
            'zip'         => 'application/zip',
            'rar'         => 'application/x-rar-compressed',
            '7z'          => 'application/x-7z-compressed',
            'tar'         => 'application/x-tar',
            'gz'          => 'application/gzip',
            'doc'         => 'application/msword',
            'docx'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'         => 'application/vnd.ms-excel',
            'xlsx'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'         => 'application/vnd.ms-powerpoint',
            'pptx'        => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt'         => 'text/plain',
            'csv'         => 'text/csv',
            'html', 'htm' => 'text/html',
            'xml'         => 'application/xml',
            'json'        => 'application/json',
            'apk'         => 'application/vnd.android.package-archive',
            default       => 'application/octet-stream',
        };
    }
}
