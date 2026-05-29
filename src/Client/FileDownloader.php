<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\UploadGetFileRequest;
use XnoxsProto\TL\Functions\InvokeWithLayerRequest;
use XnoxsProto\TL\Functions\InitConnectionRequest;
use XnoxsProto\TL\Functions\AuthImportAuthorizationRequest;
use XnoxsProto\TL\Types\InputPeer;
use XnoxsProto\Network\Connection;
use XnoxsProto\Network\Authenticator;
use XnoxsProto\Network\MTProtoSender;
use XnoxsProto\Crypto\AuthKey;
use XnoxsProto\Exceptions\RPCException;

/**
 * Downloads files and media from Telegram servers.
 *
 * Mendukung DC migration otomatis: file yang disimpan di DC berbeda dari
 * session aktif akan diunduh via koneksi sementara ke DC yang benar.
 *
 * Mendukung auto-refresh file_reference yang kadaluarsa (FILE_REFERENCE_EXPIRED).
 *
 * Contoh penggunaan:
 *   // Cara paling mudah — langsung dari message
 *   $path = $client->downloadMedia($message, '/tmp/file.jpg');
 *
 *   // Lewat downloader secara eksplisit
 *   $dl = $client->getDownloader();
 *   $dl->downloadMedia($message, '/tmp/file.jpg', function($recv, $total, $pct) {
 *       echo "\r$pct% ($recv/$total bytes)";
 *   });
 */
class FileDownloader
{
    private TelegramClient $client;

    const CHUNK_SIZE = 524288; // 512 KB per request (lebih stabil dari 1 MB)

    private const DC_OPTIONS = [
        1 => ['ip' => '149.154.175.53',  'port' => 443],
        2 => ['ip' => '149.154.167.51',  'port' => 443],
        3 => ['ip' => '149.154.175.100', 'port' => 443],
        4 => ['ip' => '149.154.167.91',  'port' => 443],
        5 => ['ip' => '91.108.56.130',   'port' => 443],
    ];

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    // =========================================================================
    // High-level: download dari message
    // =========================================================================

    /**
     * Download media dari message yang dikembalikan oleh getHistory().
     *
     * Mendukung: foto, video, audio, voice, GIF, dokumen, stiker.
     * DC migration dan file_reference refresh ditangani otomatis.
     *
     * @param array        $message     Array message dari getHistory() atau event handler
     * @param string       $savePath    Path tujuan penyimpanan file
     * @param callable|null $onProgress Opsional: fn(int $received, int $total, int $pct)
     * @return string      Path file yang disimpan
     */
    public function downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
    {
        $media = $message['media'] ?? null;
        if (!$media) {
            throw new \RuntimeException('Pesan tidak memiliki media');
        }

        $type = $media['type'] ?? '';

        // Validasi: harus ada id dan access_hash untuk download
        if (empty($media['id']) || !isset($media['access_hash']) || !isset($media['file_reference'])) {
            throw new \RuntimeException(
                "Data media tidak lengkap untuk download (type=$type). " .
                "Pastikan pesan diambil via getHistory() bukan getHistory() versi lama."
            );
        }

        // Ambil peer context untuk keperluan refresh file_reference
        /** @var \XnoxsProto\TL\Types\FullMessage|null $msgObj */
        $msgObj = $message['_message_obj'] ?? null;
        $msgId  = (int)($message['id'] ?? 0);

        if ($type === 'photo') {
            return $this->downloadPhoto(
                $media['id'],
                $media['access_hash'],
                $media['file_reference'],
                $savePath,
                $onProgress,
                $media['thumb_size'] ?? 'y',
                $media['dc_id'] ?? null
            );
        }

        if (in_array($type, ['document', 'video', 'audio', 'voice', 'gif', 'sticker'], true)) {
            return $this->downloadDocument(
                $media['id'],
                $media['access_hash'],
                $media['file_reference'],
                $savePath,
                $onProgress,
                $media['dc_id'] ?? null,
                $media['size'] ?? 0,
                $msgId,
                $msgObj
            );
        }

        throw new \RuntimeException("Tipe media tidak bisa diunduh: $type");
    }

    // =========================================================================
    // Download foto
    // =========================================================================

    /**
     * Download foto berdasarkan ID dan access hash.
     *
     * @param int         $photoId     Photo ID
     * @param int         $accessHash  Access hash
     * @param string      $fileRef     File reference bytes
     * @param string      $savePath    Path tujuan simpan
     * @param callable|null $onProgress Progress callback fn(int $received, int $total, int $pct)
     * @param string      $thumbSize   Ukuran thumb ('y' = terbesar, 'x', 'm', 's')
     * @param int|null    $dcId        DC tempat file disimpan (null = auto dari session)
     */
    public function downloadPhoto(
        int       $photoId,
        int       $accessHash,
        string    $fileRef,
        string    $savePath,
        ?callable $onProgress = null,
        string    $thumbSize  = 'y',
        ?int      $dcId       = null
    ): string {
        return $this->downloadChunked(
            locationType:  2,
            id:            $photoId,
            accessHash:    $accessHash,
            fileReference: $fileRef,
            thumbSize:     $thumbSize,
            savePath:      $savePath,
            onProgress:    $onProgress,
            dcId:          $dcId,
            totalSize:     0
        );
    }

    // =========================================================================
    // Download dokumen
    // =========================================================================

    /**
     * Download dokumen (file, video, audio, dll.) berdasarkan ID dan access hash.
     *
     * @param int         $docId      Document ID
     * @param int         $accessHash Access hash
     * @param string      $fileRef    File reference bytes
     * @param string      $savePath   Path tujuan simpan
     * @param callable|null $onProgress Progress callback fn(int $received, int $total, int $pct)
     * @param int|null    $dcId       DC tempat file disimpan (null = auto dari session)
     * @param int         $totalSize  Ukuran file total dalam bytes (untuk progress %)
     */
    public function downloadDocument(
        int       $docId,
        int       $accessHash,
        string    $fileRef,
        string    $savePath,
        ?callable $onProgress = null,
        ?int      $dcId       = null,
        int       $totalSize  = 0,
        int       $msgId      = 0,
        mixed     $msgObj     = null
    ): string {
        return $this->downloadChunked(
            locationType:  1,
            id:            $docId,
            accessHash:    $accessHash,
            fileReference: $fileRef,
            thumbSize:     '',
            savePath:      $savePath,
            onProgress:    $onProgress,
            dcId:          $dcId,
            totalSize:     $totalSize,
            msgId:         $msgId,
            msgObj:        $msgObj
        );
    }

    // =========================================================================
    // Core download loop dengan DC migration + file_reference refresh
    // =========================================================================

    private function downloadChunked(
        int       $locationType,
        int       $id,
        int       $accessHash,
        string    $fileReference,
        string    $thumbSize,
        string    $savePath,
        ?callable $onProgress,
        ?int      $dcId,
        int       $totalSize = 0,
        int       $msgId     = 0,
        mixed     $msgObj    = null
    ): string {
        $mainSender = $this->client->getSender();
        if (!$mainSender) {
            throw new \RuntimeException('Belum terhubung ke Telegram');
        }

        // Tentukan apakah perlu pindah DC
        $currentDcId = $this->client->getSession()->getDC()['dc_id'] ?? 2;
        $needDcSwitch = ($dcId !== null && $dcId !== $currentDcId && isset(self::DC_OPTIONS[$dcId]));

        $sender     = $mainSender;
        $tmpConn    = null;
        $tmpSender  = null;
        $isFirstReq = true;

        if ($needDcSwitch) {
            [$tmpConn, $tmpSender] = $this->openFileDC($dcId, $mainSender);
            $sender     = $tmpSender;
            $isFirstReq = false; // InitConnection sudah dikirim saat import auth
        }

        // Pastikan direktori tujuan ada
        $dir = dirname($savePath);
        if ($dir && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($savePath, 'wb');
        if (!$fh) {
            if ($tmpConn) $tmpConn->close();
            throw new \RuntimeException("Tidak bisa membuka file untuk ditulis: $savePath");
        }

        $offset         = 0;
        $received       = 0;
        $fileRefRefreshed = false; // Hanya refresh sekali untuk hindari infinite loop

        try {
            while (true) {
                $req = new UploadGetFileRequest(
                    $locationType,
                    $id,
                    $accessHash,
                    $fileReference,
                    $thumbSize,
                    $offset,
                    self::CHUNK_SIZE
                );

                // Wrap dengan InitConnection jika ini request pertama di sender utama
                if ($isFirstReq) {
                    $req = $this->client->wrapFirstRequest($req);
                    $isFirstReq = false;
                }

                try {
                    $response = $sender->send($req);
                } catch (RPCException $e) {
                    if (preg_match('/FILE_MIGRATE_(\d+)/', $e->errorMessage, $m)) {
                        // File pindah ke DC lain saat download berlangsung
                        if ($tmpConn) $tmpConn->close();
                        $newDcId = (int)$m[1];
                        [$tmpConn, $tmpSender] = $this->openFileDC($newDcId, $mainSender);
                        $sender  = $tmpSender;
                        continue; // retry chunk ini dari sender baru
                    }

                    if ($e->errorMessage === 'FILE_REFERENCE_EXPIRED' && !$fileRefRefreshed) {
                        // File reference kadaluarsa — coba refresh dari message asli
                        // (hanya untuk dokumen, bukan foto/thumb, sesuai perilaku Telethon)
                        if ($locationType === 1 && $thumbSize === '' && $msgId > 0 && $msgObj !== null) {
                            $freshRef = $this->refreshFileReference($msgObj, $msgId, $id);
                            if ($freshRef !== null) {
                                $fileReference    = $freshRef;
                                $fileRefRefreshed = true;
                                // Reset offset ke posisi saat ini (jangan ulang dari 0 — resume)
                                continue;
                            }
                        }
                        throw new \RuntimeException(
                            "FILE_REFERENCE_EXPIRED: file reference kadaluarsa. " .
                            "Re-fetch pesan via getHistory() dan coba download ulang."
                        );
                    }

                    throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
                }

                $c = $response['constructor'];
                $r = $response['reader'];

                // upload.file#096a18d5  type:storage.FileType mtime:int bytes:bytes
                if ($c !== 0x096a18d5) {
                    throw new \RuntimeException(sprintf('Response upload.getFile tidak dikenal: 0x%08x', $c));
                }

                $r->readInt(); // file type ctor (e.g. storage.fileJpeg)
                $r->readInt(); // mtime
                $chunkData = $r->readBytes();

                if ($chunkData === '') {
                    break; // akhir file
                }

                fwrite($fh, $chunkData);
                $chunkLen  = strlen($chunkData);
                $received += $chunkLen;
                $offset   += $chunkLen;

                if ($onProgress !== null) {
                    $pct = ($totalSize > 0)
                        ? (int)min(100, round($received / $totalSize * 100))
                        : 0;
                    ($onProgress)($received, $totalSize, $pct);
                }

                if ($chunkLen < self::CHUNK_SIZE) {
                    break; // chunk terakhir
                }
            }
        } finally {
            fclose($fh);
            if ($tmpConn) {
                try { $tmpConn->close(); } catch (\Throwable $e) {}
            }
        }

        return $savePath;
    }

    // =========================================================================
    // Refresh file_reference yang kadaluarsa (FILE_REFERENCE_EXPIRED)
    // =========================================================================

    /**
     * Re-fetch pesan dari Telegram untuk mendapatkan file_reference baru.
     *
     * Mengikuti pendekatan Telethon: ambil ulang pesan asli via getHistory(),
     * pastikan document ID cocok, lalu ambil file_reference baru.
     *
     * @param \XnoxsProto\TL\Types\FullMessage $msgObj  Object pesan asli
     * @param int                               $msgId   Message ID
     * @param int                               $docId   Document ID yang diunduh
     * @return string|null  File reference baru, atau null jika gagal
     */
    private function refreshFileReference(mixed $msgObj, int $msgId, int $docId): ?string
    {
        try {
            $inputPeer = $msgObj->peerInputPeer;
            if ($inputPeer === null) return null;

            // Ambil ulang pesan spesifik dengan window sempit di sekitar msgId
            $messages = $this->client->getHistory(
                $inputPeer,
                1,                  // limit
                $msgId + 1,         // offsetId (ambil pesan sebelum msgId+1)
                0, 0,
                $msgId + 1,         // maxId
                $msgId - 1          // minId
            );

            foreach ($messages as $msg) {
                if (($msg['id'] ?? 0) !== $msgId) continue;
                $media = $msg['media'] ?? null;
                if (!$media) continue;
                // Pastikan document ID cocok sebelum pakai file_reference baru
                if (($media['id'] ?? 0) === $docId && isset($media['file_reference'])) {
                    return $media['file_reference'];
                }
            }
        } catch (\Throwable $e) {
            // Gagal refresh — biarkan caller melempar error FILE_REFERENCE_EXPIRED
        }
        return null;
    }

    // =========================================================================
    // DC migration: buka koneksi sementara ke DC file dan import auth
    // =========================================================================

    /**
     * Buat koneksi sementara ke DC tujuan, lakukan auth export/import.
     *
     * @return array [Connection, MTProtoSender]
     */
    private function openFileDC(int $targetDcId, MTProtoSender $mainSender): array
    {
        if (!isset(self::DC_OPTIONS[$targetDcId])) {
            throw new \RuntimeException("DC ID tidak valid: $targetDcId");
        }
        $dc = self::DC_OPTIONS[$targetDcId];

        // 1. Export auth dari DC saat ini
        $exported = $this->client->exportAuthorization($targetDcId);

        // 2. Buka koneksi baru ke DC tujuan
        $conn = new Connection($dc['ip'], $dc['port']);
        $conn->connect();

        // 3. Lakukan DH key exchange di DC baru
        $auth    = new Authenticator($conn);
        $authKey = $auth->doAuthentication();
        $keyObj  = new AuthKey($authKey->getKey());
        $sender  = new MTProtoSender($conn, $keyObj, $auth->getTimeOffset());

        // 4. Import auth di DC baru (wrapped dalam invokeWithLayer + initConnection)
        $importReq = new AuthImportAuthorizationRequest(
            $exported['id'],
            $exported['bytes']
        );

        $apiId = $this->client->getApiId();
        $layer = $this->client->getLayer();

        $wrapped = new InvokeWithLayerRequest(
            $layer,
            new InitConnectionRequest(
                $apiId,
                'XnoxsProto',
                php_uname('s') . ' ' . php_uname('r'),
                '1.0.0',
                'en', '', 'en',
                $importReq
            )
        );

        try {
            $sender->send($wrapped);
        } catch (RPCException $e) {
            $conn->close();
            throw new \RuntimeException(
                "Gagal import auth ke DC $targetDcId: [{$e->errorCode}] {$e->errorMessage}",
                $e->errorCode,
                $e
            );
        }

        return [$conn, $sender];
    }

    // =========================================================================
    // Download ke memori (untuk file kecil)
    // =========================================================================

    /**
     * Download dokumen ke memori dan kembalikan sebagai string.
     * Hanya untuk file kecil (< beberapa MB).
     */
    public function downloadToMemory(
        int    $docId,
        int    $accessHash,
        string $fileRef,
        ?int   $dcId      = null,
        int    $totalSize = 0
    ): string {
        $tmp = tempnam(sys_get_temp_dir(), 'xnoxs_dl_');
        try {
            $this->downloadDocument($docId, $accessHash, $fileRef, $tmp, null, $dcId, $totalSize);
            return file_get_contents($tmp);
        } finally {
            if (file_exists($tmp)) unlink($tmp);
        }
    }

    /**
     * Download foto ke memori dan kembalikan sebagai string.
     */
    public function downloadPhotoToMemory(
        int    $photoId,
        int    $accessHash,
        string $fileRef,
        string $thumbSize = 'y',
        ?int   $dcId      = null
    ): string {
        $tmp = tempnam(sys_get_temp_dir(), 'xnoxs_ph_');
        try {
            $this->downloadPhoto($photoId, $accessHash, $fileRef, $tmp, null, $thumbSize, $dcId);
            return file_get_contents($tmp);
        } finally {
            if (file_exists($tmp)) unlink($tmp);
        }
    }
}
