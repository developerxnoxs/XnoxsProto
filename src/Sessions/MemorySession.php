<?php

namespace XnoxsProto\Sessions;

class MemorySession extends AbstractSession
{
    private ?int    $dcId          = null;
    private ?string $serverAddress = null;
    private ?int    $port          = null;
    private ?string $authKey       = null;
    private bool    $authorized    = false;
    private ?int    $userId        = null;
    private array   $updateState   = [];
    private array   $entities      = [];
    private ?int    $layer         = null;

    public function setDC(int $dcId, string $serverAddress, int $port): void
    {
        $this->dcId          = $dcId;
        $this->serverAddress = $serverAddress;
        $this->port          = $port;
    }

    public function getDC(): ?array
    {
        if ($this->dcId === null) return null;
        return [
            'dc_id'          => $this->dcId,
            'server_address' => $this->serverAddress,
            'port'           => $this->port,
        ];
    }

    public function setAuthKey(?string $authKey): void
    {
        $this->authKey = $authKey;
    }

    public function getAuthKey(): ?string
    {
        return $this->authKey;
    }

    public function setAuthorized(bool $authorized, ?int $userId = null): void
    {
        $this->authorized = $authorized;
        if ($userId !== null) $this->userId = $userId;
    }

    public function isUserAuthorized(): bool
    {
        return $this->authorized;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setLayer(int $layer): void
    {
        $this->layer = $layer;
    }

    public function getLayer(): ?int
    {
        return $this->layer;
    }

    public function save(): void {}

    public function load(): void {}

    public function delete(): void
    {
        $this->dcId          = null;
        $this->serverAddress = null;
        $this->port          = null;
        $this->authKey       = null;
        $this->authorized    = false;
        $this->userId        = null;
        $this->updateState   = [];
        $this->entities      = [];
        $this->layer         = null;
    }

    public function setUpdateState(int $pts, int $qts, int $date, int $seq): void
    {
        $this->updateState = compact('pts', 'qts', 'date', 'seq');
    }

    public function getUpdateState(): ?array
    {
        return empty($this->updateState) ? null : $this->updateState;
    }

    public function processEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            if (isset($entity['id'])) $this->entities[$entity['id']] = $entity;
        }
    }

    public function getEntityRowsByUsername(string $username): ?array
    {
        foreach ($this->entities as $entity) {
            if (isset($entity['username']) && $entity['username'] === $username) return $entity;
        }
        return null;
    }

    public function getEntityRowsByPhone(string $phone): ?array
    {
        foreach ($this->entities as $entity) {
            if (isset($entity['phone']) && $entity['phone'] === $phone) return $entity;
        }
        return null;
    }

    public function getEntityRowsById(int $id): ?array
    {
        return $this->entities[$id] ?? null;
    }
}
