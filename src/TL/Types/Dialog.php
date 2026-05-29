<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;

/**
 * dialog#d58a08c6 (tdlib telegram_api.tl)
 *
 *   flags:#
 *   pinned:flags.2?true
 *   unread_mark:flags.3?true
 *   view_forum_as_messages:flags.6?true
 *   peer:Peer
 *   top_message:int
 *   read_inbox_max_id:int
 *   read_outbox_max_id:int
 *   unread_count:int
 *   unread_mentions_count:int
 *   unread_reactions_count:int
 *   notify_settings:PeerNotifySettings
 *   pts:flags.0?int
 *   draft:flags.1?DraftMessage
 *   folder_id:flags.4?int
 *   ttl_period:flags.5?int
 *   = Dialog;
 *
 * dialogFolder#71bd134c — arsip folder, kita skip isinya
 */
class Dialog
{
    const CONSTRUCTOR        = 0xd58a08c6;
    const CONSTRUCTOR_FOLDER = 0x71bd134c;

    // Peer type constants (old + new layer constructors)
    const PEER_USER     = 0x59511722; // new (layer 185+)
    const PEER_USER_OLD = 0x9db1bc6d; // legacy
    const PEER_CHAT     = 0x36c6019a; // new (layer 185+)
    const PEER_CHAT_OLD = 0xbad0e5bb; // legacy
    const PEER_CHANNEL  = 0xa2a5371e; // unchanged

    /** @var string 'user'|'chat'|'channel' */
    public string $peerType   = 'user';
    public int    $peerId     = 0;

    public int  $topMessage           = 0;
    public int  $readInboxMaxId       = 0;
    public int  $readOutboxMaxId      = 0;
    public int  $unreadCount          = 0;
    public int  $unreadMentionsCount  = 0;
    public int  $unreadReactionsCount = 0;
    public bool $pinned               = false;
    public bool $unreadMark           = false;
    public ?int $folderId             = null;
    public ?int $ttlPeriod            = null;

    /**
     * Parse dari BinaryReader. Constructor SUDAH dibaca oleh pemanggil.
     *
     * @param int $constructor 0xd58a08c6 atau 0x71bd134c
     */
    public static function fromReader(BinaryReader $reader, int $constructor): self
    {
        $obj = new self();

        if ($constructor === self::CONSTRUCTOR_FOLDER) {
            // dialogFolder — kita skip semua field, kembalikan objek kosong
            self::skipDialogFolder($reader);
            $obj->peerType = 'folder';
            return $obj;
        }

        // dialog#d58a08c6
        $flags = $reader->readInt();

        $obj->pinned     = (bool)($flags & (1 << 2));
        $obj->unreadMark = (bool)($flags & (1 << 3));

        // peer:Peer
        $peerCtor = $reader->readInt();
        switch ($peerCtor) {
            case self::PEER_USER:
            case self::PEER_USER_OLD:
                $obj->peerType = 'user';
                $obj->peerId   = $reader->readLong();
                break;
            case self::PEER_CHAT:
            case self::PEER_CHAT_OLD:
                $obj->peerType = 'chat';
                $obj->peerId   = $reader->readLong();
                break;
            case self::PEER_CHANNEL:
                $obj->peerType = 'channel';
                $obj->peerId   = $reader->readLong();
                break;
            default:
                // Unknown peer — tetap baca long agar stream tidak rusak
                $reader->readLong();
                break;
        }

        $obj->topMessage           = $reader->readInt();
        $obj->readInboxMaxId       = $reader->readInt();
        $obj->readOutboxMaxId      = $reader->readInt();
        $obj->unreadCount          = $reader->readInt();
        $obj->unreadMentionsCount  = $reader->readInt();
        $obj->unreadReactionsCount = $reader->readInt();

        // notify_settings:PeerNotifySettings — selalu ada
        TLSkipHelper::skipPeerNotifySettings($reader);

        // pts:flags.0?int
        if ($flags & (1 << 0)) $reader->readInt();

        // draft:flags.1?DraftMessage
        if ($flags & (1 << 1)) TLSkipHelper::skipDraftMessage($reader);

        // folder_id:flags.4?int
        if ($flags & (1 << 4)) $obj->folderId = $reader->readInt();

        // ttl_period:flags.5?int
        if ($flags & (1 << 5)) $obj->ttlPeriod = $reader->readInt();

        return $obj;
    }

    /**
     * Skip dialogFolder#71bd134c:
     *   flags:# pinned:f.2?true folder:Folder top_message:int
     *   unread_muted_peers_count:int unread_unmuted_peers_count:int
     *   unread_muted_messages_count:int unread_unmuted_messages_count:int
     *
     * folder#ff544e65 id:int title:string
     */
    private static function skipDialogFolder(BinaryReader $reader): void
    {
        $flags = $reader->readInt(); // flags
        // folder:Folder — constructor + id:int + title:string
        $reader->readInt();    // folder constructor (0xff544e65)
        $reader->readInt();    // id
        $reader->readString(); // title
        $reader->readInt();    // top_message
        $reader->readInt();    // unread_muted_peers_count
        $reader->readInt();    // unread_unmuted_peers_count
        $reader->readInt();    // unread_muted_messages_count
        $reader->readInt();    // unread_unmuted_messages_count
    }
}
