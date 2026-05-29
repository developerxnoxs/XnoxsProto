<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * account.getPrivacy#dadbc950 key:InputPrivacyKey = account.PrivacyRules
 *
 * PENTING: Nilai di sini adalah inputPrivacyKey* (dipakai saat MENGIRIM ke Telegram).
 * Berbeda dengan privacyKey* yang dipakai di update/response.
 *
 * Dari TL schema resmi Telegram (Layer 214):
 *   inputPrivacyKeyStatusTimestamp#4f96cb18
 *   inputPrivacyKeyChatInvite#bdfb0426
 *   inputPrivacyKeyPhoneCall#fabadc5f
 *   inputPrivacyKeyPhoneP2P#db9e70d2
 *   inputPrivacyKeyForwards#a4dd4c08
 *   inputPrivacyKeyProfilePhoto#5719bacc
 *   inputPrivacyKeyPhoneNumber#352dafa
 *   inputPrivacyKeyAddedByPhone#d1219bdd
 *   inputPrivacyKeyVoiceMessages#aee69d68
 *   inputPrivacyKeyAbout#3823cc40
 *   inputPrivacyKeyBirthday#d65a11cc
 */
class AccountGetPrivacyRequest extends TLObject
{
    const CONSTRUCTOR = 0xdadbc950;

    const KEY_STATUS_TIMESTAMP = 0x4f96cb18;
    const KEY_CHAT_INVITE      = 0xbdfb0426;
    const KEY_PHONE_CALL       = 0xfabadc5f;
    const KEY_PHONE_P2P        = 0xdb9e70d2;
    const KEY_FORWARDS         = 0xa4dd4c08;
    const KEY_PROFILE_PHOTO    = 0x5719bacc;
    const KEY_PHONE_NUMBER     = 0x0352dafa;
    const KEY_ADDED_BY_PHONE   = 0xd1219bdd;
    const KEY_VOICE_MESSAGES   = 0xaee69d68;
    const KEY_ABOUT            = 0x3823cc40;
    const KEY_BIRTHDAY         = 0xd65a11cc;

    private int $key;

    public function __construct(int $key = self::KEY_STATUS_TIMESTAMP)
    {
        $this->key = $key;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt($this->key);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'account.getPrivacy', 'key' => $this->key];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
