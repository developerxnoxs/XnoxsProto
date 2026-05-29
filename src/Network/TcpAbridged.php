<?php

namespace XnoxsProto\Network;

class TcpAbridged
{
    private $socket = null;
    private bool $initialized = false;

    /**
     * @param string|null $ip   Pass null to create an unconnected instance (use connectWithSocket later)
     * @param int         $port Target port
     */
    public function __construct(?string $ip = null, int $port = 443)
    {
        if ($ip === null) {
            // Unconnected instance — caller will call connectWithSocket()
            return;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ]);

        $this->socket = stream_socket_client(
            "tcp://{$ip}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \RuntimeException("Failed to connect to {$ip}:{$port} — $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 30);
        stream_set_blocking($this->socket, true);

        fwrite($this->socket, "\xef");

        $this->initialized = true;
    }

    /**
     * Initialize from an existing raw socket (used by SOCKS5 proxy).
     * The socket is already tunneled to Telegram via the proxy.
     */
    public function connectWithSocket($socket): void
    {
        if ($this->socket) {
            fclose($this->socket);
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, 30);
        stream_set_blocking($this->socket, true);
        // Send MTProto Abridged init byte through the tunnel
        fwrite($this->socket, "\xef");
        $this->initialized = true;
    }

    /**
     * Factory: create a TcpAbridged instance wrapping an already-open socket.
     * Used by Socks5Connection.
     */
    public static function fromSocket($socket): self
    {
        $instance = new self(null);
        $instance->connectWithSocket($socket);
        return $instance;
    }

    public function send(string $data): void
    {
        $length = strlen($data) >> 2;

        if ($length < 127) {
            $packet = pack('C', $length) . $data;
        } else {
            $packet = "\x7f" . substr(pack('V', $length), 0, 3) . $data;
        }

        $written = fwrite($this->socket, $packet);
        if ($written === false || $written !== strlen($packet)) {
            throw new \RuntimeException('Failed to send packet');
        }
    }

    public function recv(): string
    {
        $lengthByte = fread($this->socket, 1);
        if ($lengthByte === false || $lengthByte === '') {
            throw new \RuntimeException('Failed to read length byte');
        }

        $length = ord($lengthByte);

        if ($length >= 127) {
            $lengthBytes = fread($this->socket, 3);
            if (strlen($lengthBytes) !== 3) {
                throw new \RuntimeException('Failed to read extended length');
            }
            $length = unpack('V', $lengthBytes . "\x00")[1];
        }

        $dataLength = $length << 2;

        $data = '';
        while (strlen($data) < $dataLength) {
            $chunk = fread($this->socket, $dataLength - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Failed to read packet data');
            }
            $data .= $chunk;
        }

        return $data;
    }

    /**
     * Try to receive a packet within $timeoutSeconds.
     * Returns null on timeout or no data.
     */
    public function tryRecv(int $timeoutSeconds = 1): ?string
    {
        if (!$this->socket || feof($this->socket)) {
            return null;
        }

        $read   = [$this->socket];
        $write  = null;
        $except = null;

        $result = stream_select($read, $write, $except, $timeoutSeconds, 0);

        if ($result === false || $result === 0) {
            return null;
        }

        try {
            return $this->recv();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setTimeout(int $seconds): void
    {
        if ($this->socket) {
            stream_set_timeout($this->socket, $seconds);
        }
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
        $this->initialized = false;
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && !feof($this->socket);
    }

    public function getSocket()
    {
        return $this->socket;
    }
}
