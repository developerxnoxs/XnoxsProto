<?php

namespace XnoxsProto\TL;

class BinaryWriter
{
    private string $data = '';

    public function write(string $data): void
    {
        $this->data .= $data;
    }

    public function writeInt(int $value): void
    {
        $this->data .= pack('l', $value);
    }

    public function writeLong(int $value): void
    {
        $this->data .= pack('q', $value);
    }

    public function writeDouble(float $value): void
    {
        $this->data .= pack('d', $value);
    }

    public function writeBytes(string $data): void
    {
        $this->data .= TLObject::serializeBytes($data);
    }

    public function writeString(string $value): void
    {
        $this->writeBytes($value);
    }

    public function writeBool(bool $value): void
    {
        $this->writeInt($value ? 0x997275b5 : 0xbc799737);
    }

    public function write128(string $data): void
    {
        if (strlen($data) !== 16) {
            throw new \InvalidArgumentException('Data must be 16 bytes');
        }
        $this->data .= $data;
    }

    public function write256(string $data): void
    {
        if (strlen($data) !== 32) {
            throw new \InvalidArgumentException('Data must be 32 bytes');
        }
        $this->data .= $data;
    }

    public function getValue(): string
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = '';
    }

    public function getLength(): int
    {
        return strlen($this->data);
    }
}
