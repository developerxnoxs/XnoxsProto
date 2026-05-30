<?php

namespace XnoxsProto\TL\Parser;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Types\FullMessage;
use XnoxsProto\TL\Types\User;
use XnoxsProto\TL\Types\Chat;

/**
 * Parse update constructors pushed by Telegram server.
 *
 * Returns normalized array with 'type' key. Supported types:
 *   new_message     — new incoming/outgoing message
 *   edit_message    — an existing message was edited
 *   delete_messages — one or more messages were deleted
 *   read_history    — messages were marked as read
 *   pinned_messages — messages were pinned/unpinned
 *   user_status     — user came online / went offline
 *   multi           — container with multiple updates
 */
class UpdateParser
{
    const UPDATE_SHORT_MESSAGE      = 0x313bc7f8;
    const UPDATE_SHORT_CHAT_MESSAGE = 0x4d6deea5;
    const UPDATES                   = 0x74ae4240;
    const UPDATE_SHORT              = 0x78d4dec1;
    const UPDATES_COMBINED          = 0xae0b0d43;
    const UPDATES_TOO_LONG          = 0x62d6b459;
    const NEW_SESSION_CREATED       = 0x9ec20908;

    const UPDATE_NEW_MESSAGE         = 0x1f2b0afd;
    const UPDATE_NEW_CHANNEL_MESSAGE = 0x62ba04d9;
    const UPDATE_EDIT_MESSAGE        = 0xe40370a3;
    const UPDATE_EDIT_CHANNEL_MSG    = 0x1b3f4686;

    const VECTOR_CTOR = 0x1cb5c415;

    const UPDATE_MESSAGE_ID              = 0x4e90bfd6;
    const UPDATE_READ_HISTORY_INBOX      = 0x9c974fdf;
    const UPDATE_READ_HISTORY_OUTBOX     = 0xb4f73de2;
    const UPDATE_DELETE_MESSAGES         = 0xa20db0e5;
    const UPDATE_READ_CHANNEL_INBOX      = 0x922e6e10;
    const UPDATE_READ_CHANNEL_OUTBOX     = 0x25d6c9c7;
    const UPDATE_DELETE_CHANNEL_MESSAGES = 0xc32d5b12;
    const UPDATE_EDIT_CHANNEL_MESSAGE    = 0x1b3f4686;
    const UPDATE_PINNED_MESSAGES         = 0xe9b35d34;
    const UPDATE_PINNED_CHANNEL_MESSAGES = 0x5bb98608;
    const UPDATE_USER_STATUS             = 0x1bfbd823;
    const UPDATE_USER_TYPING             = 0x5c486927;
    const UPDATE_CHAT_USER_TYPING        = 0x9a65ea7f;
    const UPDATE_CHANNEL                 = 0x635d6a41;
    const UPDATE_CHANNEL_TOO_LONG        = 0x108d941f;
    const UPDATE_CHANNEL_READ_MESSENGER  = 0x4214f37f;
    const UPDATE_NOTIFY_SETTINGS         = 0xbec268ef;
    const UPDATE_WEB_PAGE                = 0x7f891213;
    const UPDATE_DRAFT_MESSAGE           = 0xee2bb969;

    /**
     * Parse an update. $constructor has already been read from the stream.
     */
    public static function parse(int $constructor, BinaryReader $reader): ?array
    {
        switch ($constructor) {
            case self::UPDATE_SHORT_MESSAGE:
                return self::parseShortMessage($reader, false);
            case self::UPDATE_SHORT_CHAT_MESSAGE:
                return self::parseShortMessage($reader, true);
            case self::UPDATES:
            case self::UPDATES_COMBINED:
                return self::parseUpdates($reader, $constructor === self::UPDATES_COMBINED);
            case self::UPDATE_SHORT:
                return self::parseUpdateShort($reader);
            default:
                return null;
        }
    }

    /**
     * updateShortMessage / updateShortChatMessage
     */
    private static function parseShortMessage(BinaryReader $r, bool $isChat): ?array
    {
        $flags     = $r->readInt();
        $out       = (bool)($flags & (1 << 1));
        $id        = $r->readInt();

        $fromId = null;
        $chatId = null;
        $userId = null;

        if ($isChat) {
            $fromId = $r->readLong();
            $chatId = $r->readLong();
        } else {
            $userId = $r->readLong();
        }

        $text     = $r->readString();
        $pts      = $r->readInt();
        $ptsCount = $r->readInt();
        $date     = $r->readInt();

        if ($flags & (1 << 2))  TLSkipHelper::skipMessageFwdHeader($r);
        if ($flags & (1 << 11)) $r->readLong();
        if ($flags & (1 << 3))  TLSkipHelper::skipMessageReplyHeader($r);
        if ($flags & (1 << 7))  TLSkipHelper::skipVector($r, fn($x) => TLSkipHelper::skipMessageEntity($x));
        if ($flags & (1 << 25)) $r->readInt();

        $msg          = new FullMessage();
        $msg->id      = $id;
        $msg->date    = $date;
        $msg->text    = $text;
        $msg->out     = $out;
        $msg->type    = 'message';

        if ($isChat) {
            $msg->fromUserId = $fromId;
            $msg->peerType   = 'chat';
            $msg->peerId     = $chatId;
        } else {
            $msg->fromUserId = $out ? null : $userId;
            $msg->peerType   = 'user';
            $msg->peerId     = $userId;
        }

        return ['type' => 'new_message', 'message' => $msg, 'users' => [], 'chats' => []];
    }

    /**
     * updates#74ae4240 / updatesCombined#ae0b0d43
     */
    private static function parseUpdates(BinaryReader $r, bool $combined): ?array
    {
        $r->readInt(); // vector ctor
        $count   = $r->readInt();
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $ctor = $r->readInt();

            // --- New messages ---
            if ($ctor === self::UPDATE_NEW_MESSAGE || $ctor === self::UPDATE_NEW_CHANNEL_MESSAGE) {
                $msgCtor = $r->readInt();
                try {
                    $msg       = FullMessage::fromReader($r, $msgCtor);
                    $results[] = ['type' => 'new_message', 'message' => $msg];
                } catch (\Exception $e) {
                    break;
                }
                try { $r->readInt(); $r->readInt(); } catch (\Exception $e) {}

            // --- Edit messages ---
            } elseif ($ctor === self::UPDATE_EDIT_MESSAGE || $ctor === self::UPDATE_EDIT_CHANNEL_MSG) {
                $msgCtor = $r->readInt();
                try {
                    $msg       = FullMessage::fromReader($r, $msgCtor);
                    $results[] = ['type' => 'edit_message', 'message' => $msg];
                } catch (\Exception $e) { break; }
                try { $r->readInt(); $r->readInt(); } catch (\Exception $e) {}

            // --- Message ID mapping ---
            } elseif ($ctor === self::UPDATE_MESSAGE_ID) {
                try { $r->readInt(); $r->readLong(); } catch (\Exception $e) { break; }

            // --- Read history inbox ---
            } elseif ($ctor === self::UPDATE_READ_HISTORY_INBOX) {
                try {
                    $flags = $r->readInt();
                    $peer  = TLSkipHelper::readPeer($r);
                    if ($flags & 1) $r->readInt(); // folder_id
                    $maxId       = $r->readInt();
                    $stillUnread = $r->readInt();
                    $r->readInt(); $r->readInt(); // pts, pts_count
                    $results[] = [
                        'type'      => 'read_history',
                        'direction' => 'in',
                        'peer'      => $peer,
                        'max_id'    => $maxId,
                    ];
                } catch (\Exception $e) { break; }

            // --- Read history outbox ---
            } elseif ($ctor === self::UPDATE_READ_HISTORY_OUTBOX) {
                try {
                    $peer  = TLSkipHelper::readPeer($r);
                    $maxId = $r->readInt();
                    $r->readInt(); $r->readInt(); // pts, pts_count
                    $results[] = [
                        'type'      => 'read_history',
                        'direction' => 'out',
                        'peer'      => $peer,
                        'max_id'    => $maxId,
                    ];
                } catch (\Exception $e) { break; }

            // --- Delete messages ---
            } elseif ($ctor === self::UPDATE_DELETE_MESSAGES) {
                try {
                    $ids = TLSkipHelper::readVectorInt($r);
                    $r->readInt(); $r->readInt(); // pts, pts_count
                    $results[] = [
                        'type'       => 'delete_messages',
                        'ids'        => $ids,
                        'channel_id' => null,
                    ];
                } catch (\Exception $e) { break; }

            // --- Read channel inbox ---
            } elseif ($ctor === self::UPDATE_READ_CHANNEL_INBOX) {
                try {
                    $flags     = $r->readInt();
                    $channelId = $r->readLong();
                    if ($flags & 1) $r->readInt(); // folder_id
                    $maxId = $r->readInt();
                    $r->readInt(); // still_unread_count
                    $r->readInt(); // pts
                    $results[] = [
                        'type'       => 'read_history',
                        'direction'  => 'in',
                        'channel_id' => $channelId,
                        'max_id'     => $maxId,
                    ];
                } catch (\Exception $e) { break; }

            // --- Read channel outbox ---
            } elseif ($ctor === self::UPDATE_READ_CHANNEL_OUTBOX) {
                try {
                    $channelId = $r->readLong();
                    $maxId     = $r->readInt();
                    $results[] = [
                        'type'       => 'read_history',
                        'direction'  => 'out',
                        'channel_id' => $channelId,
                        'max_id'     => $maxId,
                    ];
                } catch (\Exception $e) { break; }

            // --- Delete channel messages ---
            } elseif ($ctor === self::UPDATE_DELETE_CHANNEL_MESSAGES) {
                try {
                    $channelId = $r->readLong();
                    $ids       = TLSkipHelper::readVectorInt($r);
                    $r->readInt(); $r->readInt(); // pts, pts_count
                    $results[] = [
                        'type'       => 'delete_messages',
                        'ids'        => $ids,
                        'channel_id' => $channelId,
                    ];
                } catch (\Exception $e) { break; }

            // --- Pinned messages (user/group) ---
            } elseif ($ctor === self::UPDATE_PINNED_MESSAGES) {
                try {
                    $flags  = $r->readInt();
                    $pinned = (bool)($flags & 1);
                    $peer   = TLSkipHelper::readPeer($r);
                    $ids    = TLSkipHelper::readVectorInt($r);
                    $r->readInt(); $r->readInt(); // pts, pts_count
                    $results[] = [
                        'type'   => 'pinned_messages',
                        'pinned' => $pinned,
                        'peer'   => $peer,
                        'ids'    => $ids,
                    ];
                } catch (\Exception $e) { break; }

            // --- Pinned channel messages ---
            } elseif ($ctor === self::UPDATE_PINNED_CHANNEL_MESSAGES) {
                try {
                    $flags     = $r->readInt();
                    $pinned    = (bool)($flags & 1);
                    $channelId = $r->readLong();
                    $ids       = TLSkipHelper::readVectorInt($r);
                    $r->readInt(); $r->readInt(); // pts, pts_count
                    $results[] = [
                        'type'       => 'pinned_messages',
                        'pinned'     => $pinned,
                        'channel_id' => $channelId,
                        'ids'        => $ids,
                    ];
                } catch (\Exception $e) { break; }

            // --- User status ---
            } elseif ($ctor === self::UPDATE_USER_STATUS) {
                try {
                    $userId     = $r->readLong();
                    $statusCtor = $r->readInt();
                    $online     = false;
                    $wasOnline  = 0;
                    if ($statusCtor === 0xe26f42f1 || $statusCtor === 0xedb93949) {
                        $r->readInt(); // expires
                        $online = true;
                    } elseif ($statusCtor === 0x008c703f) {
                        $wasOnline = $r->readInt();
                    }
                    // recently / last week / last month may have a flags int
                    $results[] = [
                        'type'       => 'user_status',
                        'user_id'    => $userId,
                        'online'     => $online,
                        'was_online' => $wasOnline,
                    ];
                } catch (\Exception $e) { break; }

            // --- Typing indicators (skip) ---
            } elseif ($ctor === self::UPDATE_USER_TYPING) {
                try {
                    $r->readLong(); // user_id
                    $r->readInt();  // action ctor
                } catch (\Exception $e) { break; }

            } elseif ($ctor === self::UPDATE_CHAT_USER_TYPING) {
                try {
                    $r->readLong(); // chat_id or channel_id
                    $r->readLong(); // user_id
                    $r->readInt();  // action ctor
                } catch (\Exception $e) { break; }

            // --- Single-field channel updates (skip) ---
            } elseif (in_array($ctor, [
                self::UPDATE_CHANNEL,
                self::UPDATE_CHANNEL_TOO_LONG,
                self::UPDATE_CHANNEL_READ_MESSENGER,
            ], true)) {
                try { $r->readLong(); } catch (\Exception $e) { break; }

            // --- Variable-length updates we cannot safely skip ---
            } elseif (in_array($ctor, [
                self::UPDATE_NOTIFY_SETTINGS,
                self::UPDATE_WEB_PAGE,
                self::UPDATE_DRAFT_MESSAGE,
            ], true)) {
                break; // Stop parsing — rest of stream may be valid

            } else {
                // Truly unknown update — stop parsing
                break;
            }
        }

        // users:Vector<User>
        $users = [];
        try {
            $r->readInt(); // vector ctor
            $uCount = $r->readInt();
            for ($i = 0; $i < $uCount; $i++) {
                $uCtor = $r->readInt();
                if ($uCtor === User::CONSTRUCTOR_EMPTY) { $r->readLong(); continue; }
                try { $u = User::fromReader($r); $users[$u->id] = $u; } catch (\Exception $e) { break; }
            }
        } catch (\Exception $e) {}

        // chats:Vector<Chat>
        $chats = [];
        try {
            $r->readInt(); // vector ctor
            $cCount = $r->readInt();
            for ($i = 0; $i < $cCount; $i++) {
                $cCtor = $r->readInt();
                try { $c = Chat::fromReader($r, $cCtor); $chats[$c->id] = $c; } catch (\Exception $e) { break; }
            }
        } catch (\Exception $e) {}

        if (empty($results)) return null;

        // Attach users/chats context to each result
        foreach ($results as &$res) {
            if (!isset($res['users'])) $res['users'] = $users;
            if (!isset($res['chats'])) $res['chats'] = $chats;
        }

        if (count($results) === 1) return $results[0];

        return ['type' => 'multi', 'updates' => $results];
    }

    /**
     * updateShort#11f1331c  update:Update  date:int
     */
    private static function parseUpdateShort(BinaryReader $r): ?array
    {
        $ctor   = $r->readInt();
        $result = null;

        if ($ctor === self::UPDATE_NEW_MESSAGE || $ctor === self::UPDATE_NEW_CHANNEL_MESSAGE) {
            $msgCtor = $r->readInt();
            try {
                $msg    = FullMessage::fromReader($r, $msgCtor);
                try { $r->readInt(); $r->readInt(); } catch (\Exception $e) {}
                $result = ['type' => 'new_message', 'message' => $msg, 'users' => [], 'chats' => []];
            } catch (\Exception $e) {}

        } elseif ($ctor === self::UPDATE_EDIT_MESSAGE || $ctor === self::UPDATE_EDIT_CHANNEL_MSG) {
            $msgCtor = $r->readInt();
            try {
                $msg    = FullMessage::fromReader($r, $msgCtor);
                try { $r->readInt(); $r->readInt(); } catch (\Exception $e) {}
                $result = ['type' => 'edit_message', 'message' => $msg, 'users' => [], 'chats' => []];
            } catch (\Exception $e) {}

        } elseif ($ctor === self::UPDATE_USER_STATUS) {
            try {
                $userId     = $r->readLong();
                $statusCtor = $r->readInt();
                $online     = ($statusCtor === 0xe26f42f1 || $statusCtor === 0xedb93949);
                $wasOnline  = 0;
                if ($online) {
                    $r->readInt(); // expires
                } elseif ($statusCtor === 0x008c703f) {
                    $wasOnline = $r->readInt();
                }
                $result = [
                    'type'       => 'user_status',
                    'user_id'    => $userId,
                    'online'     => $online,
                    'was_online' => $wasOnline,
                    'users'      => [],
                    'chats'      => [],
                ];
            } catch (\Exception $e) {}

        } elseif ($ctor === self::UPDATE_DELETE_MESSAGES) {
            try {
                $ids    = TLSkipHelper::readVectorInt($r);
                $r->readInt(); $r->readInt(); // pts, pts_count
                $result = ['type' => 'delete_messages', 'ids' => $ids, 'channel_id' => null, 'users' => [], 'chats' => []];
            } catch (\Exception $e) {}
        }

        try { $r->readInt(); } catch (\Exception $e) {} // date

        return $result;
    }

    public static function isUpdateConstructor(int $ctor): bool
    {
        return in_array($ctor, [
            self::UPDATE_SHORT_MESSAGE,
            self::UPDATE_SHORT_CHAT_MESSAGE,
            self::UPDATES,
            self::UPDATES_COMBINED,
            self::UPDATE_SHORT,
            self::UPDATES_TOO_LONG,
            self::NEW_SESSION_CREATED,
        ], true);
    }
}
