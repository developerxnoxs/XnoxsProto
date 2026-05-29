<?php

namespace XnoxsProto\Network;

/**
 * SOCKS5 proxy transport layer.
 *
 * Routes Telegram MTProto traffic through a SOCKS5 proxy server.
 * Supports both anonymous and username/password authenticated proxies.
 *
 * Usage via TelegramClient:
 *   $client->setProxy('127.0.0.1', 1080);                   // no auth
 *   $client->setProxy('proxy.host', 1080, 'user', 'pass');  // with auth
 *   $client->connect();
 */
class Socks5Connection extends Connection
{
    private string  $proxyHost;
    private int     $proxyPort;
    private ?string $proxyUser;
    private ?string $proxyPass;

    public function __construct(
        string  $targetIp,
        int     $targetPort,
        string  $proxyHost,
        int     $proxyPort,
        ?string $proxyUser = null,
        ?string $proxyPass = null
    ) {
        parent::__construct($targetIp, $targetPort);
        $this->proxyHost = $proxyHost;
        $this->proxyPort = $proxyPort;
        $this->proxyUser = $proxyUser;
        $this->proxyPass = $proxyPass;
    }

    /**
     * Negotiate SOCKS5 tunnel then initialise MTProto Abridged transport.
     */
    public function connect(): void
    {
        // --- Open raw TCP to proxy server ---
        $errno  = 0;
        $errstr = '';
        $sock   = @fsockopen($this->proxyHost, $this->proxyPort, $errno, $errstr, 10);

        if (!$sock) {
            throw new \RuntimeException(
                "SOCKS5: Cannot connect to proxy {$this->proxyHost}:{$this->proxyPort} — $errstr ($errno)"
            );
        }

        stream_set_timeout($sock, 10);

        $useAuth = ($this->proxyUser !== null && $this->proxyPass !== null);

        // --- Step 1: Client greeting ---
        fwrite($sock, $useAuth ? "\x05\x02\x00\x02" : "\x05\x01\x00");

        $resp = fread($sock, 2);
        if (strlen($resp) < 2 || $resp[0] !== "\x05") {
            fclose($sock);
            throw new \RuntimeException('SOCKS5: Invalid greeting response from proxy');
        }

        $method = ord($resp[1]);

        if ($method === 0xFF) {
            fclose($sock);
            throw new \RuntimeException('SOCKS5: Proxy rejected all authentication methods');
        }

        // --- Step 2: Authenticate (username/password sub-negotiation) ---
        if ($method === 0x02) {
            if (!$useAuth) {
                fclose($sock);
                throw new \RuntimeException('SOCKS5: Proxy requires credentials but none were provided');
            }

            $user    = $this->proxyUser;
            $pass    = $this->proxyPass;
            fwrite($sock, "\x01" . chr(strlen($user)) . $user . chr(strlen($pass)) . $pass);

            $authResp = fread($sock, 2);
            if (strlen($authResp) < 2 || $authResp[1] !== "\x00") {
                fclose($sock);
                throw new \RuntimeException('SOCKS5: Authentication failed (invalid credentials)');
            }
        }

        // --- Step 3: CONNECT request to Telegram target ---
        $ip    = $this->ip;
        $port  = $this->port;
        $parts = explode('.', $ip);

        if (count($parts) === 4) {
            // IPv4 — ATYP=0x01
            $connectMsg = "\x05\x01\x00\x01"
                . chr((int)$parts[0]) . chr((int)$parts[1])
                . chr((int)$parts[2]) . chr((int)$parts[3])
                . pack('n', $port);
        } else {
            // Domain name — ATYP=0x03
            $connectMsg = "\x05\x01\x00\x03" . chr(strlen($ip)) . $ip . pack('n', $port);
        }

        fwrite($sock, $connectMsg);

        // Read CONNECT response: VER REP RSV ATYP [BND.ADDR] BND.PORT
        $resp = fread($sock, 4);
        if (strlen($resp) < 4 || $resp[0] !== "\x05") {
            fclose($sock);
            throw new \RuntimeException('SOCKS5: Invalid CONNECT response');
        }

        $rep = ord($resp[1]);
        if ($rep !== 0x00) {
            fclose($sock);
            $errors = [
                1 => 'general server failure',
                2 => 'connection not allowed by ruleset',
                3 => 'network unreachable',
                4 => 'host unreachable',
                5 => 'connection refused',
                6 => 'TTL expired',
                7 => 'command not supported',
                8 => 'address type not supported',
            ];
            throw new \RuntimeException('SOCKS5 CONNECT failed: ' . ($errors[$rep] ?? "unknown error $rep"));
        }

        // Skip bound address
        $atyp = ord($resp[3]);
        if ($atyp === 0x01) {
            fread($sock, 6);                          // 4-byte IPv4 + 2-byte port
        } elseif ($atyp === 0x03) {
            $len = ord(fread($sock, 1));
            fread($sock, $len + 2);                   // domain + 2-byte port
        } elseif ($atyp === 0x04) {
            fread($sock, 18);                         // 16-byte IPv6 + 2-byte port
        }

        // Tunnel ready — wrap with MTProto Abridged
        $transport = TcpAbridged::fromSocket($sock);
        $this->setTransport($transport);
    }
}
