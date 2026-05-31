<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\MessagesSendMessageRequest;
use XnoxsProto\TL\Functions\MessagesSendMediaRequest;
use XnoxsProto\TL\Functions\MessagesSendReactionRequest;
use XnoxsProto\TL\Functions\ContactsGetContactsRequest;
use XnoxsProto\TL\Functions\MessagesGetDialogsRequest;
use XnoxsProto\TL\Functions\MessagesGetChatsRequest;
use XnoxsProto\TL\Functions\UsersGetUsersRequest;
use XnoxsProto\TL\Functions\ContactsResolveUsernameRequest;
use XnoxsProto\TL\Functions\MessagesGetHistoryRequest;
use XnoxsProto\TL\Functions\MessagesSearchRequest;
use XnoxsProto\TL\Functions\MessagesSearchGlobalRequest;
use XnoxsProto\TL\Types\InputPeer;
use XnoxsProto\TL\Types\InputMedia;
use XnoxsProto\TL\Types\InputMediaPoll;
use XnoxsProto\TL\Types\UpdateShortSentMessage;
use XnoxsProto\TL\Types\User;
use XnoxsProto\TL\Types\Dialog;
use XnoxsProto\TL\Types\Chat;
use XnoxsProto\TL\Types\MessageInfo;
use XnoxsProto\TL\Types\FullMessage;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;
use XnoxsProto\Upload\FileUploader;

class Messages
{
    private TelegramClient $client;

    // Constructor IDs
    const CONTACTS_CONTACTS          = 0xeae87e42;
    const CONTACTS_CONTACTS_NOT_MOD  = 0xb74ba9d2;
    const BOOL_TRUE                  = 0x997275b5;
    const CONTACT_CONSTRUCTOR        = 0x145ade0b;
    const USER_EMPTY_CONSTRUCTOR     = 0xd3bc4b7a;

    // messages.Dialogs constructor IDs
    const DIALOGS_CONSTRUCTOR        = 0x15ba6c40; // messages.dialogs
    const DIALOGS_SLICE_CONSTRUCTOR  = 0x71e094f3; // messages.dialogsSlice
    const DIALOGS_NOT_MODIFIED       = 0x0f0e3517; // messages.dialogsNotModified

    // messages.Chats constructor IDs (response dari messages.getChats)
    const MESSAGES_CHATS             = 0x64ff9fd5; // messages.chats#64ff9fd5
    const MESSAGES_CHATS_SLICE       = 0x9cd81144; // messages.chatsSlice#9cd81144

    // Vector constructor
    const VECTOR_CONSTRUCTOR         = 0x1cb5c415;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    // -----------------------------------------------------------------------
    // messages.getDialogs
    // -----------------------------------------------------------------------

    /**
     * Ambil daftar semua dialog: DM, group, dan channel.
     *
     * Return array of:
     *   [
     *     'type'         => 'user'|'chat'|'channel',
     *     'id'           => int,
     *     'access_hash'  => int|null,
     *     'title'        => string,           // nama user/grup/channel
     *     'username'     => string|null,
     *     'unread'       => int,
     *     'pinned'       => bool,
     *     'top_message'  => string,           // preview pesan terakhir
     *     'top_date'     => int,              // unix timestamp pesan terakhir
     *     'top_out'      => bool,             // apakah pesan terakhir kita kirim
     *     'is_channel'   => bool,             // broadcast channel (bukan supergroup)
     *     'is_supergroup'=> bool,
     *   ]
     *
     * @param int $limit Maks jumlah dialog (default 100)
     * @param bool $allPages Ambil semua halaman sampai habis (default false)
     */
    public function getDialogs(int $limit = 100, bool $allPages = false): array
    {
        $this->assertReady();

        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $results       = [];
        $offsetDate    = 0;
        $offsetId      = 0;
        $offsetPeer    = InputPeer::empty();
        $fetched       = 0;
        $totalCount    = null;

        do {
            $request = new MessagesGetDialogsRequest(
                min($limit, 100),
                $offsetDate,
                $offsetId,
                $offsetPeer
            );
            $request = $this->client->wrapFirstRequest($request);

            try {
                $response = $sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                throw new \RuntimeException(
                    sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                    $e->errorCode, $e
                );
            }

            $c      = $response['constructor'];
            $reader = $response['reader'];

            if ($c === self::DIALOGS_NOT_MODIFIED) {
                // dialogsNotModified — count:int
                $reader->readInt();
                break;
            }

            $batch = $this->parseDialogsResponse($c, $reader);

            if ($batch['total_count'] !== null && $totalCount === null) {
                $totalCount = $batch['total_count'];
            }

            $results = array_merge($results, $batch['dialogs']);
            $fetched += count($batch['dialogs']);

            // Kondisi berhenti
            $done = !$allPages
                || count($batch['dialogs']) === 0
                || ($totalCount !== null && $fetched >= $totalCount)
                || $fetched >= $limit;

            if (!$done && !empty($batch['dialogs'])) {
                // Set offset dari dialog terakhir
                $last = end($batch['dialogs']);
                $offsetDate = $last['top_date'];
                $offsetId   = $last['top_message_id'] ?? 0;
                if ($last['type'] === 'user' && $last['access_hash'] !== null) {
                    $offsetPeer = InputPeer::user($last['id'], $last['access_hash']);
                } elseif ($last['type'] === 'chat') {
                    $offsetPeer = InputPeer::chat($last['id']);
                } elseif ($last['type'] === 'channel' && $last['access_hash'] !== null) {
                    $offsetPeer = InputPeer::channel($last['id'], $last['access_hash']);
                } else {
                    $done = true;
                }
            }

        } while (!$done);

        return $results;
    }

    // -----------------------------------------------------------------------
    // Response parsing
    // -----------------------------------------------------------------------

    /**
     * Parse messages.dialogs#15ba6c40 atau messages.dialogsSlice#71e094f3.
     *
     * Struktur:
     *   [slice] count:int          — hanya pada dialogsSlice
     *   dialogs:   Vector<Dialog>
     *   messages:  Vector<Message>
     *   chats:     Vector<Chat>
     *   users:     Vector<User>
     */
    private function parseDialogsResponse(int $constructor, BinaryReader $reader): array
    {
        $totalCount = null;

        if ($constructor === self::DIALOGS_SLICE_CONSTRUCTOR) {
            $totalCount = $reader->readInt(); // count:int hanya di dialogsSlice
        }

        // --- 1. Vector<Dialog> ---
        $rawDialogs   = [];
        $dialogsOk    = true;
        try {
            $reader->readInt(); // vector constructor
            $dialogCount = $reader->readInt();
            for ($i = 0; $i < $dialogCount; $i++) {
                $ctor = $reader->readInt();
                if ($ctor !== Dialog::CONSTRUCTOR && $ctor !== Dialog::CONSTRUCTOR_FOLDER) {
                    // Unknown dialog constructor — stream di posisi yang tidak diketahui
                    $dialogsOk = false;
                    break;
                }
                try {
                    $rawDialogs[] = Dialog::fromReader($reader, $ctor);
                } catch (\Throwable $e) {
                    $dialogsOk = false;
                    break;
                }
            }
        } catch (\Throwable $e) {
            $dialogsOk = false;
        }

        // --- 2. Vector<Message> — index by message id ---
        // Jika ada pesan yang tidak dikenali, stream bisa rusak — set $dialogsOk = false.
        $messages = [];
        if ($dialogsOk) {
            try {
                $reader->readInt(); // vector constructor
                $msgCount = $reader->readInt();
                for ($i = 0; $i < $msgCount; $i++) {
                    $ctor = $reader->readInt();
                    try {
                        $msg = MessageInfo::fromReader($reader, $ctor);
                        $messages[$msg->id] = $msg;
                    } catch (\Throwable $e) {
                        // Unknown constructor in message — stream is now corrupted.
                        // Stop parsing entirely so chats/users are not read from wrong position.
                        $dialogsOk = false;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $dialogsOk = false;
            }
        }

        // --- 3. Vector<Chat> — index by id ---
        $chats = [];
        if ($dialogsOk) {
            try {
                $reader->readInt(); // vector constructor
                $chatCount = $reader->readInt();
                for ($i = 0; $i < $chatCount; $i++) {
                    $ctor = $reader->readInt();
                    try {
                        $chat = Chat::fromReader($reader, $ctor);
                        $chats[$chat->id] = $chat;
                    } catch (\Throwable $e) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Stream rusak — lanjut dengan chats kosong
            }
        }

        // --- 4. Vector<User> — index by id ---
        $users = [];
        if ($dialogsOk) {
            try {
                $reader->readInt(); // vector constructor
                $userCount = $reader->readInt();
                for ($i = 0; $i < $userCount; $i++) {
                    $ctor = $reader->readInt();
                    if ($ctor === User::CONSTRUCTOR_EMPTY) {
                        $reader->readLong(); // id only
                        continue;
                    }
                    try {
                        $user = User::fromReader($reader);
                        $users[$user->id] = $user;
                    } catch (\Throwable $e) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Stream rusak — lanjut dengan users kosong
            }
        }

        // --- 5. Cache parsed entities ke session (Telethon: _mb_entity_cache.extend) ---
        // Simpan untuk fallback min-user & access_hash recovery di session berikutnya
        $toCache = [];
        foreach ($users as $u) {
            if (!$u->id) continue;
            $row = ['id' => $u->id, 'type' => 'user', 'bot' => $u->bot];
            if ($u->accessHash !== null) $row['access_hash'] = $u->accessHash;
            if ($u->firstName  !== null) $row['first_name']  = $u->firstName;
            if ($u->lastName   !== null) $row['last_name']   = $u->lastName;
            if ($u->username   !== null) $row['username']    = $u->username;
            if ($u->phone      !== null) $row['phone']       = $u->phone;
            // Hanya cache jika bukan pure-min (min tanpa access_hash tidak berguna untuk resolving)
            if (!$u->min || isset($row['access_hash'])) {
                $toCache[] = $row;
            }
        }
        foreach ($chats as $c) {
            if (!$c->id || $c->type === 'empty' || $c->type === 'unknown') continue;
            $row = [
                'id'            => $c->id,
                'type'          => $c->type,
                'title'         => $c->title ?? '',
                'is_channel'    => $c->isChannel(),
                'is_supergroup' => $c->isSupergroup(),
            ];
            if ($c->accessHash !== null)   $row['access_hash']        = $c->accessHash;
            if ($c->username   !== null)   $row['username']           = $c->username;
            if ($c->participantsCount > 0) $row['participants_count'] = $c->participantsCount;
            $toCache[] = $row;
        }
        if ($toCache) {
            $this->client->getSession()->processEntities($toCache);
        }

        // --- 6. Gabungkan semua data ---
        $dialogs = [];
        foreach ($rawDialogs as $d) {
            if ($d->peerType === 'folder') continue; // skip arsip folder

            $entry = $this->buildDialogEntry($d, $users, $chats, $messages);
            if ($entry !== null) {
                $dialogs[] = $entry;
            }
        }

        return [
            'dialogs'     => $dialogs,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Buat array entry dialog dari data yang sudah diparsed.
     */
    private function buildDialogEntry(
        Dialog $d,
        array  $users,
        array  $chats,
        array  $messages
    ): ?array {
        $topMsg  = $messages[$d->topMessage] ?? null;
        $topText = $topMsg ? $topMsg->getPreview() : '';
        $topDate = $topMsg ? $topMsg->date : 0;
        $topOut  = $topMsg ? $topMsg->out : false;
        $session = $this->client->getSession();

        switch ($d->peerType) {
            case 'user':
                $user   = $users[$d->peerId] ?? null;
                // Fallback ke session cache untuk min-user (tidak punya nama) atau saat parse gagal
                $cached = null;
                $displayName = $user ? $user->getDisplayName() : null;
                if ($displayName === null || $displayName === 'User#' . $d->peerId) {
                    $cached = $session->getEntityRowsById($d->peerId);
                    if ($cached) {
                        $fn = $cached['first_name'] ?? null;
                        $ln = $cached['last_name']  ?? null;
                        $un = $cached['username']   ?? null;
                        $fromCache = trim(($fn ?? '') . ' ' . ($ln ?? ''));
                        $displayName = $fromCache !== '' ? $fromCache
                            : ($un ? '@' . $un : 'User#' . $d->peerId);
                    } else {
                        $displayName = $displayName ?? 'User#' . $d->peerId;
                    }
                }
                return [
                    'type'           => 'user',
                    'id'             => $d->peerId,
                    'access_hash'    => $user?->accessHash    ?? ($cached['access_hash'] ?? null),
                    'title'          => $displayName,
                    'username'       => $user?->username       ?? ($cached['username']    ?? null),
                    'phone'          => $user?->phone           ?? ($cached['phone']       ?? null),
                    'bot'            => $user?->bot             ?? ($cached['bot']         ?? false),
                    'unread'         => $d->unreadCount,
                    'pinned'         => $d->pinned,
                    'top_message'    => $topText,
                    'top_message_id' => $d->topMessage,
                    'top_date'       => $topDate,
                    'top_out'        => $topOut,
                    'is_channel'     => false,
                    'is_supergroup'  => false,
                ];

            case 'chat':
                $chat   = $chats[$d->peerId] ?? null;
                $cached = ($chat === null) ? $session->getEntityRowsById($d->peerId) : null;
                return [
                    'type'           => 'chat',
                    'id'             => $d->peerId,
                    'access_hash'    => null,
                    'title'          => $chat ? $chat->getDisplayName() : ($cached['title'] ?? 'Chat#' . $d->peerId),
                    'username'       => $chat?->username ?? ($cached['username'] ?? null),
                    'members'        => ($chat && $chat->participantsCount > 0)
                                        ? $chat->participantsCount
                                        : ($cached['participants_count'] ?? 0),
                    'unread'         => $d->unreadCount,
                    'pinned'         => $d->pinned,
                    'top_message'    => $topText,
                    'top_message_id' => $d->topMessage,
                    'top_date'       => $topDate,
                    'top_out'        => $topOut,
                    'is_channel'     => false,
                    'is_supergroup'  => false,
                    'creator'        => $chat?->creator ?? false,
                ];

            case 'channel':
                $channel = $chats[$d->peerId] ?? null;
                // Fallback ke session cache saat channel min (access_hash null) atau parse gagal
                $cached  = ($channel === null || $channel->accessHash === null)
                    ? $session->getEntityRowsById($d->peerId)
                    : null;
                return [
                    'type'           => 'channel',
                    'id'             => $d->peerId,
                    'access_hash'    => $channel?->accessHash    ?? ($cached['access_hash']    ?? null),
                    'title'          => $channel ? $channel->getDisplayName() : ($cached['title'] ?? 'Channel#' . $d->peerId),
                    'username'       => $channel?->username       ?? ($cached['username']       ?? null),
                    'members'        => ($channel && $channel->participantsCount > 0)
                                        ? $channel->participantsCount
                                        : ($cached['participants_count'] ?? 0),
                    'unread'         => $d->unreadCount,
                    'pinned'         => $d->pinned,
                    'top_message'    => $topText,
                    'top_message_id' => $d->topMessage,
                    'top_date'       => $topDate,
                    'top_out'        => $topOut,
                    'is_channel'     => $channel ? $channel->isChannel()    : ($cached['is_channel']    ?? false),
                    'is_supergroup'  => $channel ? $channel->isSupergroup() : ($cached['is_supergroup'] ?? false),
                    'creator'        => $channel?->creator ?? false,
                ];

            default:
                return null;
        }
    }

    // -----------------------------------------------------------------------
    // users.getUsers — batch fetch user info untuk min-users
    // -----------------------------------------------------------------------

    /**
     * Batch-fetch full user info menggunakan users.getUsers.
     *
     * Untuk min-user tanpa access_hash: kirim access_hash=0.
     * Server akan mengembalikan full User jika mereka adalah kontak.
     * Non-kontak/non-valid akan dikembalikan sebagai userEmpty dan diabaikan.
     *
     * @param array<array{id:int, access_hash:int|null}> $users
     * @return array<int, User>  Map user_id → User object
     */
    public function batchFetchUsers(array $users): array
    {
        if (empty($users)) return [];

        $this->assertReady();
        $sender = $this->client->getSender();
        if (!$sender) return [];

        // Batasi maks 200 user per call untuk menghindari FLOOD
        $users = array_slice($users, 0, 200);

        $request = new UsersGetUsersRequest(array_map(fn($u) => [
            'id'          => $u['id'],
            'access_hash' => $u['access_hash'] ?? 0,
        ], $users));
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\Throwable $e) {
            return [];
        }

        $ctor   = $response['constructor'];
        $reader = $response['reader'];

        // Response: Vector<User>  (ctor = 0x1cb5c415)
        if ($ctor !== self::VECTOR_CONSTRUCTOR) return [];

        $count  = $reader->readInt();
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $uCtor = $reader->readInt();
            if ($uCtor === User::CONSTRUCTOR_EMPTY) {
                $reader->readLong(); // id only
                continue;
            }
            try {
                $user = User::fromReader($reader);
                if ($user->id) {
                    $result[$user->id] = $user;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // messages.getChats — enrich Chat#ID entries (basic groups, tanpa access_hash)
    // -----------------------------------------------------------------------

    /**
     * Ambil info dasar (title, username) untuk daftar grup biasa berdasarkan ID.
     * Menggunakan messages.getChats#49e9528f — TIDAK memerlukan access_hash.
     * Cocok untuk memperbaiki tampilan "Chat#ID" setelah getDialogs parsing gagal.
     *
     * @param  int[] $ids  Daftar chat_id (basic group — bukan channel/supergroup)
     * @return Chat[]      Array Chat terindeks oleh id
     */
    public function fetchChatsByIds(array $ids): array
    {
        if (empty($ids)) return [];

        $this->assertReady();
        $sender = $this->client->getSender();
        if (!$sender) return [];

        $ids = array_values(array_unique(array_map('intval', $ids)));

        $request = new MessagesGetChatsRequest($ids);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\Throwable) {
            return [];
        }

        $ctor   = $response['constructor'];
        $reader = $response['reader'];

        if ($ctor !== self::MESSAGES_CHATS && $ctor !== self::MESSAGES_CHATS_SLICE) {
            return [];
        }

        // chatsSlice mempunyai count:int ekstra di depan
        if ($ctor === self::MESSAGES_CHATS_SLICE) {
            $reader->readInt(); // count:int
        }

        // Vector<Chat>
        $reader->readInt();         // 0x1cb5c415
        $count  = $reader->readInt();
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $chatCtor = $reader->readInt();
            try {
                $chat = Chat::fromReader($reader, $chatCtor);
                if ($chat->id && $chat->type !== 'unknown' && $chat->type !== 'empty') {
                    $result[$chat->id] = $chat;
                }
            } catch (\Throwable) {
                break; // stream rusak — hentikan
            }
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // contacts.getContacts
    // -----------------------------------------------------------------------

    /**
     * Ambil daftar kontak beserta access_hash masing-masing user.
     *
     * Return array of:
     *   [ 'id', 'access_hash', 'first_name', 'last_name', 'username', 'phone', 'mutual' ]
     */
    public function getContacts(): array
    {
        $this->assertReady();

        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new ContactsGetContactsRequest(0);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $c      = $response['constructor'];
        $reader = $response['reader'];

        if ($c === self::CONTACTS_CONTACTS_NOT_MOD) {
            return [];
        }

        if ($c !== self::CONTACTS_CONTACTS) {
            throw new \RuntimeException(sprintf('Unexpected contacts response: 0x%08x', $c));
        }

        return $this->parseContactsResponse($reader);
    }

    /**
     * Parse contacts.contacts#eae87e42:
     *   contacts  Vector<Contact>
     *   saved_count int
     *   users     Vector<User>
     */
    private function parseContactsResponse(BinaryReader $reader): array
    {
        // --- Vector<Contact> ---
        $contacts = [];
        $reader->readInt(); // vector constructor 0x1cb5c415
        $contactCount = $reader->readInt();
        for ($i = 0; $i < $contactCount; $i++) {
            $reader->readInt(); // contact constructor 0x145ade0b
            $userId = $reader->readLong();
            $mutual = ($reader->readInt() === self::BOOL_TRUE);
            $contacts[$userId] = $mutual;
        }

        $reader->readInt(); // saved_count:int

        // --- Vector<User> ---
        $users = [];
        $reader->readInt(); // vector constructor 0x1cb5c415
        $userCount = $reader->readInt();
        for ($i = 0; $i < $userCount; $i++) {
            $ctor = $reader->readInt();
            if ($ctor === self::USER_EMPTY_CONSTRUCTOR) {
                $reader->readLong(); // id only
                continue;
            }
            $user = User::fromReader($reader);
            $users[$user->id] = $user;
        }

        // --- Gabungkan contacts + users ---
        $result = [];
        foreach ($contacts as $userId => $mutual) {
            if (!isset($users[$userId])) continue;
            $u = $users[$userId];
            $result[] = [
                'id'          => $u->id,
                'access_hash' => $u->accessHash ?? 0,
                'first_name'  => $u->firstName ?? '',
                'last_name'   => $u->lastName  ?? '',
                'username'    => $u->username,
                'phone'       => $u->phone,
                'mutual'      => $mutual,
                'bot'         => $u->bot,
                'display'     => $u->getDisplayName(),
            ];
        }

        usort($result, fn($a, $b) => strcmp($a['display'], $b['display']));

        return $result;
    }

    // -----------------------------------------------------------------------
    // messages.sendMessage
    // -----------------------------------------------------------------------

    public function sendMessageToSelf(string $text): array
    {
        return $this->sendMessage(InputPeer::self(), $text);
    }

    public function sendMessageToUser(int $userId, int $accessHash, string $text): array
    {
        return $this->sendMessage(InputPeer::user($userId, $accessHash), $text);
    }

    public function sendMessageToChat(int $chatId, string $text): array
    {
        return $this->sendMessage(InputPeer::chat($chatId), $text);
    }

    public function sendMessageToChannel(int $channelId, int $accessHash, string $text): array
    {
        return $this->sendMessage(InputPeer::channel($channelId, $accessHash), $text);
    }

    /**
     * Low-level: kirim pesan ke InputPeer apa pun.
     */
    public function sendMessage(InputPeer $peer, string $text, ?int $replyToMsgId = null): array
    {
        $this->assertReady();

        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new MessagesSendMessageRequest($peer, $text, $replyToMsgId);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
            return $this->parseSendResponse($response, $text);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode,
                $e
            );
        }
    }

    // -----------------------------------------------------------------------
    // Response parsing — sendMessage
    // -----------------------------------------------------------------------

    private function parseSendResponse(array $response, string $sentText): array
    {
        $constructor = $response['constructor'];
        $reader      = $response['reader'];

        if ($constructor === UpdateShortSentMessage::CONSTRUCTOR_ID) {
            $update = UpdateShortSentMessage::fromReader($reader);
            return [
                'sent'       => true,
                'message_id' => $update->id,
                'date'       => $update->date,
                'pts'        => $update->pts,
                'text'       => $sentText,
            ];
        }

        if ($constructor === 0x74ae4240) {
            $messageId = $this->extractMessageIdFromUpdates($reader);
            return [
                'sent'       => true,
                'message_id' => $messageId,
                'date'       => time(),
                'text'       => $sentText,
            ];
        }

        if ($constructor === 0x78d4dec1) {
            return ['sent' => true, 'message_id' => null, 'date' => time(), 'text' => $sentText];
        }

        return [
            'sent'        => true,
            'message_id'  => null,
            'date'        => time(),
            'text'        => $sentText,
            'constructor' => sprintf('0x%08x', $constructor),
        ];
    }

    private function extractMessageIdFromUpdates(BinaryReader $reader): ?int
    {
        try {
            $vectorCtor = $reader->readInt();
            if ($vectorCtor !== self::VECTOR_CONSTRUCTOR) return null;

            $count = $reader->readInt();
            for ($i = 0; $i < $count; $i++) {
                $updateCtor = $reader->readInt();
                if ($updateCtor === 0x4e90bfd6) {
                    return $reader->readInt();
                }
                return null;
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // contacts.resolveUsername
    // -----------------------------------------------------------------------

    /**
     * Resolve @username ke peer info: id, access_hash, title, type.
     *
     * @param string $username Username tanpa '@'
     * @return array ['type'=>'user'|'chat'|'channel', 'id'=>int, 'access_hash'=>int|null,
     *                'title'=>string, 'username'=>string|null, 'bot'=>bool]
     */
    public function resolveUsername(string $username): array
    {
        $this->assertReady();
        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new ContactsResolveUsernameRequest($username);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e
            );
        }

        $ctor   = $response['constructor'];
        $reader = $response['reader'];

        // contacts.resolvedPeer#7f077ad9  peer:Peer  chats:Vector<Chat>  users:Vector<User>
        if ($ctor !== 0x7f077ad9) {
            throw new \RuntimeException(sprintf('Unexpected resolveUsername response: 0x%08x', $ctor));
        }

        // 1. peer:Peer
        $peer = TLSkipHelper::readPeer($reader);

        // 2. chats:Vector<Chat>
        $chats = [];
        $reader->readInt(); // vector ctor
        $chatCount = $reader->readInt();
        for ($i = 0; $i < $chatCount; $i++) {
            $ctor2 = $reader->readInt();
            try {
                $chat = Chat::fromReader($reader, $ctor2);
                $chats[$chat->id] = $chat;
            } catch (\Exception $e) { break; }
        }

        // 3. users:Vector<User>
        $users = [];
        $reader->readInt(); // vector ctor
        $userCount = $reader->readInt();
        for ($i = 0; $i < $userCount; $i++) {
            $ctor2 = $reader->readInt();
            if ($ctor2 === User::CONSTRUCTOR_EMPTY) { $reader->readLong(); continue; }
            try {
                $user = User::fromReader($reader);
                $users[$user->id] = $user;
            } catch (\Exception $e) { break; }
        }

        // Gabungkan peer + entity
        $id   = $peer['id'];
        $type = $peer['type'];

        switch ($type) {
            case 'user':
                $u = $users[$id] ?? null;
                return [
                    'type'        => 'user',
                    'id'          => $id,
                    'access_hash' => $u?->accessHash,
                    'title'       => $u ? $u->getDisplayName() : 'User#' . $id,
                    'username'    => $u?->username,
                    'bot'         => $u?->bot ?? false,
                ];
            case 'chat':
                $c = $chats[$id] ?? null;
                return [
                    'type'        => 'chat',
                    'id'          => $id,
                    'access_hash' => null,
                    'title'       => $c ? $c->getDisplayName() : 'Chat#' . $id,
                    'username'    => $c?->username,
                    'bot'         => false,
                ];
            case 'channel':
                $c = $chats[$id] ?? null;
                return [
                    'type'        => 'channel',
                    'id'          => $id,
                    'access_hash' => $c?->accessHash,
                    'title'       => $c ? $c->getDisplayName() : 'Channel#' . $id,
                    'username'    => $c?->username,
                    'bot'         => false,
                ];
            default:
                throw new \RuntimeException('Peer type tidak dikenal: ' . $type);
        }
    }

    // -----------------------------------------------------------------------
    // messages.getHistory — new Telethon-like API (takes InputPeer directly)
    // -----------------------------------------------------------------------

    /**
     * Fetch message history for a peer using InputPeer directly.
     * Returns array of message dicts including 'reply_markup' when present.
     *
     * This is called by TelegramClient::getHistory().
     */
    public function getHistoryByPeer(
        InputPeer $inputPeer,
        int       $limit      = 20,
        int       $offsetId   = 0,
        int       $offsetDate = 0,
        int       $addOffset  = 0,
        int       $maxId      = 0,
        int       $minId      = 0
    ): array {
        $this->assertReady();
        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender not ready');

        $request = new MessagesGetHistoryRequest($inputPeer, $limit, $offsetId, $offsetDate, $addOffset, $maxId, $minId);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e
            );
        }

        return $this->parseHistoryResponseFull($response['constructor'], $response['reader'], $inputPeer);
    }

    /**
     * Parse history response using FullMessage (with reply_markup support).
     */
    private function parseHistoryResponseFull(int $ctor, BinaryReader $reader, InputPeer $inputPeer): array
    {
        $C_MESSAGES          = 0x8c718e87;
        $C_MESSAGES_SLICE    = 0x3a54685e;
        $C_MESSAGES_SLICE_V2 = 0x762b263d;
        $C_CHANNEL_MESSAGES  = 0xc776ba4e;
        $C_NOT_MODIFIED      = 0x74535f21;

        if ($ctor === $C_NOT_MODIFIED) {
            return ['messages' => [], 'count' => $reader->readInt()];
        }

        $totalCount = null;
        if ($ctor === $C_MESSAGES) {
            // No extra header
        } elseif ($ctor === $C_MESSAGES_SLICE || $ctor === $C_MESSAGES_SLICE_V2) {
            $flags = $reader->readInt();
            $totalCount = $reader->readInt();
            if ($flags & (1 << 0)) $reader->readInt(); // next_rate
            if ($flags & (1 << 2)) $reader->readInt(); // offset_id_offset
        } elseif ($ctor === $C_CHANNEL_MESSAGES) {
            $flags = $reader->readInt();
            $reader->readInt();              // pts
            $totalCount = $reader->readInt(); // count
            if ($flags & (1 << 2)) $reader->readInt(); // offset_id_offset
        } else {
            throw new \RuntimeException(sprintf('Unexpected getHistory response: 0x%08x', $ctor));
        }

        // messages:Vector<Message>
        $parsedMsgs = [];
        $reader->readInt(); // vector ctor
        $msgCount = $reader->readInt();
        for ($i = 0; $i < $msgCount; $i++) {
            $msgCtor = $reader->readInt();
            try {
                $msg = FullMessage::fromReader($reader, $msgCtor);
                $parsedMsgs[$msg->id] = $msg;
            } catch (\Exception $e) {
                break;
            }
        }

        // topics:Vector<ForumTopic> (channelMessages only)
        if ($ctor === $C_CHANNEL_MESSAGES) {
            $reader->readInt(); // vector ctor
            $topicCount = $reader->readInt();
            if ($topicCount > 0) {
                return $this->buildFullMessageResult(array_values($parsedMsgs), [], [], $totalCount, $inputPeer);
            }
        }

        // chats:Vector<Chat>
        $chats = [];
        try {
            $reader->readInt();
            $chatCount = $reader->readInt();
            for ($i = 0; $i < $chatCount; $i++) {
                $c2 = $reader->readInt();
                try { $chat = Chat::fromReader($reader, $c2); $chats[$chat->id] = $chat; } catch (\Exception $e) { break; }
            }
        } catch (\Exception $e) {}

        // users:Vector<User>
        $users = [];
        try {
            $reader->readInt();
            $userCount = $reader->readInt();
            for ($i = 0; $i < $userCount; $i++) {
                $c2 = $reader->readInt();
                if ($c2 === User::CONSTRUCTOR_EMPTY) { $reader->readLong(); continue; }
                try { $user = User::fromReader($reader); $users[$user->id] = $user; } catch (\Exception $e) { break; }
            }
        } catch (\Exception $e) {}

        return $this->buildFullMessageResult(array_values($parsedMsgs), $users, $chats, $totalCount, $inputPeer);
    }

    private function buildFullMessageResult(array $msgs, array $users, array $chats, ?int $totalCount, InputPeer $inputPeer): array
    {
        $result = [];
        foreach ($msgs as $msg) {
            $fromName = '';
            if ($msg->out) {
                $fromName = 'You';
            } elseif ($msg->fromUserId !== null) {
                $u = $users[$msg->fromUserId] ?? null;
                $fromName = $u ? $u->getDisplayName() : 'User#' . $msg->fromUserId;
            }

            // Attach client + peer so FullMessage::click() works
            $msg->setClient($this->client, $inputPeer);

            $result[] = [
                'id'           => $msg->id,
                'date'         => $msg->date,
                'text'         => $msg->text,
                'message'      => $msg->text,   // Telethon uses .message
                'out'          => $msg->out,
                'type'         => $msg->type,
                'from_id'      => $msg->fromUserId,
                'from'         => $fromName,
                'media'        => $msg->media,        // null if no media; array with 'type','mime','filename' if has media
                'reply_markup' => $msg->replyMarkup,  // null if not present
                '_message_obj' => $msg,               // raw FullMessage for click()
            ];
        }

        usort($result, fn($a, $b) => $b['id'] <=> $a['id']);
        return ['messages' => $result, 'count' => $totalCount ?? count($result)];
    }

    // -----------------------------------------------------------------------
    // messages.getHistory — original API (peerType + peerId + accessHash)
    // -----------------------------------------------------------------------

    /**
     * Ambil riwayat pesan dari sebuah peer.
     *
     * @param string   $peerType  'user' | 'chat' | 'channel'
     * @param int      $peerId
     * @param int      $accessHash  (0 untuk chat biasa)
     * @param int      $limit       Jumlah pesan (default 20)
     * @param int      $offsetId    Mulai dari message_id ini (0 = pesan terbaru)
     * @return array ['messages' => [...], 'count' => int|null]
     */
    public function getHistory(
        string $peerType,
        int    $peerId,
        int    $accessHash = 0,
        int    $limit      = 20,
        int    $offsetId   = 0
    ): array {
        $this->assertReady();
        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $inputPeer = match($peerType) {
            'user'    => InputPeer::user($peerId, $accessHash),
            'chat'    => InputPeer::chat($peerId),
            'channel' => InputPeer::channel($peerId, $accessHash),
            default   => throw new \RuntimeException('Tipe peer tidak valid: ' . $peerType),
        };

        $request = new MessagesGetHistoryRequest($inputPeer, $limit, $offsetId);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e
            );
        }

        return $this->parseHistoryResponse($response['constructor'], $response['reader']);
    }

    /**
     * Parse messages.Messages response.
     *
     * Constructors:
     *   messages.messages#8c718e87         — messages + chats + users
     *   messages.messagesSlice#3a54685e    — flags + count + (next_rate) + (offset) + msgs + chats + users
     *   messages.channelMessages#c776ba4e  — flags + pts + count + msgs + topics + chats + users
     *   messages.messagesNotModified#74535f21 — count
     */
    private function parseHistoryResponse(int $ctor, BinaryReader $reader): array
    {
        // Constructor IDs
        // messages.messages#8c718e87            — messages + chats + users (no header)
        // messages.messagesSlice#3a54685e        — flags + count + [next_rate] + [offset] + msgs + chats + users
        // messages.messagesSlice#762b263d        — same layout, layer 160+
        // messages.channelMessages#c776ba4e      — flags + pts + count + msgs + topics + chats + users
        // messages.messagesNotModified#74535f21  — count
        // NOTE: 0x3072cfa1 = gzip_packed, BUKAN messages variant — dihandle oleh MTProtoSender
        $C_MESSAGES         = 0x8c718e87;
        $C_MESSAGES_SLICE   = 0x3a54685e;
        $C_MESSAGES_SLICE_V2 = 0x762b263d; // layer 160+
        $C_CHANNEL_MESSAGES = 0xc776ba4e;
        $C_NOT_MODIFIED     = 0x74535f21;

        if ($ctor === $C_NOT_MODIFIED) {
            return ['messages' => [], 'count' => $reader->readInt()];
        }

        $totalCount = null;

        if ($ctor === $C_MESSAGES) {
            // No extra fields before messages vector
        } elseif ($ctor === $C_MESSAGES_SLICE || $ctor === $C_MESSAGES_SLICE_V2) {
            $flags = $reader->readInt();
            $totalCount = $reader->readInt(); // count
            if ($flags & (1 << 0)) $reader->readInt(); // next_rate
            if ($flags & (1 << 2)) $reader->readInt(); // offset_id_offset
        } elseif ($ctor === $C_CHANNEL_MESSAGES) {
            $flags = $reader->readInt();
            $reader->readInt(); // pts
            $totalCount = $reader->readInt(); // count
        } else {
            throw new \RuntimeException(sprintf('Unexpected getHistory response: 0x%08x', $ctor));
        }

        // ── messages:Vector<Message> ─────────────────────────────────────
        $parsedMsgs = [];
        $reader->readInt(); // vector ctor
        $msgCount = $reader->readInt();
        for ($i = 0; $i < $msgCount; $i++) {
            $msgCtor = $reader->readInt();
            try {
                $msg = MessageInfo::fromReader($reader, $msgCtor);
                $parsedMsgs[$msg->id] = $msg;
            } catch (\Exception $e) {
                break;
            }
        }

        // ── topics:Vector<ForumTopic>  (channelMessages only) ────────────
        if ($ctor === $C_CHANNEL_MESSAGES) {
            // Skip topics — kita tidak parse forum topics untuk sekarang
            $reader->readInt(); // vector ctor
            $topicCount = $reader->readInt();
            // Forum topics sangat kompleks — jika ada, stream bisa rusak.
            // Untuk channel biasa topicCount = 0.
            if ($topicCount > 0) {
                // Tidak ada cara aman untuk skip ForumTopic tanpa parser penuh.
                // Kembalikan apa yang sudah diparsed dari messages.
                return ['messages' => array_values($parsedMsgs), 'count' => $totalCount];
            }
        }

        // ── chats:Vector<Chat> ───────────────────────────────────────────
        $chats = [];
        $reader->readInt();
        $chatCount = $reader->readInt();
        for ($i = 0; $i < $chatCount; $i++) {
            $c2 = $reader->readInt();
            try {
                $chat = Chat::fromReader($reader, $c2);
                $chats[$chat->id] = $chat;
            } catch (\Exception $e) { break; }
        }

        // ── users:Vector<User> ───────────────────────────────────────────
        $users = [];
        $reader->readInt();
        $userCount = $reader->readInt();
        for ($i = 0; $i < $userCount; $i++) {
            $c2 = $reader->readInt();
            if ($c2 === User::CONSTRUCTOR_EMPTY) { $reader->readLong(); continue; }
            try {
                $user = User::fromReader($reader);
                $users[$user->id] = $user;
            } catch (\Exception $e) { break; }
        }

        // ── Build result array ───────────────────────────────────────────
        $result = [];
        foreach ($parsedMsgs as $msg) {
            // Tentukan nama pengirim
            $fromName = '';
            if ($msg->out) {
                $fromName = 'You';
            } elseif ($msg->fromUserId !== null) {
                $u = $users[$msg->fromUserId] ?? null;
                $fromName = $u ? $u->getDisplayName() : 'User#' . $msg->fromUserId;
            }

            $result[] = [
                'id'        => $msg->id,
                'date'      => $msg->date,
                'text'      => $msg->text,
                'out'       => $msg->out,
                'type'      => $msg->type,
                'from_id'   => $msg->fromUserId,
                'from'      => $fromName,
                'reactions' => $msg->reactions ?? [],
            ];
        }

        // Urutkan terbaru dulu (descending by id)
        usort($result, fn($a, $b) => $b['id'] <=> $a['id']);

        return ['messages' => $result, 'count' => $totalCount ?? count($result)];
    }

    // -----------------------------------------------------------------------
    // messages.sendMedia — kirim foto, video, audio, dokumen, file
    // -----------------------------------------------------------------------

    /**
     * Kirim media (foto/video/audio/dokumen/file) ke sebuah peer.
     *
     * Method ini bersifat low-level: kamu sudah harus punya InputPeer dan InputMedia.
     * Untuk penggunaan lebih mudah, pakai sendPhoto / sendDocument / sendVideo / sendAudio / sendFile.
     *
     * @param InputPeer  $peer          Tujuan pengiriman
     * @param InputMedia $media         Object media yang sudah dibuat (hasil upload)
     * @param string     $caption       Teks / caption media (opsional)
     * @param int|null   $replyToMsgId  Balas pesan tertentu (opsional)
     * @return array     ['sent', 'message_id', 'date', 'caption', 'type']
     */
    public function sendMedia(
        InputPeer                 $peer,
        InputMedia|InputMediaPoll $media,
        string                    $caption      = '',
        ?int                      $replyToMsgId = null
    ): array {
        $this->assertReady();

        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new MessagesSendMediaRequest($peer, $media, $caption, $replyToMsgId);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
            return $this->parseMediaSendResponse($response, $caption);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode,
                $e
            );
        }
    }

    /**
     * Upload & kirim foto dari path lokal.
     *
     * @param InputPeer   $peer          Tujuan pengiriman
     * @param string      $filePath      Path file gambar (JPG, PNG, WebP)
     * @param string      $caption       Caption foto (opsional)
     * @param int|null    $replyToMsgId  ID pesan yang dibalas (opsional)
     * @param callable|null $onProgress  Progress callback: function(int $part, int $total, int $pct)
     * @return array
     */
    public function sendPhoto(
        InputPeer $peer,
        string    $filePath,
        string    $caption      = '',
        ?int      $replyToMsgId = null,
        ?callable $onProgress   = null
    ): array {
        $uploader = $this->makeUploader($onProgress);
        $file     = $uploader->upload($filePath);
        $media    = InputMedia::photo($file);
        $result   = $this->sendMedia($peer, $media, $caption, $replyToMsgId);
        $result['type'] = 'photo';
        return $result;
    }

    /**
     * Upload & kirim video dari path lokal.
     *
     * @param InputPeer   $peer          Tujuan pengiriman
     * @param string      $filePath      Path file video (MP4, MOV, AVI, MKV, dll.)
     * @param string      $caption       Caption video (opsional)
     * @param float       $duration      Durasi video dalam detik (0 = auto-detect via ffprobe)
     * @param int         $width         Lebar frame (0 = auto-detect)
     * @param int         $height        Tinggi frame (0 = auto-detect)
     * @param int|null    $replyToMsgId  ID pesan yang dibalas (opsional)
     * @param callable|null $onProgress  Progress callback
     * @return array
     */
    public function sendVideo(
        InputPeer $peer,
        string    $filePath,
        string    $caption      = '',
        float     $duration     = 0.0,
        int       $width        = 0,
        int       $height       = 0,
        ?int      $replyToMsgId = null,
        ?callable $onProgress   = null
    ): array {
        $uploader = $this->makeUploader($onProgress);
        $file     = $uploader->upload($filePath);
        $mime     = FileUploader::detectMime($filePath);
        $filename = basename($filePath);
        $media    = InputMedia::video($file, $mime, $filename, $duration, $width, $height, true);
        $result   = $this->sendMedia($peer, $media, $caption, $replyToMsgId);
        $result['type'] = 'video';
        return $result;
    }

    /**
     * Upload & kirim audio dari path lokal.
     *
     * @param InputPeer   $peer          Tujuan pengiriman
     * @param string      $filePath      Path file audio (MP3, OGG, FLAC, WAV, dll.)
     * @param string      $caption       Caption audio (opsional)
     * @param int         $duration      Durasi dalam detik (0 = auto-detect via ffprobe)
     * @param string      $title         Judul lagu (opsional)
     * @param string      $performer     Nama artis (opsional)
     * @param int|null    $replyToMsgId  ID pesan yang dibalas (opsional)
     * @param callable|null $onProgress  Progress callback
     * @return array
     */
    public function sendAudio(
        InputPeer $peer,
        string    $filePath,
        string    $caption      = '',
        int       $duration     = 0,
        string    $title        = '',
        string    $performer    = '',
        ?int      $replyToMsgId = null,
        ?callable $onProgress   = null
    ): array {
        $uploader = $this->makeUploader($onProgress);
        $file     = $uploader->upload($filePath);
        $mime     = FileUploader::detectMime($filePath);
        $filename = basename($filePath);
        $media    = InputMedia::audio($file, $mime, $filename, $duration, $title, $performer);
        $result   = $this->sendMedia($peer, $media, $caption, $replyToMsgId);
        $result['type'] = 'audio';
        return $result;
    }

    /**
     * Upload & kirim file/dokumen dari path lokal.
     * Cocok untuk PDF, ZIP, APK, atau file apa pun yang ingin dikirim apa adanya.
     *
     * @param InputPeer   $peer          Tujuan pengiriman
     * @param string      $filePath      Path file lokal
     * @param string      $caption       Caption dokumen (opsional)
     * @param string      $filename      Nama file yang terlihat di chat (default: nama asli)
     * @param int|null    $replyToMsgId  ID pesan yang dibalas (opsional)
     * @param callable|null $onProgress  Progress callback
     * @return array
     */
    public function sendDocument(
        InputPeer $peer,
        string    $filePath,
        string    $caption      = '',
        string    $filename     = '',
        ?int      $replyToMsgId = null,
        ?callable $onProgress   = null
    ): array {
        $uploader = $this->makeUploader($onProgress);
        $filename = $filename !== '' ? $filename : basename($filePath);
        $file     = $uploader->upload($filePath, $filename);
        $mime     = FileUploader::detectMime($filePath);
        $media    = InputMedia::document($file, $mime, $filename);
        $result   = $this->sendMedia($peer, $media, $caption, $replyToMsgId);
        $result['type'] = 'document';
        return $result;
    }

    /**
     * Upload & kirim file dengan deteksi tipe otomatis.
     *
     * Library akan otomatis memilih:
     *   - JPG/PNG/WebP → dikirim sebagai foto (tampil inline)
     *   - MP4/MOV/AVI  → dikirim sebagai video (player inline)
     *   - MP3/OGG/FLAC → dikirim sebagai audio (player audio)
     *   - GIF/lainnya  → dikirim sebagai dokumen
     *
     * @param InputPeer   $peer          Tujuan pengiriman
     * @param string      $filePath      Path file lokal
     * @param string      $caption       Caption (opsional)
     * @param bool        $forceDocument Paksa kirim sebagai dokumen (bypass auto-detect)
     * @param int|null    $replyToMsgId  ID pesan yang dibalas (opsional)
     * @param callable|null $onProgress  Progress callback: function(int $part, int $total, int $pct)
     * @return array      ['sent', 'message_id', 'date', 'caption', 'type', 'mime', 'filename']
     */
    public function sendFile(
        InputPeer $peer,
        string    $filePath,
        string    $caption       = '',
        bool      $forceDocument = false,
        ?int      $replyToMsgId  = null,
        ?callable $onProgress    = null
    ): array {
        $uploader = $this->makeUploader($onProgress);
        $uploaded = $uploader->uploadAuto($filePath, $forceDocument);

        $result = $this->sendMedia($peer, $uploaded['input_media'], $caption, $replyToMsgId);
        $result['type']     = $uploaded['category'];
        $result['mime']     = $uploaded['mime'];
        $result['filename'] = basename($filePath);
        return $result;
    }

    /**
     * Upload & kirim voice note dari path lokal.
     *
     * @param InputPeer     $peer          Tujuan pengiriman
     * @param string        $filePath      Path file audio (OGG/OGA, MP3, dll.)
     * @param int           $duration      Durasi dalam detik
     * @param int|null      $replyToMsgId  ID pesan yang dibalas (opsional)
     * @param callable|null $onProgress    Progress callback
     * @return array        ['sent', 'message_id', 'date', 'type']
     */
    public function sendVoice(
        InputPeer $peer,
        string    $filePath,
        int       $duration     = 0,
        ?int      $replyToMsgId = null,
        ?callable $onProgress   = null
    ): array {
        $uploader = $this->makeUploader($onProgress);
        $file     = $uploader->upload($filePath);
        $media    = InputMedia::voice($file, $duration);
        $result   = $this->sendMedia($peer, $media, '', $replyToMsgId);
        $result['type'] = 'voice';
        return $result;
    }

    /**
     * Kirim poll ke sebuah peer.
     *
     * @param InputPeer     $peer          Tujuan pengiriman
     * @param InputMediaPoll $poll         Poll yang sudah dibuat
     * @param int|null      $replyToMsgId  ID pesan yang dibalas (opsional)
     * @return array        ['sent', 'message_id', 'date', 'type']
     */
    public function sendPoll(
        InputPeer     $peer,
        InputMediaPoll $poll,
        ?int          $replyToMsgId = null
    ): array {
        $result = $this->sendMedia($peer, $poll, '', $replyToMsgId);
        $result['type'] = 'poll';
        return $result;
    }

    /**
     * Cari pesan dalam sebuah peer.
     *
     * @param InputPeer $peer      Tujuan pencarian
     * @param string    $query     Kata kunci pencarian
     * @param int       $limit     Maksimum hasil
     * @param int       $filter    Filter tipe (default: semua pesan)
     * @param int       $offsetId  Offset message ID
     * @return array    Flat array of message arrays (same format as getHistory)
     */
    public function search(
        InputPeer $peer,
        string    $query,
        int       $limit    = 20,
        int       $filter   = MessagesSearchRequest::FILTER_EMPTY,
        int       $offsetId = 0
    ): array {
        $this->assertReady();

        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new MessagesSearchRequest($peer, $query, $limit, $filter, $offsetId);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $result = $this->parseHistoryResponseFull($response['constructor'], $response['reader'], $peer);
        return $result['messages'] ?? $result;
    }

    /**
     * Cari pesan secara global (semua chat).
     *
     * @param string $query  Kata kunci pencarian
     * @param int    $limit  Maksimum hasil
     * @return array  Flat array of message arrays
     */
    public function searchGlobal(string $query, int $limit = 20): array
    {
        $this->assertReady();

        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new MessagesSearchGlobalRequest($query, $limit);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $result = $this->parseHistoryResponse($response['constructor'], $response['reader']);
        return $result['messages'] ?? $result;
    }

    // -----------------------------------------------------------------------
    // Response parsing — sendMedia
    // -----------------------------------------------------------------------

    private function parseMediaSendResponse(array $response, string $caption): array
    {
        $constructor = $response['constructor'];

        // updateShortSentMessage#9d2e67c5 (kadang dipakai untuk media juga)
        if ($constructor === UpdateShortSentMessage::CONSTRUCTOR_ID) {
            $update = UpdateShortSentMessage::fromReader($response['reader']);
            return [
                'sent'       => true,
                'message_id' => $update->id,
                'date'       => $update->date,
                'pts'        => $update->pts,
                'caption'    => $caption,
            ];
        }

        // updates#74ae4240 — respons umum untuk sendMedia
        if ($constructor === 0x74ae4240) {
            $msgId = $this->extractMessageIdFromUpdates($response['reader']);
            return [
                'sent'       => true,
                'message_id' => $msgId,
                'date'       => time(),
                'caption'    => $caption,
            ];
        }

        // updateShort#78d4dec1
        if ($constructor === 0x78d4dec1) {
            return ['sent' => true, 'message_id' => null, 'date' => time(), 'caption' => $caption];
        }

        return [
            'sent'        => true,
            'message_id'  => null,
            'date'        => time(),
            'caption'     => $caption,
            'constructor' => sprintf('0x%08x', $constructor),
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUploader(?callable $onProgress): FileUploader
    {
        $uploader = new FileUploader($this->client);
        if ($onProgress !== null) {
            $uploader->onProgress($onProgress);
        }
        return $uploader;
    }

    // -----------------------------------------------------------------------
    // messages.sendReaction — kirim atau hapus reaksi pada pesan
    // -----------------------------------------------------------------------

    /**
     * Kirim atau hapus reaksi emoji pada sebuah pesan.
     *
     * @param InputPeer $peer       Peer chat tempat pesan berada
     * @param int       $msgId      ID pesan yang akan direaksi
     * @param array     $reactions  Daftar reaksi:
     *                              [['type'=>'emoji','emoticon'=>'👍']]
     *                              Kirim [] untuk HAPUS semua reaksi
     * @param bool      $big        Tampilkan animasi besar
     * @return array  ['ok'=>true,'msg_id'=>int,'reactions'=>array]
     */
    public function sendReaction(InputPeer $peer, int $msgId, array $reactions = [], bool $big = false): array
    {
        $this->assertReady();
        $sender = $this->client->getSender();
        if (!$sender) throw new \RuntimeException('MTProto sender belum siap');

        $request = new MessagesSendReactionRequest($peer, $msgId, $reactions, $big);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $sender->send($request);
            return ['ok' => true, 'msg_id' => $msgId, 'reactions' => $reactions];
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode,
                $e
            );
        }
    }

    private function assertReady(): void
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Tidak terhubung ke Telegram');
        }
        if (!$this->client->getAuth()->isAuthorized()) {
            throw new \RuntimeException('Belum login.');
        }
    }
}
