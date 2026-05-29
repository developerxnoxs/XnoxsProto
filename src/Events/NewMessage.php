<?php

namespace XnoxsProto\Events;

use XnoxsProto\TL\Types\FullMessage;

/**
 * NewMessage event filter — equivalent to Telethon's events.NewMessage.
 *
 * Usage:
 *   $filter = new Events\NewMessage(fromUsers: '@bot', incoming: true);
 *   $client->on($filter, function(NewMessageEvent $event) { ... });
 *
 * Or static factory:
 *   $filter = Events\NewMessage::from('@bot', incoming: true);
 */
class NewMessage
{
    /** @var string[]|int[]|null  Peer usernames/IDs to filter by. null = any. */
    private ?array $fromUsers;

    /** null = any, true = only incoming, false = only outgoing */
    private ?bool $incoming;

    /** If set, only events containing this string in text match. */
    private ?string $pattern;

    /**
     * @param string|int|array|null $fromUsers  '@bot', '+phone', user_id, or array
     * @param bool|null             $incoming   true=incoming, false=outgoing, null=both
     * @param string|null           $pattern    Simple substring filter on message text
     */
    public function __construct(
        string|int|array|null $fromUsers = null,
        ?bool                 $incoming  = null,
        ?string               $pattern   = null
    ) {
        if ($fromUsers === null) {
            $this->fromUsers = null;
        } elseif (is_array($fromUsers)) {
            $this->fromUsers = array_map(fn($u) => ltrim((string)$u, '@'), $fromUsers);
        } else {
            $this->fromUsers = [ltrim((string)$fromUsers, '@')];
        }

        $this->incoming = $incoming;
        $this->pattern  = $pattern;
    }

    public static function from(
        string|int|array|null $fromUsers = null,
        ?bool                 $incoming  = null,
        ?string               $pattern   = null
    ): self {
        return new self($fromUsers, $incoming, $pattern);
    }

    /**
     * Check if this filter matches an update.
     *
     * @param array $update   Parsed update array from UpdateParser
     * @param array $peerCache  ['username' => peer_info, 'id' => peer_info]
     */
    public function matches(array $update, array $peerCache = []): bool
    {
        if ($update['type'] !== 'new_message') return false;

        /** @var FullMessage $msg */
        $msg = $update['message'];

        // Direction filter
        if ($this->incoming === true && $msg->out)  return false;
        if ($this->incoming === false && !$msg->out) return false;

        // fromUsers filter
        if ($this->fromUsers !== null) {
            $matched = false;
            foreach ($this->fromUsers as $filterPeer) {
                if ($this->peerMatchesFilter($filterPeer, $msg, $peerCache)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) return false;
        }

        // Text pattern filter
        if ($this->pattern !== null && strpos($msg->text, $this->pattern) === false) {
            return false;
        }

        return true;
    }

    private function peerMatchesFilter(string $filter, FullMessage $msg, array $peerCache): bool
    {
        // Numeric: match by user/chat/channel id
        if (is_numeric($filter)) {
            $filterId = (int)$filter;
            if ($msg->fromUserId === $filterId) return true;
            if ($msg->peerId     === $filterId) return true;
            return false;
        }

        // Username: look up in cache
        $filter = ltrim($filter, '@');
        if (isset($peerCache[$filter])) {
            $cachedId = $peerCache[$filter]['id'] ?? null;
            if ($cachedId !== null) {
                if ($msg->fromUserId === $cachedId) return true;
                if ($msg->peerId     === $cachedId) return true;
            }
        }

        return false;
    }

    public function getFromUsers(): ?array  { return $this->fromUsers; }
    public function getIncoming(): ?bool    { return $this->incoming; }
    public function getPattern(): ?string   { return $this->pattern; }
}
