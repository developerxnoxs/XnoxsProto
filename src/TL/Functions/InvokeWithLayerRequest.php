<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

class InvokeWithLayerRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0xda9b0d0d;
    
    private int $layer;
    private $query;

    public function __construct(int $layer, $query)
    {
        $this->layer = $layer;
        $this->query = $query;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);
        $writer->writeInt($this->layer);
        $this->query->serialize($writer);
    }

    public function toDict(): array
    {
        return [
            '_' => 'invokeWithLayer',
            'layer' => $this->layer,
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
