<?php

namespace XnoxsProto\TL\Parser;

use XnoxsProto\TL\BinaryReader;

/**
 * Parse ReplyMarkup dari binary stream Telegram (Layer 214).
 *
 * Constructor IDs (verified with TLSkipHelper):
 *   replyKeyboardHide#a03e5b85
 *   replyKeyboardForceReply#f4108aa0
 *   replyKeyboardMarkup#85dd99d1     flags:# rows placeholder:f.2?string
 *   replyInlineMarkup#48a30254       rows:Vector<KeyboardButtonRow>
 *
 * KeyboardButtonRow#77608b83  buttons:Vector<KeyboardButton>
 *
 * Inline button variants:
 *   keyboardButtonCallback#35bbdb6b   flags:# requires_password:f.0?true text:string data:bytes
 *   keyboardButtonUrl#258aff05         text:string url:string
 *   keyboardButton#a2fa4880            text:string  (basic, no data)
 *   keyboardButtonUrlAuth#10b78d29     flags:# text:string fwd_text:f.1?string url:string button_id:int
 *   keyboardButtonSwitchInline#0568a748 flags:# text:string query:string
 *   keyboardButtonSwitchInline#93b9fbb5 flags:# text:string query:string peer_types:f.1?Vector
 *   keyboardButtonUserProfile#e988037b text:string user_id:long
 *   keyboardButtonWebView#0d01b6f5    text:string url:string
 *   keyboardButtonSimpleWebView#a0c0505c text:string url:string
 *   keyboardButtonRequestPoll#bbc7515d flags:# quiz:f.0?Bool text:string
 *   keyboardButtonCopyText#a29b9606   text:string copy_text:string
 *   keyboardButtonGame#50f41ccf       text:string
 *   keyboardButtonBuy#afd93fbb        text:string
 */
class ReplyMarkupParser
{
    const REPLY_KEYBOARD_HIDE        = 0xa03e5b85;
    const REPLY_KEYBOARD_FORCE_REPLY = 0xf4108aa0;
    const REPLY_KEYBOARD_MARKUP      = 0x85dd99d1;
    const REPLY_INLINE_MARKUP        = 0x48a30254;
    const REPLY_INLINE_MARKUP_ALT    = 0xe984091a; // some layers

    const BTN_CALLBACK_NEW   = 0x35bbdb6b; // flags:# text:string data:bytes
    const BTN_CALLBACK_OLD   = 0xa2fa4880; // text:string only (no data)
    const BTN_URL            = 0x258aff05;
    const BTN_URL_AUTH       = 0x10b78d29;
    const BTN_SWITCH_INLINE  = 0x0568a748;
    const BTN_SWITCH_INLINE2 = 0x93b9fbb5;
    const BTN_SWITCH_SAME    = 0xd02e7fd4;
    const BTN_USER_PROFILE_OLD = 0xd02e7fd4;
    const BTN_USER_PROFILE   = 0xe988037b;
    const BTN_WEB_VIEW       = 0x0d01b6f5;
    const BTN_SIMPLE_WEB     = 0xa0c0505c;
    const BTN_SIMPLE_WEB_OLD = 0x13767230;
    const BTN_REQ_POLL       = 0xbbc7515d;
    const BTN_COPY_TEXT      = 0xa29b9606;
    const BTN_GAME           = 0x50f41ccf;
    const BTN_BUY            = 0xafd93fbb;
    const BTN_REQ_PHONE      = 0xb16a6c29;
    const BTN_REQ_GEO        = 0xfc796b3f;

    /**
     * Read and parse a ReplyMarkup (constructor has NOT been read yet).
     *
     * Returns:
     *   ['type' => 'inline'|'keyboard'|'hide'|'force_reply',
     *    'rows' => [
     *      [  // row 0
     *        ['type'=>'callback'|'url'|..., 'text'=>'...', 'data'=>'...', 'url'=>'...'],
     *        ...
     *      ],
     *      ...
     *    ]]
     */
    public static function parse(BinaryReader $reader): array
    {
        $ctor = $reader->readInt();
        return self::parseFromCtor($reader, $ctor);
    }

    public static function parseFromCtor(BinaryReader $reader, int $ctor): array
    {
        switch ($ctor) {
            case self::REPLY_KEYBOARD_HIDE:
                $reader->readInt(); // flags
                return ['type' => 'hide', 'rows' => []];

            case self::REPLY_KEYBOARD_FORCE_REPLY:
                $flags = $reader->readInt();
                if ($flags & (1 << 3)) $reader->readString(); // placeholder
                return ['type' => 'force_reply', 'rows' => []];

            case self::REPLY_KEYBOARD_MARKUP:
                $flags = $reader->readInt();
                $rows = self::parseRows($reader);
                if ($flags & (1 << 2)) $reader->readString(); // placeholder
                return ['type' => 'keyboard', 'rows' => $rows];

            case self::REPLY_INLINE_MARKUP:
            case self::REPLY_INLINE_MARKUP_ALT:
                $rows = self::parseRows($reader);
                return ['type' => 'inline', 'rows' => $rows];

            default:
                return ['type' => 'unknown', 'rows' => [], 'ctor' => sprintf('0x%08x', $ctor)];
        }
    }

    private static function parseRows(BinaryReader $reader): array
    {
        $reader->readInt(); // vector ctor 0x1cb5c415
        $rowCount = $reader->readInt();
        $rows = [];
        for ($r = 0; $r < $rowCount; $r++) {
            $reader->readInt(); // keyboardButtonRow#77608b83
            $reader->readInt(); // buttons vector ctor
            $btnCount = $reader->readInt();
            $buttons = [];
            for ($b = 0; $b < $btnCount; $b++) {
                $buttons[] = self::parseButton($reader);
            }
            $rows[] = $buttons;
        }
        return $rows;
    }

    private static function parseButton(BinaryReader $reader): array
    {
        $ctor = $reader->readInt();
        switch ($ctor) {
            case self::BTN_CALLBACK_NEW:
                $flags = $reader->readInt();
                $text  = $reader->readString();
                $data  = $reader->readBytes();
                return ['type' => 'callback', 'text' => $text, 'data' => $data, 'url' => null];

            case self::BTN_CALLBACK_OLD:
                $text = $reader->readString();
                return ['type' => 'callback', 'text' => $text, 'data' => null, 'url' => null];

            case self::BTN_URL:
                $text = $reader->readString();
                $url  = $reader->readString();
                return ['type' => 'url', 'text' => $text, 'data' => null, 'url' => $url];

            case self::BTN_URL_AUTH:
                $flags = $reader->readInt();
                $text  = $reader->readString();
                if ($flags & (1 << 1)) $reader->readString(); // fwd_text
                $url  = $reader->readString();
                $reader->readInt(); // button_id
                return ['type' => 'url_auth', 'text' => $text, 'data' => null, 'url' => $url];

            case self::BTN_WEB_VIEW:
            case self::BTN_SIMPLE_WEB:
            case self::BTN_SIMPLE_WEB_OLD:
                $text = $reader->readString();
                $url  = $reader->readString();
                return ['type' => 'web_view', 'text' => $text, 'data' => null, 'url' => $url];

            case self::BTN_SWITCH_INLINE:
                $reader->readInt(); // flags
                $text  = $reader->readString();
                $query = $reader->readString();
                return ['type' => 'switch_inline', 'text' => $text, 'data' => $query, 'url' => null];

            case self::BTN_SWITCH_INLINE2:
                $flags = $reader->readInt();
                $text  = $reader->readString();
                $query = $reader->readString();
                if ($flags & (1 << 1)) TLSkipHelper::skipVector($reader, fn($x) => $x->readInt());
                return ['type' => 'switch_inline', 'text' => $text, 'data' => $query, 'url' => null];

            case self::BTN_USER_PROFILE:
                $text   = $reader->readString();
                $userId = $reader->readLong();
                return ['type' => 'user_profile', 'text' => $text, 'data' => null, 'url' => null, 'user_id' => $userId];

            case self::BTN_REQ_POLL:
                $flags = $reader->readInt();
                if ($flags & (1 << 0)) $reader->readInt(); // quiz:Bool
                $text = $reader->readString();
                return ['type' => 'request_poll', 'text' => $text, 'data' => null, 'url' => null];

            case self::BTN_COPY_TEXT:
                $text     = $reader->readString();
                $copyText = $reader->readString();
                return ['type' => 'copy_text', 'text' => $text, 'data' => $copyText, 'url' => null];

            case self::BTN_GAME:
            case self::BTN_BUY:
                $text = $reader->readString();
                return ['type' => 'game', 'text' => $text, 'data' => null, 'url' => null];

            case self::BTN_REQ_PHONE:
            case self::BTN_REQ_GEO:
                $text = $reader->readString();
                return ['type' => 'request', 'text' => $text, 'data' => null, 'url' => null];

            default:
                try { $text = $reader->readString(); } catch (\Exception $e) { $text = ''; }
                return ['type' => 'unknown', 'text' => $text, 'data' => null, 'url' => null,
                        'ctor' => sprintf('0x%08x', $ctor)];
        }
    }
}
