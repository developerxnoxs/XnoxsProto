<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

class InitConnectionRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0xc1cd5ea9;
    
    private int $flags;
    private int $apiId;
    private string $deviceModel;
    private string $systemVersion;
    private string $appVersion;
    private string $systemLangCode;
    private string $langPack;
    private string $langCode;
    private $query;

    public function __construct(
        int $apiId,
        string $deviceModel,
        string $systemVersion,
        string $appVersion,
        string $systemLangCode,
        string $langPack,
        string $langCode,
        $query
    ) {
        $this->flags = 0;
        $this->apiId = $apiId;
        $this->deviceModel = $deviceModel;
        $this->systemVersion = $systemVersion;
        $this->appVersion = $appVersion;
        $this->systemLangCode = $systemLangCode;
        $this->langPack = $langPack;
        $this->langCode = $langCode;
        $this->query = $query;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);
        $writer->writeInt($this->flags);
        $writer->writeInt($this->apiId);
        $writer->writeString($this->deviceModel);
        $writer->writeString($this->systemVersion);
        $writer->writeString($this->appVersion);
        $writer->writeString($this->systemLangCode);
        $writer->writeString($this->langPack);
        $writer->writeString($this->langCode);
        $this->query->serialize($writer);
    }

    public function toDict(): array
    {
        return [
            '_' => 'initConnection',
            'api_id' => $this->apiId,
            'device_model' => $this->deviceModel,
            'system_version' => $this->systemVersion,
            'app_version' => $this->appVersion,
            'system_lang_code' => $this->systemLangCode,
            'lang_pack' => $this->langPack,
            'lang_code' => $this->langCode,
            'query' => $this->query->toDict()
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
