<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * channels.editAdmin#9a98ad68 flags:#
 *   channel:InputChannel
 *   user_id:InputUser
 *   admin_rights:ChatAdminRights
 *   rank:flags.0?string
 *   = Updates;
 *
 * Perubahan dari #d33c09d1 (layer lama) → #9a98ad68 (Layer 214):
 *   - Tambah field `flags:#` di awal
 *   - `rank` sekarang opsional (flags.0?string), hanya ditulis jika non-empty
 *
 * chatAdminRights#5fb224d5 flags:#
 *   change_info:flags.0?true           0x00001
 *   post_messages:flags.1?true         0x00002  (channels only)
 *   edit_messages:flags.2?true         0x00004  (channels only)
 *   delete_messages:flags.3?true       0x00008
 *   ban_users:flags.4?true             0x00010
 *   invite_users:flags.5?true          0x00020
 *   pin_messages:flags.7?true          0x00080
 *   add_admins:flags.9?true            0x00200
 *   anonymous:flags.10?true            0x00400
 *   manage_call:flags.11?true          0x00800
 *   other:flags.12?true                0x01000  (must be set for basic admin)
 *   manage_topics:flags.13?true        0x02000
 *   post_stories:flags.14?true         0x04000
 *   edit_stories:flags.15?true         0x08000
 *   delete_stories:flags.16?true       0x10000
 *   manage_direct_messages:flags.17?true 0x20000  (new Layer 214)
 *   manage_ranks:flags.18?true         0x40000  (new Layer 214)
 *   = ChatAdminRights;
 *
 * inputChannel#f35aec28 channel_id:long access_hash:long
 * inputUser#f21158c6    user_id:long    access_hash:long
 */
class ChannelsEditAdminRequest extends TLObject
{
    const CONSTRUCTOR = 0x9a98ad68;

    const RIGHT_CHANGE_INFO            = 0x00001;
    const RIGHT_POST_MESSAGES          = 0x00002;
    const RIGHT_EDIT_MESSAGES          = 0x00004;
    const RIGHT_DELETE_MESSAGES        = 0x00008;
    const RIGHT_BAN_USERS              = 0x00010;
    const RIGHT_INVITE_USERS           = 0x00020;
    const RIGHT_PIN_MESSAGES           = 0x00080;
    const RIGHT_ADD_ADMINS             = 0x00200;
    const RIGHT_ANONYMOUS              = 0x00400;
    const RIGHT_MANAGE_CALL            = 0x00800;
    const RIGHT_OTHER                  = 0x01000;
    const RIGHT_MANAGE_TOPICS          = 0x02000;
    const RIGHT_POST_STORIES           = 0x04000;
    const RIGHT_EDIT_STORIES           = 0x08000;
    const RIGHT_DELETE_STORIES         = 0x10000;
    const RIGHT_MANAGE_DIRECT_MESSAGES = 0x20000;
    const RIGHT_MANAGE_RANKS           = 0x40000;

    public function __construct(
        private int    $channelId,
        private int    $channelAccessHash,
        private int    $userId,
        private int    $userAccessHash,
        private int    $adminRightsFlags,
        private string $rank = ''
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:# — bit 0 = rank present
        $flags = ($this->rank !== '') ? 0x1 : 0x0;
        $writer->writeInt($flags);

        // InputChannel#f35aec28
        $writer->writeInt(0xf35aec28);
        $writer->writeLong($this->channelId);
        $writer->writeLong($this->channelAccessHash);

        // InputUser#f21158c6
        $writer->writeInt(0xf21158c6);
        $writer->writeLong($this->userId);
        $writer->writeLong($this->userAccessHash);

        // chatAdminRights#5fb224d5
        $writer->writeInt(0x5fb224d5);
        $writer->writeInt($this->adminRightsFlags);

        // rank:flags.0?string — hanya tulis jika flag bit 0 set
        if ($flags & 0x1) {
            $writer->writeString($this->rank);
        }
    }

    public function toDict(): array
    {
        return [
            '_'           => 'channels.editAdmin',
            'channel_id'  => $this->channelId,
            'user_id'     => $this->userId,
            'admin_flags' => $this->adminRightsFlags,
            'rank'        => $this->rank,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
