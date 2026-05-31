<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * auth.exportLoginToken#b7e085fe api_id:int api_hash:string except_ids:Vector<long> = auth.LoginToken
 *
 * Used to start the QR-code login flow. Returns auth.loginToken containing
 * a binary token (expires ~30 s) that must be base64url-encoded and embedded
 * in a tg://login?token=<base64url> URL, then shown as a QR code.
 */
class AuthExportLoginTokenRequest extends TLObject
{
    const CONSTRUCTOR = 0xb7e085fe;

    private int    $apiId;
    private string $apiHash;
    private array  $exceptIds;

    /**
     * @param int    $apiId     API ID from my.telegram.org/apps
     * @param string $apiHash   API Hash
     * @param int[]  $exceptIds List of already-authorised user IDs to exclude
     */
    public function __construct(int $apiId, string $apiHash, array $exceptIds = [])
    {
        $this->apiId     = $apiId;
        $this->apiHash   = $apiHash;
        $this->exceptIds = $exceptIds;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt($this->apiId);
        $writer->writeString($this->apiHash);

        // Vector<long> constructor 0x1cb5c415 + count + items
        $writer->writeInt(0x1cb5c415);
        $writer->writeInt(count($this->exceptIds));
        foreach ($this->exceptIds as $id) {
            $writer->writeLong($id);
        }
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'          => 'auth.exportLoginToken',
            'api_id'     => $this->apiId,
            'api_hash'   => $this->apiHash,
            'except_ids' => $this->exceptIds,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
