<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.exportChatInvite#a02ce5d5 flags:#
 *   legacy_revoke_permanent:flags.2?true
 *   request_needed:flags.3?true
 *   peer:InputPeer
 *   expire_date:flags.0?int
 *   usage_limit:flags.1?int
 *   title:flags.4?string
 *   = ExportedChatInvite;
 *
 * Generate link undangan untuk grup, channel, atau supergroup.
 * Jika legacy_revoke_permanent=true, link permanen lama di-revoke dan link baru dibuat.
 *
 * CRC verified dari TDLib telegram_api.tl (Layer 214).
 */
class MessagesExportChatInviteRequest extends TLObject
{
    const CONSTRUCTOR = 0xa02ce5d5;

    /**
     * @param InputPeer  $peer              Peer target (grup/channel/supergroup)
     * @param bool       $revokePermanent   true = revoke link lama, buat baru
     * @param bool       $requestNeeded     true = perlu persetujuan admin sebelum join
     * @param int|null   $expireDate        Unix timestamp kapan link kadaluarsa (null = selamanya)
     * @param int|null   $usageLimit        Batas berapa kali link bisa dipakai (null = unlimited)
     * @param string     $title             Nama/label untuk link (untuk identifikasi)
     */
    public function __construct(
        private InputPeer $peer,
        private bool      $revokePermanent = false,
        private bool      $requestNeeded   = false,
        private ?int      $expireDate      = null,
        private ?int      $usageLimit      = null,
        private string    $title           = ''
    ) {}

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        // flags:#
        $flags = 0;
        if ($this->expireDate      !== null) $flags |= (1 << 0);
        if ($this->usageLimit      !== null) $flags |= (1 << 1);
        if ($this->revokePermanent)          $flags |= (1 << 2);
        if ($this->requestNeeded)            $flags |= (1 << 3);
        if ($this->title !== '')             $flags |= (1 << 4);
        $writer->writeInt($flags);

        // peer:InputPeer
        $this->peer->serialize($writer);

        // expire_date:flags.0?int
        if ($this->expireDate !== null) {
            $writer->writeInt($this->expireDate);
        }

        // usage_limit:flags.1?int
        if ($this->usageLimit !== null) {
            $writer->writeInt($this->usageLimit);
        }

        // title:flags.4?string
        if ($this->title !== '') {
            $writer->writeString($this->title);
        }
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_'               => 'messages.exportChatInvite',
            'revoke_permanent'=> $this->revokePermanent,
            'request_needed'  => $this->requestNeeded,
            'expire_date'     => $this->expireDate,
            'usage_limit'     => $this->usageLimit,
            'title'           => $this->title,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
