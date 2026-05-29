<?php

namespace XnoxsProto\TL;

abstract class TLObject
{
    const CONSTRUCTOR_ID = null;
    const SUBCLASS_OF_ID = null;

    public static function serializeBytes(string $data): string
    {
        $r = '';
        $length = strlen($data);
        
        if ($length < 254) {
            $padding = ($length + 1) % 4;
            if ($padding !== 0) {
                $padding = 4 - $padding;
            }
            
            $r .= chr($length);
            $r .= $data;
        } else {
            $padding = $length % 4;
            if ($padding !== 0) {
                $padding = 4 - $padding;
            }
            
            $r .= chr(254);
            $r .= substr(pack('V', $length), 0, 3);
            $r .= $data;
        }
        
        $r .= str_repeat("\0", $padding);
        
        return $r;
    }

    public static function serializeInt(int $value): string
    {
        return pack('l', $value);
    }

    public static function serializeLong(int $value): string
    {
        return pack('q', $value);
    }

    public static function serializeDouble(float $value): string
    {
        return pack('d', $value);
    }

    public static function serializeBool(bool $value): string
    {
        return pack('L', $value ? 0x997275b5 : 0xbc799737);
    }

    public static function serializeString(string $value): string
    {
        return self::serializeBytes($value);
    }

    public static function serializeDateTime(\DateTime $dt): string
    {
        return pack('l', $dt->getTimestamp());
    }

    abstract public function toDict(): array;
    
    abstract public function toBytes(): string;
}
