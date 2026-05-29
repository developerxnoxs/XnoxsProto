<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\AccountUpdateProfileRequest;
use XnoxsProto\TL\Functions\AccountUpdateUsernameRequest;
use XnoxsProto\TL\Functions\AccountGetAuthorizationsRequest;
use XnoxsProto\TL\Functions\AccountResetAuthorizationRequest;
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;
use XnoxsProto\TL\Functions\AccountSetPrivacyRequest;
use XnoxsProto\TL\Functions\PhotosUploadProfilePhotoRequest;
use XnoxsProto\TL\Types\User;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\Exceptions\RPCException;

/**
 * Account management: profile, privacy, active sessions.
 */
class Account
{
    private TelegramClient $client;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    // =========================================================================
    // Profile
    // =========================================================================

    /**
     * Update first name, last name, and/or bio.
     * Pass null to leave a field unchanged.
     *
     * @return array Updated user info ['id','first_name','last_name','username']
     */
    public function updateProfile(
        ?string $firstName = null,
        ?string $lastName  = null,
        ?string $about     = null
    ): array {
        $sender  = $this->client->getSender();
        $request = new AccountUpdateProfileRequest($firstName, $lastName, $about);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        return $this->parseUserResponse($response);
    }

    /**
     * Change your Telegram username (@handle).
     * Pass empty string to remove the username.
     */
    public function updateUsername(string $username): array
    {
        $sender  = $this->client->getSender();
        $request = new AccountUpdateUsernameRequest($username);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        return $this->parseUserResponse($response);
    }

    /**
     * Upload a photo from local file and set it as profile picture.
     *
     * @param string        $filePath   Path to JPG/PNG file
     * @param callable|null $onProgress Optional progress callback fn(int $part, int $total, int $pct)
     * @return array ['photo_id', 'date']
     */
    public function uploadProfilePhoto(string $filePath, ?callable $onProgress = null): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: $filePath");
        }

        $uploader = new \XnoxsProto\Upload\FileUploader($this->client->getSender(), $this->client);
        $uploaded = $uploader->upload($filePath, $onProgress);

        $request = new PhotosUploadProfilePhotoRequest(
            $uploaded->id,
            $uploaded->parts,
            $uploaded->name,
            $uploaded->md5
        );
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $this->client->getSender()->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        $c = $response['constructor'];
        $r = $response['reader'];

        // photos.photo#20212ca8  photo:Photo users:Vector<User>
        if ($c === 0x20212ca8) {
            $photoCtor = $r->readInt();
            $photoId   = 0;
            if ($photoCtor === 0xfb197a65) {
                // photo#fb197a65
                $r->readInt(); // flags
                $photoId = $r->readLong(); // id
                // skip rest
            }
            return ['photo_id' => $photoId, 'date' => time()];
        }

        return ['photo_id' => 0, 'date' => time()];
    }

    // =========================================================================
    // Active Sessions
    // =========================================================================

    /**
     * Get all active authorized sessions.
     *
     * @return array[] Array of session info arrays:
     *   ['hash', 'current', 'official_app', 'password_pending',
     *    'device_model', 'platform', 'system_version', 'api_id',
     *    'app_name', 'app_version', 'date_created', 'date_active',
     *    'ip', 'country', 'region']
     */
    public function getAuthorizations(): array
    {
        $sender  = $this->client->getSender();
        $request = new AccountGetAuthorizationsRequest();
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        $c = $response['constructor'];
        $r = $response['reader'];

        // account.authorizations#4bff8ea0 authorization_ttl_days:int authorizations:Vector<Authorization>
        if ($c !== 0x4bff8ea0) {
            return [];
        }

        $ttlDays = $r->readInt();
        $r->readInt(); // vector ctor
        $count   = $r->readInt();

        $sessions = [];
        for ($i = 0; $i < $count; $i++) {
            $ctor = $r->readInt();
            // account.authorization#ad01d61d
            if ($ctor !== 0xad01d61d) break;

            $flags          = $r->readInt();
            $hash           = $r->readLong();
            $deviceModel    = $r->readString();
            $platform       = $r->readString();
            $systemVersion  = $r->readString();
            $apiId          = $r->readInt();
            $appName        = $r->readString();
            $appVersion     = $r->readString();
            $dateCreated    = $r->readInt();
            $dateActive     = $r->readInt();
            $ip             = $r->readString();
            $country        = $r->readString();
            $region         = $r->readString();

            $sessions[] = [
                'hash'             => $hash,
                'current'          => (bool)($flags & 1),
                'official_app'     => (bool)($flags & 2),
                'password_pending' => (bool)($flags & 4),
                'device_model'     => $deviceModel,
                'platform'         => $platform,
                'system_version'   => $systemVersion,
                'api_id'           => $apiId,
                'app_name'         => $appName,
                'app_version'      => $appVersion,
                'date_created'     => $dateCreated,
                'date_active'      => $dateActive,
                'ip'               => $ip,
                'country'          => $country,
                'region'           => $region,
            ];
        }

        return $sessions;
    }

    /**
     * Terminate (logout) a specific session by its hash.
     * Get hashes from getAuthorizations().
     */
    public function resetAuthorization(int $hash): bool
    {
        $sender  = $this->client->getSender();
        $request = new AccountResetAuthorizationRequest($hash);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        // Returns Bool: 0x997275b5 = true, 0xbc799737 = false
        return $response['constructor'] === 0x997275b5;
    }

    /**
     * Terminate all other active sessions (keep current one).
     *
     * @return int Number of terminated sessions
     */
    public function terminateAllOtherSessions(): int
    {
        $sessions   = $this->getAuthorizations();
        $terminated = 0;

        foreach ($sessions as $session) {
            if (!$session['current']) {
                try {
                    $this->resetAuthorization($session['hash']);
                    $terminated++;
                } catch (\Exception $e) {
                    // Skip errors
                }
            }
        }

        return $terminated;
    }

    // =========================================================================
    // Privacy
    // =========================================================================

    /**
     * Get privacy rules for a specific key.
     *
     * @param int $key One of AccountGetPrivacyRequest::KEY_* constants
     * @return array ['rules' => [...], 'users' => [...], 'chats' => [...]]
     */
    public function getPrivacy(int $key = AccountGetPrivacyRequest::KEY_STATUS_TIMESTAMP): array
    {
        $sender  = $this->client->getSender();
        $request = new AccountGetPrivacyRequest($key);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        $c = $response['constructor'];
        $r = $response['reader'];

        // account.privacyRules#50a04e45
        if ($c !== 0x50a04e45) {
            return ['rules' => [], 'users' => [], 'chats' => []];
        }

        $r->readInt(); // vector ctor for rules
        $rCount = $r->readInt();
        $rules  = [];
        for ($i = 0; $i < $rCount; $i++) {
            $rCtor   = $r->readInt();
            $rules[] = $this->ruleCtorToString($rCtor);
        }

        // Skip users vector
        try {
            $r->readInt();
            $uCount = $r->readInt();
            for ($i = 0; $i < $uCount; $i++) {
                $r->readInt(); // ctor
                // Skip user fields (variable length) — simplified
            }
        } catch (\Exception $e) {}

        return ['rules' => $rules];
    }

    /**
     * Set privacy rule for a key.
     *
     * @param int   $key   One of AccountGetPrivacyRequest::KEY_* constants
     * @param int[] $rules Array of AccountSetPrivacyRequest::RULE_* constants
     */
    public function setPrivacy(int $key, array $rules): bool
    {
        // Map string rule names to integer constructors (so callers can use either)
        $ruleMap = [
            'allow_all'         => AccountSetPrivacyRequest::RULE_ALLOW_ALL,
            'allow_contacts'    => AccountSetPrivacyRequest::RULE_ALLOW_CONTACTS,
            'disallow_all'      => AccountSetPrivacyRequest::RULE_DISALLOW_ALL,
        ];
        $intRules = [];
        foreach ($rules as $rule) {
            if (is_string($rule) && isset($ruleMap[$rule])) {
                $intRules[] = $ruleMap[$rule];
            } elseif (is_int($rule)) {
                $intRules[] = $rule;
            }
        }
        if (empty($intRules)) {
            throw new \InvalidArgumentException('setPrivacy: no valid rules provided');
        }

        $sender  = $this->client->getSender();
        $request = new AccountSetPrivacyRequest($key, $intRules);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        return $response['constructor'] === 0x50a04e45;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function parseUserResponse(array $response): array
    {
        $c = $response['constructor'];
        $r = $response['reader'];

        if ($c === User::CONSTRUCTOR_EMPTY) {
            return [];
        }

        try {
            $user = User::fromReader($r);
            return [
                'id'         => $user->id,
                'first_name' => $user->firstName ?? '',
                'last_name'  => $user->lastName  ?? '',
                'username'   => $user->username,
                'phone'      => $user->phone,
            ];
        } catch (\Exception $e) {
            return ['raw_constructor' => sprintf('0x%08x', $c)];
        }
    }

    private function ruleCtorToString(int $ctor): string
    {
        return match ($ctor) {
            // privacyValue* — dari TL schema resmi Layer 214
            0x65427b82 => 'allow_all',
            0xfffe1bac => 'allow_contacts',
            0xf7e8d89b => 'allow_close_friends',
            0xece9814b => 'allow_premium',
            0x21461b5d => 'allow_bots',
            0xb8905fb2 => 'allow_users',
            0x6b134e8e => 'allow_chat_participants',
            0x8b73e763 => 'disallow_all',
            0xf888fa1a => 'disallow_contacts',
            0xf6a5f82f => 'disallow_bots',
            0xe4621141 => 'disallow_users',
            0x41c87565 => 'disallow_chat_participants',
            default     => sprintf('0x%08x', $ctor),
        };
    }
}
