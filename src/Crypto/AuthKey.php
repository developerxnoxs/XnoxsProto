<?php

namespace XnoxsProto\Crypto;

class AuthKey
{
    private ?string $key;
    private ?string $keyId;
    private ?string $auxHash;

    public function __construct(?string $data = null)
    {
        if ($data === null) {
            $this->key = null;
            $this->keyId = null;
            $this->auxHash = null;
        } else {
            if (strlen($data) !== 2048 / 8) {
                throw new \InvalidArgumentException('AuthKey must be 256 bytes long');
            }
            
            $this->key = $data;
            
            $hash = sha1($this->key, true);
            $this->auxHash = substr($hash, 0, 8);
            $this->keyId = substr($hash, -8);
        }
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getKeyId(): ?string
    {
        return $this->keyId;
    }

    public function getAuxHash(): ?string
    {
        return $this->auxHash;
    }

    public function calcNewNonceHash(string $newNonce, int $number): string
    {
        $data = $newNonce . pack('C', $number) . $this->auxHash;
        $hash = sha1($data, true);
        return substr($hash, -16);
    }
}
