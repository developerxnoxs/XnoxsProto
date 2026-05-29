<?php

namespace XnoxsProto\Events;

/**
 * Event fired for all server-pushed updates.
 *
 * Properties vary by type. Check $event->type before accessing other fields.
 *
 * Common types:
 *   'new_message'      — $event->message (FullMessage), $event->users, $event->chats
 *   'delete_messages'  — $event->messageIds (int[]), $event->channelId (int|null)
 *   'read_history'     — $event->peerId, $event->maxId (int), $event->direction ('in'|'out')
 *   'pinned_messages'  — $event->messageIds (int[]), $event->peerId, $event->pinned (bool)
 *   'user_status'      — $event->userId (int), $event->online (bool), $event->wasOnline (int)
 *   'edit_message'     — $event->message (FullMessage)
 */
class RawUpdateEvent
{
    public readonly string $type;
    public readonly array  $data;

    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
