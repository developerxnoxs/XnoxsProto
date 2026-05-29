<?php

namespace XnoxsProto\Network;

class Connection
{
    protected string $ip;
    protected int    $port;
    private ?TcpAbridged $transport = null;

    public function __construct(string $ip, int $port)
    {
        $this->ip   = $ip;
        $this->port = $port;
    }

    public function connect(): void
    {
        $this->transport = new TcpAbridged($this->ip, $this->port);
    }

    public function send(string $data): void
    {
        $this->transport->send($data);
    }

    public function recv(): string
    {
        return $this->transport->recv();
    }

    /**
     * Try to receive data within $timeoutSeconds. Returns null on timeout.
     */
    public function tryRecv(int $timeoutSeconds = 1): ?string
    {
        if ($this->transport === null) return null;
        return $this->transport->tryRecv($timeoutSeconds);
    }

    public function close(): void
    {
        if ($this->transport) {
            $this->transport->close();
            $this->transport = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->transport !== null && $this->transport->isConnected();
    }

    public function getTransport(): ?TcpAbridged
    {
        return $this->transport;
    }

    /**
     * Set transport directly (used by Socks5Connection after tunnel negotiation).
     */
    protected function setTransport(TcpAbridged $transport): void
    {
        $this->transport = $transport;
    }
}
