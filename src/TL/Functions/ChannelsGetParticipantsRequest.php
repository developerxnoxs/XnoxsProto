<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.getParticipants#77ced9d0
 *   channel : InputChannel
 *   filter  : ChannelParticipantsFilter
 *   offset  : int
 *   limit   : int
 *   hash    : long
 *   = channels.ChannelParticipants;
 *
 * Response constructors:
 *   channels.channelParticipants#9ab0feaf
 *     count:int participants:Vector<ChannelParticipant> chats:Vector<Chat> users:Vector<User>
 *   channels.channelParticipantsNotModified#f0173fe9
 *     (no fields)
 */
class ChannelsGetParticipantsRequest extends TLObject
{
    const CONSTRUCTOR = 0x77ced9d0;

    // -------------------------------------------------------------------------
    // ChannelParticipantsFilter constructors
    // -------------------------------------------------------------------------
    const FILTER_RECENT   = 0xde3f3c79; // channelParticipantsRecent    — no extra fields
    const FILTER_ADMINS   = 0xb4608969; // channelParticipantsAdmins    — no extra fields
    const FILTER_BOTS     = 0xb0d1865b; // channelParticipantsBots      — no extra fields
    const FILTER_BANNED   = 0x1427a5e1; // channelParticipantsBanned    q:string
    const FILTER_SEARCH   = 0x0656ac4b; // channelParticipantsSearch    q:string

    // -------------------------------------------------------------------------
    // ChannelParticipant constructors (used when parsing response)
    // Source of truth: TDLib telegram_api.tl
    // -------------------------------------------------------------------------

    // channelParticipant#1bd54456
    //   flags:# user_id:long date:int
    //   subscription_until_date:flags.0?int rank:flags.2?string
    const PARTICIPANT_MEMBER  = 0x1bd54456;

    // channelParticipantSelf#a9478a1a
    //   flags:# via_request:flags.0?true user_id:long inviter_id:long date:int
    //   subscription_until_date:flags.1?int rank:flags.2?string
    const PARTICIPANT_SELF    = 0xa9478a1a;

    // channelParticipantCreator#2fe601d3
    //   flags:# user_id:long admin_rights:ChatAdminRights rank:flags.0?string
    const PARTICIPANT_CREATOR = 0x2fe601d3;

    // channelParticipantAdmin#34c3bb53
    //   flags:# can_edit:flags.0?true self:flags.1?true user_id:long
    //   inviter_id:flags.1?long promoted_by:long date:int
    //   admin_rights:ChatAdminRights rank:flags.2?string
    const PARTICIPANT_ADMIN   = 0x34c3bb53;

    // channelParticipantBanned#d5f0ad91
    //   flags:# left:flags.0?true peer:Peer kicked_by:long date:int
    //   banned_rights:ChatBannedRights rank:flags.2?string
    const PARTICIPANT_BANNED  = 0xd5f0ad91;

    // channelParticipantLeft#1b03f006
    //   peer:Peer
    const PARTICIPANT_LEFT    = 0x1b03f006;

    // Older CRCs — kept for backward compatibility when parsing server responses
    const PARTICIPANT_MEMBER_OLD = 0xcb397619; // flags:# user_id:long date:int sub_until:flags.0?int
    const PARTICIPANT_SELF_OLD   = 0x4f607bef; // flags:# user_id:long inviter_id:flags.1?long date:int
    const PARTICIPANT_BANNED_OLD = 0x6df8014e; // flags:# peer:Peer kicked_by:long date:int banned_rights

    public function __construct(
        private int    $channelId,
        private int    $channelHash,
        private int    $filter      = self::FILTER_RECENT,
        private string $searchQuery = '',
        private int    $offset      = 0,
        private int    $limit       = 100,
        private int    $hash        = 0
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // channel:InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->channelHash);

        // filter:ChannelParticipantsFilter
        // Both FILTER_SEARCH and FILTER_BANNED carry a q:string field
        $writer->writeInt($this->filter);
        if ($this->filter === self::FILTER_SEARCH || $this->filter === self::FILTER_BANNED) {
            $writer->writeString($this->searchQuery);
        }

        $writer->writeInt($this->offset);
        $writer->writeInt($this->limit);
        $writer->writeLong($this->hash);
    }

    public function toDict(): array
    {
        return [
            '_'          => 'channels.getParticipants',
            'channel_id' => $this->channelId,
            'filter'     => $this->filter,
            'offset'     => $this->offset,
            'limit'      => $this->limit,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
