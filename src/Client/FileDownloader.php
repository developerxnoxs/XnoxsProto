<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\UploadGetFileRequest;
use XnoxsProto\Network\MTProtoSender;
use XnoxsProto\Exceptions\RPCException;

/**
 * Downloads files and media from Telegram servers.
 *
 * Usage:
 *   $downloader = $client->getDownloader();
 *   $downloader->downloadMedia($message, '/path/to/save');
 *   $downloader->downloadDocument($docId, $accessHash, $fileRef, '/path/to/save.pdf');
 *   $downloader->downloadPhoto($photoId, $accessHash, $fileRef, '/path/to/save.jpg');
 */
class FileDownloader
{
    private TelegramClient $client;

    const CHUNK_SIZE = 1048576; // 1 MB per request

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    // =========================================================================
    // High-level: download from message media
    // =========================================================================

    /**
     * Download media from a message returned by getHistory().
     *
     * @param array       $message     Message array from getHistory()
     * @param string      $savePath    Path where the file will be saved
     * @param callable|null $onProgress Optional fn(int $received, int $total, int $pct)
     * @return string     The path where the file was saved
     */
    public function downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
    {
        $media = $message['media'] ?? null;
        if (!$media) {
            throw new \RuntimeException('Message has no media');
        }

        $type = $media['type'] ?? '';

        if ($type === 'photo') {
            return $this->downloadPhoto(
                $media['id'],
                $media['access_hash'],
                $media['file_reference'] ?? '',
                $savePath,
                $onProgress
            );
        }

        if (in_array($type, ['document', 'video', 'audio', 'voice', 'gif'], true)) {
            return $this->downloadDocument(
                $media['id'],
                $media['access_hash'],
                $media['file_reference'] ?? '',
                $savePath,
                $onProgress
            );
        }

        throw new \RuntimeException("Cannot download media type: $type");
    }

    // =========================================================================
    // Download photo
    // =========================================================================

    /**
     * Download a photo by its ID and access hash.
     *
     * @param int    $photoId     Photo ID
     * @param int    $accessHash  Access hash
     * @param string $fileRef     File reference bytes
     * @param string $savePath    Where to save the file
     * @param string $thumbSize   Thumbnail size (default 'y' = largest JPEG)
     */
    public function downloadPhoto(
        int       $photoId,
        int       $accessHash,
        string    $fileRef,
        string    $savePath,
        ?callable $onProgress = null,
        string    $thumbSize  = 'y'
    ): string {
        return $this->downloadChunked(
            locationType:  2,
            id:            $photoId,
            accessHash:    $accessHash,
            fileReference: $fileRef,
            thumbSize:     $thumbSize,
            savePath:      $savePath,
            onProgress:    $onProgress
        );
    }

    // =========================================================================
    // Download document
    // =========================================================================

    /**
     * Download a document (file, video, audio, etc.) by ID and access hash.
     *
     * @param int    $docId      Document ID
     * @param int    $accessHash Access hash
     * @param string $fileRef    File reference bytes
     * @param string $savePath   Where to save the file
     */
    public function downloadDocument(
        int       $docId,
        int       $accessHash,
        string    $fileRef,
        string    $savePath,
        ?callable $onProgress = null
    ): string {
        return $this->downloadChunked(
            locationType:  1,
            id:            $docId,
            accessHash:    $accessHash,
            fileReference: $fileRef,
            thumbSize:     '',
            savePath:      $savePath,
            onProgress:    $onProgress
        );
    }

    // =========================================================================
    // Core download loop
    // =========================================================================

    private function downloadChunked(
        int       $locationType,
        int       $id,
        int       $accessHash,
        string    $fileReference,
        string    $thumbSize,
        string    $savePath,
        ?callable $onProgress
    ): string {
        $sender = $this->client->getSender();
        if (!$sender) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        // Ensure directory exists
        $dir = dirname($savePath);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh     = fopen($savePath, 'wb');
        if (!$fh) {
            throw new \RuntimeException("Cannot open file for writing: $savePath");
        }

        $offset    = 0;
        $totalSize = 0;
        $received  = 0;

        try {
            while (true) {
                $request = new UploadGetFileRequest(
                    $locationType,
                    $id,
                    $accessHash,
                    $fileReference,
                    $thumbSize,
                    $offset,
                    self::CHUNK_SIZE
                );
                $request = $this->client->wrapFirstRequest($request);

                try {
                    $response = $sender->send($request);
                } catch (RPCException $e) {
                    // FILE_REFERENCE_EXPIRED — cannot refresh here without the original message
                    throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
                }

                $c = $response['constructor'];
                $r = $response['reader'];

                // upload.file#096a18d5  type:storage.FileType mtime:int bytes:bytes
                if ($c !== 0x096a18d5) {
                    throw new \RuntimeException(sprintf('Unexpected upload.getFile response: 0x%08x', $c));
                }

                $r->readInt(); // file type ctor (e.g. storage.fileJpeg)
                $mtime     = $r->readInt();
                $chunkData = $r->readBytes();

                if ($chunkData === '') {
                    break; // End of file
                }

                fwrite($fh, $chunkData);
                $chunkLen  = strlen($chunkData);
                $received += $chunkLen;
                $offset   += $chunkLen;

                if ($onProgress !== null) {
                    $pct = $totalSize > 0 ? (int)($received / $totalSize * 100) : 0;
                    ($onProgress)($received, $totalSize, $pct);
                }

                if ($chunkLen < self::CHUNK_SIZE) {
                    break; // Last chunk
                }
            }
        } finally {
            fclose($fh);
        }

        return $savePath;
    }

    // =========================================================================
    // Download to memory (for small files)
    // =========================================================================

    /**
     * Download a document to memory and return as string.
     * Use only for small files.
     */
    public function downloadToMemory(
        int    $docId,
        int    $accessHash,
        string $fileRef
    ): string {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xnoxs_dl_');
        try {
            $this->downloadDocument($docId, $accessHash, $fileRef, $tmpFile);
            return file_get_contents($tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Download a photo to memory and return as string.
     */
    public function downloadPhotoToMemory(
        int    $photoId,
        int    $accessHash,
        string $fileRef,
        string $thumbSize = 'y'
    ): string {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xnoxs_ph_');
        try {
            $this->downloadPhoto($photoId, $accessHash, $fileRef, $tmpFile, null, $thumbSize);
            return file_get_contents($tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
