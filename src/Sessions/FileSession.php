<?php

namespace XnoxsProto\Sessions;

/**
 * FileSession — encrypted binary session storage.
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  File layout                                                        │
 * │  ─────────────────────────────────────────────────────────────────  │
 * │  [4]   Magic        "XNXS"                                          │
 * │  [1]   Version      0x01                                            │
 * │  [1]   Flags        0x01 = AES-256-CBC encrypted                    │
 * │  [16]  IV           random per save                                 │
 * │  [4]   Payload len  uint32 LE                                       │
 * │  [N]   Payload      AES-256-CBC(TLV binary, key=encKey, iv=IV)      │
 * │  [32]  HMAC         HMAC-SHA256 over all bytes above (mac key)      │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * TLV payload — each record:
 *   [1 byte tag][1 byte type][value]
 *
 *   Type 0x01 → INT32  : 4 bytes LE signed
 *   Type 0x02 → INT64  : 8 bytes LE signed
 *   Type 0x03 → BYTES  : 4 bytes LE length + raw bytes
 *   Type 0x04 → BOOL   : 1 byte (0x00 / 0x01)
 *
 * Keys are derived per-machine per-file (non-portable by design, same
 * philosophy as Telethon's local SQLite sessions).
 */
class FileSession extends AbstractSession
{
    // ── Binary format constants ──────────────────────────────────────────
    private const MAGIC   = "XNXS";
    private const VERSION = 0x01;
    private const F_ENC   = 0x01;

    // TLV types
    private const T_INT32 = 0x01;
    private const T_INT64 = 0x02;
    private const T_BYTES = 0x03;
    private const T_BOOL  = 0x04;

    // TLV field tags
    private const F_DC_ID       = 0x01;
    private const F_PORT        = 0x02;
    private const F_USER_ID     = 0x03;
    private const F_AUTHORIZED  = 0x04;
    private const F_LAYER       = 0x05;
    private const F_AUTH_KEY    = 0x06;
    private const F_SERVER_ADDR = 0x07;
    private const F_PTS         = 0x08;
    private const F_QTS         = 0x09;
    private const F_DATE        = 0x0A;
    private const F_SEQ         = 0x0B;
    private const F_ENTITIES    = 0x0C;

    // ── Session state ────────────────────────────────────────────────────
    private string  $filename;
    private ?int    $dcId          = null;
    private ?string $serverAddress = null;
    private ?int    $port          = null;
    private ?string $authKey       = null;
    private bool    $authorized    = false;
    private ?int    $userId        = null;
    private array   $updateState   = [];
    private array   $entities      = [];
    private ?int    $layer         = null;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->load();
    }

    // ── AbstractSession API ──────────────────────────────────────────────

    public function setDC(int $dcId, string $serverAddress, int $port): void
    {
        $this->dcId          = $dcId;
        $this->serverAddress = $serverAddress;
        $this->port          = $port;
        $this->save();
    }

    public function getDC(): ?array
    {
        if ($this->dcId === null) return null;
        return [
            'dc_id'          => $this->dcId,
            'server_address' => $this->serverAddress,
            'port'           => $this->port,
        ];
    }

    public function setAuthKey(?string $authKey): void
    {
        $this->authKey = $authKey;
        $this->save();
    }

    public function getAuthKey(): ?string
    {
        return $this->authKey;
    }

    public function setAuthorized(bool $authorized, ?int $userId = null): void
    {
        $this->authorized = $authorized;
        if ($userId !== null) $this->userId = $userId;
        $this->save();
    }

    public function isUserAuthorized(): bool
    {
        return $this->authorized;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setLayer(int $layer): void
    {
        $this->layer = $layer;
        $this->save();
    }

    public function getLayer(): ?int
    {
        return $this->layer;
    }

    public function setUpdateState(int $pts, int $qts, int $date, int $seq): void
    {
        $this->updateState = compact('pts', 'qts', 'date', 'seq');
        $this->save();
    }

    public function getUpdateState(): ?array
    {
        return empty($this->updateState) ? null : $this->updateState;
    }

    public function processEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            if (isset($entity['id'])) $this->entities[(int)$entity['id']] = $entity;
        }
        $this->save();
    }

    public function getEntityRowsByUsername(string $username): ?array
    {
        foreach ($this->entities as $entity) {
            if (isset($entity['username']) && $entity['username'] === $username) return $entity;
        }
        return null;
    }

    public function getEntityRowsByPhone(string $phone): ?array
    {
        foreach ($this->entities as $entity) {
            if (isset($entity['phone']) && $entity['phone'] === $phone) return $entity;
        }
        return null;
    }

    public function getEntityRowsById(int $id): ?array
    {
        return $this->entities[$id] ?? null;
    }

    // ── Persistence ──────────────────────────────────────────────────────

    public function save(): void
    {
        $payload = $this->encodeTLV();
        [$encKey, $macKey] = $this->deriveKeys();

        $iv        = random_bytes(16);
        $encrypted = openssl_encrypt($payload, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Session encryption failed: ' . openssl_error_string());
        }

        $lenBytes = pack('V', strlen($encrypted));

        $header  = self::MAGIC . chr(self::VERSION) . chr(self::F_ENC) . $iv . $lenBytes;
        $hmac    = hash_hmac('sha256', $header . $encrypted, $macKey, true);
        $fileData = $header . $encrypted . $hmac;

        if (file_put_contents($this->filename, $fileData, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write session file: ' . $this->filename);
        }
    }

    public function load(): void
    {
        if (!file_exists($this->filename)) return;

        $raw = file_get_contents($this->filename);
        if ($raw === false || $raw === '') return;

        // ── Auto-migrate: JSON → binary ──────────────────────────────────
        if ($raw[0] === '{') {
            $this->loadFromJson($raw);
            $this->save(); // re-save in new binary format
            return;
        }

        $this->loadFromBinary($raw);
    }

    public function delete(): void
    {
        if (file_exists($this->filename)) unlink($this->filename);
        $this->resetState();
    }

    // ── Internal: binary save/load ───────────────────────────────────────

    private function loadFromBinary(string $raw): void
    {
        // Minimum size: 4 + 1 + 1 + 16 + 4 + 0 + 32 = 58 bytes
        if (strlen($raw) < 58) return;

        $magic = substr($raw, 0, 4);
        if ($magic !== self::MAGIC) return;

        $version = ord($raw[4]);
        $flags   = ord($raw[5]);

        if ($version !== self::VERSION) return;
        if (!($flags & self::F_ENC)) return;

        $iv      = substr($raw, 6, 16);
        $payLen  = unpack('V', substr($raw, 22, 4))[1];

        // Validate lengths
        $expectedTotal = 22 + 4 + $payLen + 32;
        if (strlen($raw) !== $expectedTotal) return;

        $encrypted = substr($raw, 26, $payLen);
        $hmac      = substr($raw, 26 + $payLen, 32);

        [$encKey, $macKey] = $this->deriveKeys();

        // Verify HMAC first (encrypt-then-MAC)
        $headerAndPayload = substr($raw, 0, 26 + $payLen);
        $expectedHmac = hash_hmac('sha256', $headerAndPayload, $macKey, true);
        if (!hash_equals($expectedHmac, $hmac)) {
            // Corrupted or from a different machine — start fresh
            return;
        }

        $plaintext = openssl_decrypt($encrypted, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) return;

        $this->decodeTLV($plaintext);
    }

    private function loadFromJson(string $json): void
    {
        $data = json_decode($json, true);
        if (!is_array($data)) return;

        $this->dcId          = $data['dc_id']          ?? null;
        $this->serverAddress = $data['server_address'] ?? null;
        $this->port          = $data['port']           ?? null;
        $this->authKey       = isset($data['auth_key']) ? base64_decode($data['auth_key']) : null;
        $this->authorized    = (bool)($data['authorized'] ?? false);
        $this->userId        = $data['user_id']        ?? null;
        $this->updateState   = $data['update_state']   ?? [];
        $this->entities      = $data['entities']       ?? [];
        $this->layer         = $data['layer']          ?? null;
    }

    // ── TLV encoding ─────────────────────────────────────────────────────

    private function encodeTLV(): string
    {
        $buf = '';

        if ($this->dcId !== null)
            $buf .= $this->tlvInt32(self::F_DC_ID, $this->dcId);

        if ($this->port !== null)
            $buf .= $this->tlvInt32(self::F_PORT, $this->port);

        if ($this->userId !== null)
            $buf .= $this->tlvInt64(self::F_USER_ID, $this->userId);

        $buf .= $this->tlvBool(self::F_AUTHORIZED, $this->authorized);

        if ($this->layer !== null)
            $buf .= $this->tlvInt32(self::F_LAYER, $this->layer);

        if ($this->authKey !== null)
            $buf .= $this->tlvBytes(self::F_AUTH_KEY, $this->authKey);

        if ($this->serverAddress !== null)
            $buf .= $this->tlvBytes(self::F_SERVER_ADDR, $this->serverAddress);

        if (!empty($this->updateState)) {
            $buf .= $this->tlvInt32(self::F_PTS,  $this->updateState['pts']  ?? 0);
            $buf .= $this->tlvInt32(self::F_QTS,  $this->updateState['qts']  ?? 0);
            $buf .= $this->tlvInt32(self::F_DATE, $this->updateState['date'] ?? 0);
            $buf .= $this->tlvInt32(self::F_SEQ,  $this->updateState['seq']  ?? 0);
        }

        if (!empty($this->entities)) {
            $buf .= $this->tlvBytes(self::F_ENTITIES, json_encode($this->entities, JSON_UNESCAPED_UNICODE));
        }

        return $buf;
    }

    private function decodeTLV(string $data): void
    {
        $i   = 0;
        $len = strlen($data);
        $pts = null; $qts = null; $date = null; $seq = null;

        while ($i + 2 <= $len) {
            $tag  = ord($data[$i]);
            $type = ord($data[$i + 1]);
            $i   += 2;

            switch ($type) {
                case self::T_INT32:
                    if ($i + 4 > $len) break 2;
                    $val = unpack('l', substr($data, $i, 4))[1];
                    $i  += 4;
                    $this->applyField($tag, $val, $pts, $qts, $date, $seq);
                    break;

                case self::T_INT64:
                    if ($i + 8 > $len) break 2;
                    $val = unpack('q', substr($data, $i, 8))[1];
                    $i  += 8;
                    $this->applyField($tag, $val, $pts, $qts, $date, $seq);
                    break;

                case self::T_BYTES:
                    if ($i + 4 > $len) break 2;
                    $bLen = unpack('V', substr($data, $i, 4))[1];
                    $i   += 4;
                    if ($i + $bLen > $len) break 2;
                    $bytes = substr($data, $i, $bLen);
                    $i    += $bLen;
                    $this->applyField($tag, $bytes, $pts, $qts, $date, $seq);
                    break;

                case self::T_BOOL:
                    if ($i + 1 > $len) break 2;
                    $val = ord($data[$i]) !== 0;
                    $i  += 1;
                    $this->applyField($tag, $val, $pts, $qts, $date, $seq);
                    break;

                default:
                    // Unknown type — cannot determine field size, stop parsing
                    break 2;
            }
        }

        if ($pts !== null || $qts !== null || $date !== null || $seq !== null) {
            $this->updateState = [
                'pts'  => $pts  ?? 0,
                'qts'  => $qts  ?? 0,
                'date' => $date ?? 0,
                'seq'  => $seq  ?? 0,
            ];
        }
    }

    private function applyField(int $tag, mixed $val, ?int &$pts, ?int &$qts, ?int &$date, ?int &$seq): void
    {
        match ($tag) {
            self::F_DC_ID       => $this->dcId          = (int)$val,
            self::F_PORT        => $this->port           = (int)$val,
            self::F_USER_ID     => $this->userId         = (int)$val,
            self::F_AUTHORIZED  => $this->authorized     = (bool)$val,
            self::F_LAYER       => $this->layer          = (int)$val,
            self::F_AUTH_KEY    => $this->authKey        = (string)$val,
            self::F_SERVER_ADDR => $this->serverAddress  = (string)$val,
            self::F_PTS         => $pts                  = (int)$val,
            self::F_QTS         => $qts                  = (int)$val,
            self::F_DATE        => $date                 = (int)$val,
            self::F_SEQ         => $seq                  = (int)$val,
            self::F_ENTITIES    => $this->entities       = (array)(json_decode((string)$val, true) ?? []),
            default             => null,
        };
    }

    // ── TLV field builders ───────────────────────────────────────────────

    private function tlvInt32(int $tag, int $val): string
    {
        return chr($tag) . chr(self::T_INT32) . pack('l', $val);
    }

    private function tlvInt64(int $tag, int $val): string
    {
        return chr($tag) . chr(self::T_INT64) . pack('q', $val);
    }

    private function tlvBytes(int $tag, string $val): string
    {
        return chr($tag) . chr(self::T_BYTES) . pack('V', strlen($val)) . $val;
    }

    private function tlvBool(int $tag, bool $val): string
    {
        return chr($tag) . chr(self::T_BOOL) . chr($val ? 1 : 0);
    }

    // ── Key derivation ───────────────────────────────────────────────────
    //
    // Keys are derived from just the session filename (basename), making
    // sessions portable across machines.
    // Two separate keys are derived: one for encryption, one for MAC.

    /** @return array{string, string} [encKey (32 bytes), macKey (32 bytes)] */
    private function deriveKeys(): array
    {
        // Use only the basename so sessions are portable across machines.
        $name   = basename($this->filename);
        $base   = 'XnoxsProto:session:' . $name;
        $encKey = hash('sha256', $base . ':enc', true);
        $macKey = hash('sha256', $base . ':mac', true);

        return [$encKey, $macKey];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resetState(): void
    {
        $this->dcId          = null;
        $this->serverAddress = null;
        $this->port          = null;
        $this->authKey       = null;
        $this->authorized    = false;
        $this->userId        = null;
        $this->updateState   = [];
        $this->entities      = [];
        $this->layer         = null;
    }
}
