<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\AccountUpdateProfileRequest;
use XnoxsProto\TL\Functions\AccountUpdateUsernameRequest;
use XnoxsProto\TL\Functions\AccountGetAuthorizationsRequest;
use XnoxsProto\TL\Functions\AccountResetAuthorizationRequest;
use XnoxsProto\TL\Functions\AccountGetPrivacyRequest;
use XnoxsProto\TL\Functions\AccountSetPrivacyRequest;
use XnoxsProto\TL\Functions\PhotosUploadProfilePhotoRequest;
use XnoxsProto\TL\Functions\PhotosGetUserPhotosRequest;
use XnoxsProto\TL\Functions\PhotosDeletePhotosRequest;
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

        $uploader = new \XnoxsProto\Upload\FileUploader($this->client);
        if ($onProgress !== null) {
            $uploader->onProgress($onProgress);
        }
        $uploaded = $uploader->upload($filePath);

        $request = new PhotosUploadProfilePhotoRequest($uploaded);
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

    /**
     * Ambil daftar foto profil akun sendiri.
     *
     * @param int $limit Maksimal jumlah foto yang diambil (default 100)
     * @return array[] Array of ['id', 'access_hash', 'file_reference', 'date']
     */
    public function getProfilePhotos(int $limit = 100): array
    {
        $sender  = $this->client->getSender();
        $request = new PhotosGetUserPhotosRequest(0, 0, $limit);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        $c = $response['constructor'];
        $r = $response['reader'];

        // photos.photos#8dca6aa5  atau  photos.photosSlice#15051f54
        if ($c === 0x15051f54) {
            $r->readInt(); // count (total, bukan jumlah yg dikembalikan)
        } elseif ($c !== 0x8dca6aa5) {
            return [];
        }

        // Vector<Photo>
        $r->readInt(); // vector ctor 0x1cb5c415
        $count  = $r->readInt();
        $photos = [];

        for ($i = 0; $i < $count; $i++) {
            $photoCtor = $r->readInt();

            // photoEmpty#2331b22d
            if ($photoCtor === 0x2331b22d) {
                $r->readLong(); // id
                continue;
            }

            // photo#fb197a65
            if ($photoCtor !== 0xfb197a65) {
                break; // konstruktor tidak dikenal, hentikan parsing
            }

            $flags          = $r->readInt();
            $id             = $r->readLong();
            $accessHash     = $r->readLong();
            $fileReference  = $r->readBytes();
            $date           = $r->readInt();

            // Lewati sizes: Vector<PhotoSize>
            $this->skipPhotoSizes($r);

            // Lewati video_sizes: flags.1?Vector<VideoSize>
            if ($flags & 2) {
                $this->skipVideoSizes($r);
            }

            // dc_id:int
            $r->readInt();

            $photos[] = [
                'id'             => $id,
                'access_hash'    => $accessHash,
                'file_reference' => $fileReference,
                'date'           => $date,
            ];
        }

        return $photos;
    }

    /**
     * Hapus satu foto profil berdasarkan photo_id.
     *
     * Gunakan getProfilePhotos() untuk mendapatkan ID foto yang tersedia.
     *
     * @param int $photoId ID foto yang ingin dihapus
     * @return bool true jika foto berhasil dihapus dari daftar yang dikembalikan server
     * @throws \InvalidArgumentException Jika foto tidak ditemukan di profil
     */
    public function deleteProfilePhoto(int $photoId): bool
    {
        $deleted = $this->deleteProfilePhotos([$photoId]);
        return in_array($photoId, $deleted, true);
    }

    /**
     * Hapus beberapa foto profil sekaligus berdasarkan array photo_id.
     *
     * @param int[] $photoIds Array ID foto yang ingin dihapus
     * @return int[] Array photo_id yang berhasil dihapus (dikembalikan server)
     * @throws \InvalidArgumentException Jika salah satu ID tidak ditemukan di profil
     */
    public function deleteProfilePhotos(array $photoIds): array
    {
        if (empty($photoIds)) {
            return [];
        }

        // Ambil semua foto profil untuk mendapatkan access_hash & file_reference
        $all    = $this->getProfilePhotos();
        $byId   = [];
        foreach ($all as $p) {
            $byId[$p['id']] = $p;
        }

        $toDelete = [];
        foreach ($photoIds as $pid) {
            if (!isset($byId[$pid])) {
                throw new \InvalidArgumentException("Foto dengan ID $pid tidak ditemukan di profil.");
            }
            $toDelete[] = $byId[$pid];
        }

        $sender  = $this->client->getSender();
        $request = new PhotosDeletePhotosRequest($toDelete);
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (RPCException $e) {
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        $c = $response['constructor'];
        $r = $response['reader'];

        // Respons: Vector<long> — $c sudah berisi vector ctor (0x1cb5c415),
        // reader langsung dimulai dari count (ctor sudah dikonsumsi sebelumnya).
        $n       = $r->readInt();
        $deleted = [];
        for ($i = 0; $i < $n; $i++) {
            $deleted[] = $r->readLong();
        }

        return $deleted;
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
        // Rule constructors yang membawa Vector<long> extra field
        $ctorsWithUsers = [
            0xb8905fb2, // privacyValueAllowUsers
            0xe4621141, // privacyValueDisallowUsers
        ];
        $ctorsWithChats = [
            0x6b134e8e, // privacyValueAllowChatParticipants
            0x41c87565, // privacyValueDisallowChatParticipants
        ];
        for ($i = 0; $i < $rCount; $i++) {
            $rCtor   = $r->readInt();
            $rules[] = $this->ruleCtorToString($rCtor);
            // Skip extra Vector<long> agar stream position tetap benar
            if (in_array($rCtor, $ctorsWithUsers, true) || in_array($rCtor, $ctorsWithChats, true)) {
                try {
                    $r->readInt(); // vector ctor
                    $vCount = $r->readInt();
                    for ($j = 0; $j < $vCount; $j++) $r->readLong();
                } catch (\Throwable) {}
            }
        }

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

    // =========================================================================
    // Skip helpers untuk parsing foto
    // =========================================================================

    private function skipPhotoSizes(BinaryReader $r): void
    {
        $r->readInt(); // vector ctor
        $n = $r->readInt();
        for ($i = 0; $i < $n; $i++) {
            $ctor = $r->readInt();
            match ($ctor) {
                // photoSizeEmpty#e17e23c — type:string
                0x0e17e23c => $r->readString(),
                // photoSize#75c2f7b8 — type:string w:int h:int size:int
                0x75c2f7b8 => ($r->readString() && $r->readInt() && $r->readInt() && $r->readInt()),
                // photoCachedSize#e9a734fa — type:string w:int h:int bytes:bytes
                0xe9a734fa => ($r->readString() && $r->readInt() && $r->readInt() && $r->readBytes()),
                // photoStrippedSize#e0fe0de — type:string bytes:bytes
                0x0e0fe0de => ($r->readString() && $r->readBytes()),
                // photoSizeProgressive#fa3efb95 — type:string w:int h:int sizes:Vector<int>
                0xfa3efb95 => (function () use ($r) {
                    $r->readString();
                    $r->readInt(); // w
                    $r->readInt(); // h
                    $r->readInt(); // vector ctor
                    $m = $r->readInt();
                    for ($j = 0; $j < $m; $j++) $r->readInt();
                })(),
                // photoPathSize#d8214d41 — type:string bytes:bytes
                0xd8214d41 => ($r->readString() && $r->readBytes()),
                default => null, // konstruktor tidak dikenal, hentikan
            };
        }
    }

    private function skipVideoSizes(BinaryReader $r): void
    {
        $r->readInt(); // vector ctor
        $n = $r->readInt();
        for ($i = 0; $i < $n; $i++) {
            $ctor = $r->readInt();
            // videoSize#de33b094 — type:string w:int h:int size:int video_start_ts:flags.0?double
            if ($ctor === 0xde33b094) {
                $flags = $r->readInt();
                $r->readString(); // type
                $r->readInt();    // w
                $r->readInt();    // h
                $r->readInt();    // size
                if ($flags & 1) $r->readDouble(); // video_start_ts
            }
            // videoSizeEmojiMarkup & videoSizeStickerMarkup — jarang di foto profil, abaikan
        }
    }

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
