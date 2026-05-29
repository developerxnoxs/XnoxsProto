<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;

class SetClientDHParamsRequest extends TLObject
{
    const CONSTRUCTOR_ID = 0xf5045f1f;

    public string $nonce;
    public string $serverNonce;
    public string $encryptedData;

    public function __construct(
        string $nonce,
        string $serverNonce,
        string $encryptedData
    ) {
        $this->nonce = $nonce;
        $this->serverNonce = $serverNonce;
        $this->encryptedData = $encryptedData;
    }

    public function toDict(): array
    {
        return [
            '_' => 'set_client_DH_params',
            'nonce' => bin2hex($this->nonce),
            'server_nonce' => bin2hex($this->serverNonce),
            'encrypted_data' => bin2hex($this->encryptedData)
        ];
    }

    public function toBytes(): string
    {
        $r = pack('V', self::CONSTRUCTOR_ID);
        $r .= $this->nonce;
        $r .= $this->serverNonce;
        $r .= self::serializeBytes($this->encryptedData);
        
        return $r;
    }
}
