<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryReader;

/**
 * updateShortSentMessage#9d2e67c5 — response tercepat saat kirim pesan ke user biasa.
 *
 * TL schema:
 *   updateShortSentMessage#9d2e67c5
 *     flags:#
 *     out:flags.1?true
 *     id:int
 *     pts:int
 *     pts_count:int
 *     date:int
 *     media:flags.9?MessageMedia
 *     entities:flags.7?Vector<MessageEntity>
 *     = Updates;
 */
class UpdateShortSentMessage
{
    const CONSTRUCTOR_ID = 0x9d2e67c5;

    public bool $out       = false;
    public int  $id        = 0;
    public int  $pts       = 0;
    public int  $ptsCount  = 0;
    public int  $date      = 0;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj = new self();

        $flags = $reader->readInt();

        $obj->out      = (bool)($flags & (1 << 1));
        $obj->id       = $reader->readInt();
        $obj->pts      = $reader->readInt();
        $obj->ptsCount = $reader->readInt();
        $obj->date     = $reader->readInt();

        // media: flags.9 — skip jika ada (kita tidak parse media untuk sekarang)
        if ($flags & (1 << 9)) {
            // Baca constructor media dan skip sisanya (baca byte mentah yang tersisa)
            // Cukup abaikan saja karena kita tidak butuh ini untuk text message
        }

        // entities: flags.7 — skip jika ada
        // (formatting entities, tidak butuh untuk basic send)

        return $obj;
    }
}
