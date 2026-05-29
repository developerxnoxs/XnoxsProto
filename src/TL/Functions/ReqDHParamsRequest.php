<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;

class ReqDHParamsRequest extends TLObject
{
    const CONSTRUCTOR_ID = 0xd712e4be;

    public string $nonce;
    public string $serverNonce;
    public string $p;
    public string $q;
    public int $publicKeyFingerprint;
    public string $encryptedData;

    public function __construct(
        string $nonce,
        string $serverNonce,
        string $p,
        string $q,
        int $publicKeyFingerprint,
        string $encryptedData
    ) {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->p = $p;
        $this->q = $q;
        $this->publicKeyFingerprint = $publicKeyFingerprint;
        $this->encryptedData = $encryptedData;
    }

    public function toDict(): array
    {
        return [
            '_' => 'req_DH_params',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'p' => bin2hex($this->p),
            'q' => bin2hex($this->q),
            'public_key_fingerprint' => $this->publicKeyFingerprint,
            'encrypted_data' => bin2hex($this->encryptedData)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= self::serializeBytes($this->p);
        $r .= self::serializeBytes($this->q);
        $r .= pack('P', $this->publicKeyFingerprint);
        $r .= self::serializeBytes($this->encryptedData);
        
        return $r;
    }
}
