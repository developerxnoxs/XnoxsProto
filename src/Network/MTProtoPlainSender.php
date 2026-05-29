<?php

namespace XnoxsProto\Network;

use XnoxsProto\Helpers\Helpers;

class MTProtoPlainSender
{
    private TcpAbridged $connection;

    public function __construct(TcpAbridged $connection)
    {
        $this->connection = $connection;
    }

    public function send(string $data): int
    {
        $messageId = Helpers::generateMessageId();
        
        $packet = pack('P', 0);
        $packet .= pack('P', $messageId);
        $packet .= pack('V', strlen($data));
        $packet .= $data;
        
        $this->connection->send($packet);
        
        return $messageId;
    }

    public function recv(): array
    {
        $response = $this->connection->recv();
        
        if (strlen($response) < 20) {
            throw new \RuntimeException('Invalid response length: ' . strlen($response));
        }
        
        $authKeyId = unpack('P', substr($response, 0, 8))[1];
        $messageId = unpack('P', substr($response, 8, 8))[1];
        $messageLength = unpack('V', substr($response, 16, 4))[1];
        $data = substr($response, 20);
        
        if (strlen($data) !== $messageLength) {
            throw new \RuntimeException("Message length mismatch: expected $messageLength, got " . strlen($data));
        }
        
        if ($authKeyId !== 0) {
            throw new \RuntimeException('Expected unencrypted message (auth_key_id should be 0)');
        }
        
        return [
            'auth_key_id' => $authKeyId,
            'message_id' => $messageId,
            'data' => $data
        ];
    }

    public function sendRecv(string $data): array
    {
        $this->send($data);
        return $this->recv();
    }
}
