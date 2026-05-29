<?php

namespace XnoxsProto\Events;

use XnoxsProto\TL\Types\FullMessage;
use XnoxsProto\TL\Types\User;
use XnoxsProto\TL\Types\Chat;

/**
 * Event object passed to NewMessage handlers.
 *
 * Equivalent to Telethon's events.NewMessage.Event
 *
 * Properties (all Telethon equivalents):
 *   $event->rawText                   → msg text (event.raw_text)
 *   $event->message                   → FullMessage object (event.message)
 *   $event->originalUpdate            → raw update array (event.original_update)
 *   $event->isIncoming / $event->isOutgoing
 *
 * Access reply markup (PHP equivalent of event.original_update.message.reply_markup):
 *   $event->message->replyMarkup['rows'][0][0]['url']
 *   $event->originalUpdate['message']['reply_markup']['rows'][0][0]['url']
 *
 * Click a button (PHP equivalent of await message.click(0, 0)):
 *   $event->message->click(0, 0)
 */
class NewMessageEvent
{
    /** Raw message text */
    public readonly string $rawText;

    /** Full message object with click() support */
    public readonly FullMessage $message;

    /** Raw parsed update array */
    public readonly array $originalUpdate;

    /** Whether message was sent by us */
    public readonly bool $isOutgoing;

    /** Whether message is from someone else */
    public readonly bool $isIncoming;

    /** Users present in this update */
    public readonly array $users;

    /** Chats present in this update */
    public readonly array $chats;

    public function __construct(array $update)
    {
        /** @var FullMessage $msg */
        $msg = $update['message'];

        $this->message    = $msg;
        $this->rawText    = $msg->text;
        $this->isOutgoing = $msg->out;
        $this->isIncoming = !$msg->out;
        $this->users      = $update['users'] ?? [];
        $this->chats      = $update['chats'] ?? [];

        // Build originalUpdate array (PHP equivalent of Python's original_update object)
        $this->originalUpdate = [
            'type'    => $update['type'],
            'message' => [
                'id'           => $msg->id,
                'date'         => $msg->date,
                'message'      => $msg->text,
                'out'          => $msg->out,
                'peer_type'    => $msg->peerType,
                'peer_id'      => $msg->peerId,
                'from_user_id' => $msg->fromUserId,
                'reply_markup' => $msg->replyMarkup,
            ],
        ];
    }

    /**
     * Convenience: get sender User object if present in update.
     */
    public function getSender(): ?User
    {
        if ($this->message->fromUserId === null) return null;
        return $this->users[$this->message->fromUserId] ?? null;
    }

    /**
     * Convenience: get Chat object if message is from a group.
     */
    public function getChat(): ?Chat
    {
        if ($this->message->peerType !== 'chat' && $this->message->peerType !== 'channel') return null;
        return $this->chats[$this->message->peerId] ?? null;
    }
}
