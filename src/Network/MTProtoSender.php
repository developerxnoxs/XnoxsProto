<?php

namespace XnoxsProto\Network;

use XnoxsProto\Crypto\AES;
use XnoxsProto\Crypto\AuthKey;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\Helpers\Helpers;
use XnoxsProto\Exceptions\RPCException;
use XnoxsProto\TL\Parser\UpdateParser;

class MTProtoSender
{
    private Connection $connection;
    private AuthKey $authKey;
    private int $sessionId;
    private int $seqNo = 0;
    private int $msgId = 0;
    private int $salt = 0;
    private int $timeOffset = 0;

    /** Update yang ditangkap selama send() — diambil oleh runUntilDisconnected */
    private array $pendingUpdates = [];

    public function __construct(Connection $connection, AuthKey $authKey, int $timeOffset = 0)
    {
        $this->connection = $connection;
        $this->authKey = $authKey;
        $this->sessionId = unpack('P', random_bytes(8))[1];
        $this->timeOffset = $timeOffset;
        $this->salt = unpack('P', random_bytes(8))[1];
    }

    /**
     * Ambil semua update yang tertahan selama API call, lalu kosongkan antrian.
     */
    public function drainPendingUpdates(): array
    {
        $updates = $this->pendingUpdates;
        $this->pendingUpdates = [];
        return $updates;
    }

    /**
     * Kirim ping#7abe77ec tanpa menunggu pong (fire-and-forget).
     * Wajib dikirim tiap ~20 detik agar Telegram terus push update.
     */
    public function ping(): void
    {
        $pingId = unpack('P', random_bytes(8))[1];
        // ping#7abe77ec id:long
        $body = pack('V', 0x7abe77ec) . pack('P', $pingId);
        $this->sendRawBody($body, false);
    }

    /**
     * Kirim body TL terenkripsi tanpa menunggu respons.
     */
    private function sendRawBody(string $body, bool $contentRelated = true): void
    {
        $msgId = $this->getNewMsgId();
        $seqNo = $this->getSeqNo($contentRelated);

        $writer = new BinaryWriter();
        $writer->writeLong($this->salt);
        $writer->writeLong($this->sessionId);
        $writer->writeLong($msgId);
        $writer->writeInt($seqNo);
        $writer->writeInt(strlen($body));
        $writer->write($body);

        $plaintext = $writer->getValue();
        $paddingLength = (16 - (strlen($plaintext) % 16)) % 16;
        if ($paddingLength < 12) $paddingLength += 16;
        $plaintext .= random_bytes($paddingLength);

        $msgKeyLarge = hash('sha256', substr($this->authKey->getKey(), 88, 32) . $plaintext, true);
        $msgKey      = substr($msgKeyLarge, 8, 16);
        $encrypted   = $this->aesCalculate($plaintext, $msgKey, true);

        $packet = new BinaryWriter();
        $packet->write($this->authKey->getKeyId());
        $packet->write($msgKey);
        $packet->write($encrypted);

        $this->connection->send($packet->getValue());
    }

    public function send($request): array
    {
        $maxRetries = 10;
        $attempt    = 0;

        while ($attempt < $maxRetries) {
            try {
                $msgId = $this->getNewMsgId();
                $seqNo = $this->getSeqNo(true);

                $bodyWriter = new BinaryWriter();
                $request->serialize($bodyWriter);
                $body = $bodyWriter->getValue();

                $writer = new BinaryWriter();
                $writer->writeLong($this->salt);
                $writer->writeLong($this->sessionId);
                $writer->writeLong($msgId);
                $writer->writeInt($seqNo);
                $writer->writeInt(strlen($body));
                $writer->write($body);

                $plaintext = $writer->getValue();

                $paddingLength = (16 - (strlen($plaintext) % 16)) % 16;
                if ($paddingLength < 12) {
                    $paddingLength += 16;
                }
                $plaintext .= random_bytes($paddingLength);

                $msgKeyLarge = hash('sha256', substr($this->authKey->getKey(), 88, 32) . $plaintext, true);
                $msgKey      = substr($msgKeyLarge, 8, 16);

                $encrypted = $this->aesCalculate($plaintext, $msgKey, true);

                $packet = new BinaryWriter();
                $packet->write($this->authKey->getKeyId());
                $packet->write($msgKey);
                $packet->write($encrypted);

                $this->connection->send($packet->getValue());
                $response = $this->connection->recv();
                $result   = $this->processResponse($response, $msgId);

                // bad_server_salt — update salt and retry
                if ($result['constructor'] === 0xedab447b) {
                    $result['reader']->readLong(); // bad_msg_id
                    $result['reader']->readInt();  // bad_msg_seqno
                    $result['reader']->readInt();  // error_code
                    $this->salt = $result['reader']->readLong();
                    $attempt++;
                    continue;
                }

                return $result;

            } catch (RPCException $e) {
                // FLOOD_WAIT_X — sleep for X seconds then retry (auto-retry)
                if (str_starts_with($e->errorMessage, 'FLOOD_WAIT_')) {
                    $seconds = (int) substr($e->errorMessage, 11);
                    // Jika flood wait > 30 detik, lempar langsung supaya caller bisa skip/handle
                    if ($seconds > 30) {
                        throw $e;
                    }
                    $wait = $seconds + 1;
                    error_log("[XnoxsProto] FLOOD_WAIT_{$seconds}s — sleeping {$wait}s (attempt {$attempt}/{$maxRetries})");
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                // SLOWMODE_WAIT_X — slow mode in groups
                if (str_starts_with($e->errorMessage, 'SLOWMODE_WAIT_')) {
                    $seconds = (int) substr($e->errorMessage, 14);
                    $wait    = min($seconds + 1, 120);
                    error_log("[XnoxsProto] SLOWMODE_WAIT_{$seconds}s — sleeping {$wait}s");
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                throw $e; // all other RPC errors propagate immediately

            } catch (\Exception $e) {
                // Network/socket error — attempt TCP reconnect and retry
                if ($attempt < $maxRetries - 1) {
                    $delay = min(1 + $attempt, 10); // 1s, 2s, 3s … cap at 10s
                    error_log("[XnoxsProto] Network error (attempt {$attempt}): {$e->getMessage()} — reconnecting in {$delay}s");
                    sleep($delay);
                    try {
                        $this->connection->close();
                        $this->connection->connect();
                    } catch (\Exception $reconnectEx) {
                        error_log("[XnoxsProto] Reconnect failed: {$reconnectEx->getMessage()}");
                    }
                    $attempt++;
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Max retries exceeded (FLOOD_WAIT / bad_server_salt / network errors)');
    }

    /**
     * Try to receive a server-pushed update within $timeoutSeconds.
     * Returns parsed update array or null if no update within timeout.
     *
     * Used by TelegramClient::runUntilDisconnected().
     */
    public function receiveUpdate(int $timeoutSeconds = 1): ?array
    {
        $raw = $this->connection->tryRecv($timeoutSeconds);
        if ($raw === null) return null;

        try {
            $plaintextReader = $this->decryptPacket($raw);
        } catch (\Exception $e) {
            return null;
        }

        try {
            $constructor = $plaintextReader->readInt();

            // Handle gzip_packed
            while ($constructor === 0x3072cfa1) {
                [$constructor, $plaintextReader] = $this->decompressGzip($plaintextReader);
            }

            // rpc_result — response to salah satu request kita (abaikan di update loop)
            if ($constructor === 0xf35c6d01) {
                return null;
            }

            // pong#347773c5 — respons dari ping kita (abaikan)
            if ($constructor === 0x347773c5) {
                return null;
            }

            // msgs_ack#62d6b459 — server ACK pesan kita (abaikan)
            if ($constructor === 0x62d6b459) {
                return null;
            }

            // msg_container — bisa berisi rpc_results + updates
            if ($constructor === 0x73f1f8dc) {
                return $this->extractUpdateFromContainer($plaintextReader);
            }

            // new_session_created — update salt
            if ($constructor === 0x9ec20908) {
                $plaintextReader->readLong(); // first_msg_id
                $plaintextReader->readLong(); // unique_id
                $this->salt = $plaintextReader->readLong();
                return null;
            }

            // bad_server_salt — update salt silently
            if ($constructor === 0xedab447b) {
                $plaintextReader->readLong(); // bad_msg_id
                $plaintextReader->readInt();  // bad_msg_seqno
                $plaintextReader->readInt();  // error_code
                $this->salt = $plaintextReader->readLong();
                return null;
            }

            if (UpdateParser::isUpdateConstructor($constructor)) {
                return UpdateParser::parse($constructor, $plaintextReader);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract ALL updates from a msg_container, ignoring rpc_results.
     * Returns multi-update array if more than one update found.
     */
    private function extractUpdateFromContainer(BinaryReader $reader): ?array
    {
        $containerSize = $reader->readInt();
        $collected = [];

        for ($i = 0; $i < $containerSize; $i++) {
            $reader->readLong(); // inner_msg_id
            $reader->readInt();  // inner_seqno
            $innerBytes = $reader->readInt();
            $innerCtor  = $reader->readInt();

            if ($innerCtor === 0xf35c6d01) {
                // rpc_result — skip payload
                $reader->read($innerBytes - 4);
                continue;
            }

            if ($innerCtor === 0x9ec20908) {
                // new_session_created — read 3 longs, update salt
                $reader->readLong(); $reader->readLong();
                $this->salt = $reader->readLong();
                continue;
            }

            if (UpdateParser::isUpdateConstructor($innerCtor)) {
                try {
                    $parsed = UpdateParser::parse($innerCtor, $reader);
                    if ($parsed !== null) {
                        // Flatten multi already returned by UpdateParser
                        if ($parsed['type'] === 'multi') {
                            foreach ($parsed['updates'] as $sub) {
                                $collected[] = $sub;
                            }
                        } else {
                            $collected[] = $parsed;
                        }
                    }
                } catch (\Exception $e) {
                    // Skip remaining payload for this item
                    $skip = $innerBytes - 4;
                    if ($skip > 0) { try { $reader->read($skip); } catch (\Exception $e2) { break; } }
                }
                continue;
            }

            // Unknown — skip
            $skip = $innerBytes - 4;
            if ($skip > 0) {
                try { $reader->read($skip); } catch (\Exception $e) { break; }
            }
        }

        if (empty($collected)) return null;
        if (count($collected) === 1) return $collected[0];
        return ['type' => 'multi', 'updates' => $collected];
    }

    /**
     * Decrypt an MTProto packet and return a BinaryReader positioned at the body.
     */
    private function decryptPacket(string $response): BinaryReader
    {
        $reader    = new BinaryReader($response);
        $authKeyId = $reader->read(8);

        if ($authKeyId !== $this->authKey->getKeyId()) {
            throw new \RuntimeException('Invalid auth_key_id in received packet');
        }

        $msgKey   = $reader->read(16);
        $encrypted = $reader->read(strlen($response) - 24);

        $plaintext   = $this->aesCalculate($encrypted, $msgKey, false);
        $msgKeyCheck = substr(hash('sha256', substr($this->authKey->getKey(), 96, 32) . $plaintext, true), 8, 16);

        if ($msgKey !== $msgKeyCheck) {
            throw new \RuntimeException('msg_key mismatch');
        }

        $ptReader = new BinaryReader($plaintext);
        $ptReader->readLong(); // salt
        $ptReader->readLong(); // session_id
        $ptReader->readLong(); // msg_id
        $ptReader->readInt();  // seqno
        $ptReader->readInt();  // length

        return $ptReader;
    }

    private function processResponse(string $response, int $expectedMsgId = 0): array
    {
        $reader = new BinaryReader($response);

        $authKeyId = $reader->read(8);
        if ($authKeyId !== $this->authKey->getKeyId()) {
            throw new \RuntimeException('Invalid auth_key_id in response');
        }

        $msgKey   = $reader->read(16);
        $encrypted = $reader->read(strlen($response) - 24);

        $plaintext    = $this->aesCalculate($encrypted, $msgKey, false);
        $msgKeyCheck  = substr(hash('sha256', substr($this->authKey->getKey(), 96, 32) . $plaintext, true), 8, 16);
        if ($msgKey !== $msgKeyCheck) {
            throw new \RuntimeException('msg_key mismatch - possible tampering detected');
        }

        $plaintextReader = new BinaryReader($plaintext);
        $salt            = $plaintextReader->readLong();
        $sessionId       = $plaintextReader->readLong();
        $msgId           = $plaintextReader->readLong();
        $seqNo           = $plaintextReader->readInt();
        $length          = $plaintextReader->readInt();

        $constructor = $plaintextReader->readInt();

        // ── rpc_result#f35c6d01 ───────────────────────────────────────────────
        if ($constructor === 0xf35c6d01) {
            $reqMsgId          = $plaintextReader->readLong();
            $resultConstructor = $plaintextReader->readInt();

            if ($expectedMsgId !== 0 && $reqMsgId !== $expectedMsgId) {
                $nextResponse = $this->connection->recv();
                return $this->processResponse($nextResponse, $expectedMsgId);
            }

            if ($resultConstructor === 0x2144ca19) { // rpc_error
                $errorCode    = $plaintextReader->readInt();
                $errorMessage = $plaintextReader->readString();
                throw new RPCException($errorCode, $errorMessage);
            }

            while ($resultConstructor === 0x3072cfa1) {
                [$resultConstructor, $plaintextReader] = $this->decompressGzip($plaintextReader);
            }

            return [
                'constructor' => $resultConstructor,
                'reader'      => $plaintextReader,
                'msg_id'      => $msgId,
                'req_msg_id'  => $reqMsgId,
                'length'      => $length,
            ];
        }

        // ── msg_container#73f1f8dc ────────────────────────────────────────────
        if ($constructor === 0x73f1f8dc) {
            $containerSize = $plaintextReader->readInt();

            for ($i = 0; $i < $containerSize; $i++) {
                $innerMsgId       = $plaintextReader->readLong();
                $innerSeqNo       = $plaintextReader->readInt();
                $innerBytes       = $plaintextReader->readInt();
                $innerConstructor = $plaintextReader->readInt();

                if ($innerConstructor === 0xf35c6d01) {
                    $reqMsgId          = $plaintextReader->readLong();
                    $resultConstructor = $plaintextReader->readInt();

                    if ($expectedMsgId !== 0 && $reqMsgId !== $expectedMsgId) {
                        $skip = $innerBytes - 16;
                        if ($skip > 0) $plaintextReader->read($skip);
                        continue;
                    }

                    while ($resultConstructor === 0x3072cfa1) {
                        [$resultConstructor, $plaintextReader] = $this->decompressGzip($plaintextReader);
                    }

                    return [
                        'constructor' => $resultConstructor,
                        'reader'      => $plaintextReader,
                        'msg_id'      => $innerMsgId,
                        'req_msg_id'  => $reqMsgId,
                        'length'      => $innerBytes - 12,
                    ];
                }

                if ($innerConstructor === 0x9ec20908) {
                    $plaintextReader->readLong();
                    $plaintextReader->readLong();
                    $this->salt = $plaintextReader->readLong();
                } elseif ($innerConstructor === 0xedab447b) {
                    $plaintextReader->readLong(); // bad_msg_id
                    $plaintextReader->readInt();  // bad_msg_seqno
                    $plaintextReader->readInt();  // error_code
                    $this->salt = $plaintextReader->readLong();
                } elseif ($innerConstructor === 0x347773c5) {
                    // pong — skip msg_id + ping_id (2 longs)
                    try { $plaintextReader->readLong(); $plaintextReader->readLong(); } catch (\Throwable) {}
                } elseif (UpdateParser::isUpdateConstructor($innerConstructor)) {
                    // Update dalam container — queue, jangan dibuang
                    try {
                        $parsed = UpdateParser::parse($innerConstructor, $plaintextReader);
                        if ($parsed !== null) {
                            if ($parsed['type'] === 'multi') {
                                foreach ($parsed['updates'] as $sub) { $this->pendingUpdates[] = $sub; }
                            } else {
                                $this->pendingUpdates[] = $parsed;
                            }
                        }
                    } catch (\Throwable) {
                        $skip = $innerBytes - 4;
                        if ($skip > 0) try { $plaintextReader->read($skip); } catch (\Throwable) { break; }
                    }
                } else {
                    $plaintextReader->read($innerBytes - 4);
                }
            }

            $nextResponse = $this->connection->recv();
            return $this->processResponse($nextResponse, $expectedMsgId);
        }

        // ── gzip_packed#3072cfa1 ──────────────────────────────────────────────
        if ($constructor === 0x3072cfa1) {
            [$constructor, $plaintextReader] = $this->decompressGzip($plaintextReader);
        }

        // ── pong#347773c5 — respons dari ping kita, abaikan ──────────────────
        if ($constructor === 0x347773c5) {
            $nextResponse = $this->connection->recv();
            return $this->processResponse($nextResponse, $expectedMsgId);
        }

        // ── Update / service messages — queue agar tidak hilang ──────────────
        $updateCtors = [
            0x74ae4240, 0x78d4dec1, 0x9e0d9b1f,
            0x11f1331c, 0xae0b0d43, 0x62d6b459,
        ];

        if (in_array($constructor, $updateCtors, true)) {
            // Simpan ke antrian — akan di-dispatch setelah send() selesai
            try {
                $parsed = UpdateParser::parse($constructor, $plaintextReader);
                if ($parsed !== null) {
                    if ($parsed['type'] === 'multi') {
                        foreach ($parsed['updates'] as $sub) {
                            $this->pendingUpdates[] = $sub;
                        }
                    } else {
                        $this->pendingUpdates[] = $parsed;
                    }
                }
            } catch (\Throwable) {}
            $nextResponse = $this->connection->recv();
            return $this->processResponse($nextResponse, $expectedMsgId);
        }

        return [
            'constructor' => $constructor,
            'reader'      => $plaintextReader,
            'msg_id'      => $msgId,
            'length'      => $length,
        ];
    }

    private function decompressGzip(BinaryReader $reader): array
    {
        $packedData   = $reader->readBytes();
        $decompressed = @gzdecode($packedData);
        if ($decompressed === false) $decompressed = @gzinflate($packedData);
        if ($decompressed === false) $decompressed = @gzuncompress($packedData);
        if ($decompressed === false) throw new \RuntimeException('Gagal decompress gzip_packed');

        $innerReader      = new BinaryReader($decompressed);
        $innerConstructor = $innerReader->readInt();
        return [$innerConstructor, $innerReader];
    }

    private function aesCalculate(string $data, string $msgKey, bool $encrypt): string
    {
        $x = $encrypt ? 0 : 8;
        $sha256a = hash('sha256', $msgKey . substr($this->authKey->getKey(), $x, 36), true);
        $sha256b = hash('sha256', substr($this->authKey->getKey(), 40 + $x, 36) . $msgKey, true);
        $aesKey = substr($sha256a, 0, 8) . substr($sha256b, 8, 16) . substr($sha256a, 24, 8);
        $aesIv  = substr($sha256b, 0, 8) . substr($sha256a, 8, 16) . substr($sha256b, 24, 8);
        return $encrypt ? AES::encryptIGE($data, $aesKey, $aesIv) : AES::decryptIGE($data, $aesKey, $aesIv);
    }

    private function getNewMsgId(): int
    {
        $now         = microtime(true) + $this->timeOffset;
        $nanoseconds = (int)(($now - floor($now)) * 1000000000);
        $seconds     = (int)floor($now);
        $newMsgId    = ($seconds << 32) | ($nanoseconds & 0xFFFFFFFC);
        if ($newMsgId <= $this->msgId) $newMsgId = $this->msgId + 4;
        $this->msgId = $newMsgId;
        return $newMsgId;
    }

    private function getSeqNo(bool $contentRelated): int
    {
        $seqNo = $this->seqNo * 2;
        if ($contentRelated) { $seqNo++; $this->seqNo++; }
        return $seqNo;
    }

    public function setTimeOffset(int $offset): void
    {
        $this->timeOffset = $offset;
    }
}
