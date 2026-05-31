<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;
use XnoxsProto\TL\Types\InputPeer;

/**
 * messages.sendReaction#d30d78d4
 *   flags:#
 *   big:flags.1?true
 *   add_to_recent:flags.2?true
 *   peer:InputPeer
 *   msg_id:int
 *   reaction:flags.0?Vector<Reaction>
 *   = Updates;
 *
 * Reaction constructors (confirmed from server TLSkipHelper):
 *   reactionEmpty#79f5d419
 *   reactionEmoji#1b2286b8       emoticon:string
 *   reactionCustomEmoji#8935fc73  document_id:long
 *   reactionPaid#95d2ac92
 *
 * Usage:
 *   // Add 👍 reaction
 *   new MessagesSendReactionRequest($peer, $msgId, [['type'=>'emoji','emoticon'=>'👍']]);
 *
 *   // Remove all reactions (pass empty array)
 *   new MessagesSendReactionRequest($peer, $msgId, []);
 *
 *   // Custom emoji (Telegram Premium)
 *   new MessagesSendReactionRequest($peer, $msgId, [['type'=>'custom_emoji','document_id'=>12345]]);
 */
class MessagesSendReactionRequest extends TLObject
{
    const CONSTRUCTOR = 0xd30d78d4;

    const VECTOR_CTOR           = 0x1cb5c415;
    const REACTION_EMPTY        = 0x79f5d419;
    const REACTION_EMOJI        = 0x1b2286b8;
    const REACTION_CUSTOM_EMOJI = 0x8935fc73;
    const REACTION_PAID         = 0x95d2ac92;

    private InputPeer $peer;
    private int       $msgId;
    private array     $reactions;
    private bool      $big;
    private bool      $addToRecent;

    /**
     * @param InputPeer $peer
     * @param int       $msgId        ID pesan yang akan direaksi
     * @param array     $reactions    Daftar reaksi. Setiap item:
     *                                ['type'=>'emoji','emoticon'=>'👍']
     *                                ['type'=>'custom_emoji','document_id'=>int]
     *                                ['type'=>'paid']
     *                                Kirim array kosong [] untuk HAPUS semua reaksi.
     * @param bool      $big          Tampilkan animasi reaksi besar
     * @param bool      $addToRecent  Tambah emoji ke daftar reaksi terakhir
     */
    public function __construct(
        InputPeer $peer,
        int       $msgId,
        array     $reactions    = [],
        bool      $big          = false,
        bool      $addToRecent  = true
    ) {
        $this->peer        = $peer;
        $this->msgId       = $msgId;
        $this->reactions   = $reactions;
        $this->big         = $big;
        $this->addToRecent = $addToRecent;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);

        $flags = (1 << 0);              // flags.0 — reaction vector selalu disertakan
        if ($this->big)         $flags |= (1 << 1);
        if ($this->addToRecent) $flags |= (1 << 2);

        $writer->writeInt($flags);
        $this->peer->serialize($writer);
        $writer->writeInt($this->msgId);

        // reaction:flags.0?Vector<Reaction>
        $writer->writeInt(self::VECTOR_CTOR);
        $writer->writeInt(count($this->reactions));

        foreach ($this->reactions as $rxn) {
            $type = $rxn['type'] ?? 'emoji';
            switch ($type) {
                case 'empty':
                    $writer->writeInt(self::REACTION_EMPTY);
                    break;
                case 'custom_emoji':
                    $writer->writeInt(self::REACTION_CUSTOM_EMOJI);
                    $writer->writeLong((int)($rxn['document_id'] ?? 0));
                    break;
                case 'paid':
                    $writer->writeInt(self::REACTION_PAID);
                    break;
                default: // 'emoji'
                    $writer->writeInt(self::REACTION_EMOJI);
                    $writer->writeString($rxn['emoticon'] ?? '👍');
                    break;
            }
        }
    }

    public function toDict(): array
    {
        return [
            '_'         => 'messages.sendReaction',
            'peer'      => ['type' => $this->peer->getType(), 'id' => $this->peer->getId()],
            'msg_id'    => $this->msgId,
            'reactions' => $this->reactions,
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
