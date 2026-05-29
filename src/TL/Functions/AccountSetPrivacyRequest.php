<?php

namespace XnoxsProto\TL\Functions;

use XnoxsProto\TL\TLObject;
use XnoxsProto\TL\BinaryWriter;

/**
 * account.setPrivacy#c9f81ce8 key:InputPrivacyKey rules:Vector<InputPrivacyRule> = account.PrivacyRules
 *
 * Privacy rules:
 *   inputPrivacyValueAllowAll      = 0x184b35ce
 *   inputPrivacyValueAllowContacts = 0xd09e07b
 *   inputPrivacyValueDisallowAll   = 0xd66b66c9
 */
class AccountSetPrivacyRequest extends TLObject
{
    const CONSTRUCTOR = 0xc9f81ce8;

    const RULE_ALLOW_ALL       = 0x184b35ce;
    const RULE_ALLOW_CONTACTS  = 0x0d09e07b;
    const RULE_DISALLOW_ALL    = 0xd66b66c9;

    private int   $key;
    private array $rules;

    public function __construct(int $key, array $rules)
    {
        $this->key   = $key;
        $this->rules = $rules;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        $writer->writeInt($this->key);

        // Vector<InputPrivacyRule>
        $writer->writeInt(0x1cb5c415);
        $writer->writeInt(count($this->rules));
        foreach ($this->rules as $rule) {
            $writer->writeInt($rule);
        }
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return ['_' => 'account.setPrivacy', 'key' => $this->key, 'rules' => $this->rules];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
