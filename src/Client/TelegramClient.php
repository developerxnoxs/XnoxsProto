<?php

namespace XnoxsProto\Client;

use XnoxsProto\Sessions\AbstractSession;
use XnoxsProto\Sessions\MemorySession;
use XnoxsProto\Network\Connection;
use XnoxsProto\Network\Socks5Connection;
use XnoxsProto\Network\Authenticator;
use XnoxsProto\Network\MTProtoSender;
use XnoxsProto\Crypto\RSA;
use XnoxsProto\Crypto\AuthKey;
use XnoxsProto\Network\LayerDetector;
use XnoxsProto\TL\Functions\InvokeWithLayerRequest;
use XnoxsProto\TL\Functions\InitConnectionRequest;
use XnoxsProto\TL\Functions\UsersGetSelfRequest;
use XnoxsProto\TL\Functions\MessagesGetBotCallbackAnswerRequest;
use XnoxsProto\TL\Functions\MessagesForwardMessagesRequest;
use XnoxsProto\TL\Functions\ChannelsJoinChannelRequest;
use XnoxsProto\TL\Functions\ChannelsLeaveChannelRequest;
use XnoxsProto\TL\Functions\MessagesImportChatInviteRequest;
use XnoxsProto\TL\Functions\MessagesStartBotRequest;
use XnoxsProto\TL\Functions\MessagesDeleteMessagesRequest;
use XnoxsProto\TL\Functions\ChannelsDeleteMessagesRequest;
use XnoxsProto\TL\Functions\MessagesEditMessageRequest;
use XnoxsProto\TL\Functions\ChannelsEditAdminRequest;
use XnoxsProto\TL\Functions\ChannelsEditBannedRequest;
use XnoxsProto\TL\Functions\MessagesEditChatAdminRequest;
use XnoxsProto\TL\Functions\MessagesDeleteChatUserRequest;
use XnoxsProto\TL\Functions\MessagesUpdatePinnedMessageRequest;
use XnoxsProto\TL\Functions\ChannelsUpdatePinnedMessageRequest;
use XnoxsProto\TL\Functions\MessagesCreateChatRequest;
use XnoxsProto\TL\Functions\ChannelsCreateChannelRequest;
use XnoxsProto\TL\Functions\MessagesDeleteChatRequest;
use XnoxsProto\TL\Functions\ChannelsDeleteChannelRequest;
use XnoxsProto\TL\Functions\MessagesMigrateChatRequest;
use XnoxsProto\TL\Functions\MessagesEditChatTitleRequest;
use XnoxsProto\TL\Functions\ChannelsEditTitleRequest;
use XnoxsProto\TL\Functions\ChannelsEditAboutRequest;
use XnoxsProto\TL\Functions\MessagesAddChatUserRequest;
use XnoxsProto\TL\Functions\ChannelsToggleSlowModeRequest;
use XnoxsProto\TL\Functions\MessagesExportChatInviteRequest;
use XnoxsProto\TL\Functions\MessagesEditChatDefaultBannedRightsRequest;
use XnoxsProto\TL\Functions\ChannelsToggleSignaturesRequest;
use XnoxsProto\TL\Functions\ChannelsToggleJoinToSendRequest;
use XnoxsProto\TL\Functions\ChannelsToggleJoinRequestRequest;
use XnoxsProto\TL\Functions\MessagesSearchRequest;
use XnoxsProto\TL\Functions\MessagesSearchGlobalRequest;
use XnoxsProto\TL\Functions\AuthExportAuthorizationRequest;
use XnoxsProto\TL\Functions\AuthImportAuthorizationRequest;
use XnoxsProto\TL\Functions\UsersGetFullUserRequest;
use XnoxsProto\TL\Functions\MessagesGetFullChatRequest;
use XnoxsProto\TL\Functions\ChannelsGetFullChannelRequest;
use XnoxsProto\TL\Types\InputPeer;
use XnoxsProto\TL\Types\InputMediaPoll;
use XnoxsProto\TL\Types\User;
use XnoxsProto\TL\Types\Chat;
use XnoxsProto\TL\Functions\ChannelsGetParticipantsRequest;
use XnoxsProto\TL\Functions\UpdatesGetStateRequest;
use XnoxsProto\TL\Types\FullMessage;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Parser\TLSkipHelper;
use XnoxsProto\Events\NewMessage as NewMessageFilter;
use XnoxsProto\Events\NewMessageEvent;
use XnoxsProto\Events\RawUpdateEvent;

class TelegramClient
{
    // =========================================================================
    // Konstanta Admin Rights — gunakan di promoteAdmin()
    // Tidak perlu import class lain, cukup pakai TelegramClient::ADMIN_*
    // =========================================================================

    /** Ubah nama, foto, deskripsi grup */
    const ADMIN_CHANGE_INFO     = 0x00001;
    /** Kirim pesan di channel broadcast */
    const ADMIN_POST_MESSAGES   = 0x00002;
    /** Edit pesan yang sudah dikirim (channel) */
    const ADMIN_EDIT_MESSAGES   = 0x00004;
    /** Hapus pesan anggota lain */
    const ADMIN_DELETE_MESSAGES = 0x00008;
    /** Ban / restrict anggota */
    const ADMIN_BAN_USERS       = 0x00010;
    /** Undang anggota baru */
    const ADMIN_INVITE_USERS    = 0x00020;
    /** Pin pesan */
    const ADMIN_PIN_MESSAGES    = 0x00080;
    /** Jadikan anggota lain sebagai admin */
    const ADMIN_ADD_ADMINS      = 0x00200;
    /** Posting anonim atas nama grup */
    const ADMIN_ANONYMOUS       = 0x00400;
    /** Kelola video call / live stream */
    const ADMIN_MANAGE_CALL     = 0x00800;
    /** Wajib diset agar status admin aktif */
    const ADMIN_OTHER           = 0x01000;
    /** Kelola topik di forum */
    const ADMIN_MANAGE_TOPICS   = 0x02000;

    // =========================================================================
    // Konstanta Ban/Restrict — gunakan di restrictUser()
    // Tidak perlu import class lain, cukup pakai TelegramClient::BAN_*
    // =========================================================================

    /** Larang melihat pesan (ban total) */
    const BAN_VIEW_MESSAGES  = 0x000001;
    /** Larang kirim pesan teks (mute) */
    const BAN_SEND_MESSAGES  = 0x000002;
    /** Larang kirim semua media */
    const BAN_SEND_MEDIA     = 0x000004;
    /** Larang kirim stiker */
    const BAN_SEND_STICKERS  = 0x000008;
    /** Larang kirim GIF */
    const BAN_SEND_GIFS      = 0x000010;
    /** Larang main game Telegram */
    const BAN_SEND_GAMES     = 0x000020;
    /** Larang pakai inline bot */
    const BAN_SEND_INLINE    = 0x000040;
    /** Larang kirim link/URL */
    const BAN_EMBED_LINKS    = 0x000080;
    /** Larang buat polling */
    const BAN_SEND_POLLS     = 0x000100;
    /** Larang ubah info grup */
    const BAN_CHANGE_INFO    = 0x000400;
    /** Larang undang anggota */
    const BAN_INVITE_USERS   = 0x008000;
    /** Larang pin pesan */
    const BAN_PIN_MESSAGES   = 0x020000;
    /** Larang kirim foto */
    const BAN_SEND_PHOTOS    = 0x080000;
    /** Larang kirim video */
    const BAN_SEND_VIDEOS    = 0x100000;
    /** Larang kirim audio */
    const BAN_SEND_AUDIOS    = 0x400000;
    /** Larang kirim dokumen/file */
    const BAN_SEND_DOCS      = 0x800000;

    private int $apiId;
    private string $apiHash;
    private AbstractSession $session;
    private ?Connection $connection = null;
    private ?Auth $auth = null;
    private ?Messages $messages = null;
    private ?Account $account = null;
    private ?FileDownloader $downloader = null;
    private ?MTProtoSender $sender = null;
    private int  $timeOffset      = 0;
    private bool $isFirstRequest  = true;
    private int  $detectedLayer   = self::LAYER;

    // Proxy configuration (null = no proxy)
    private ?array $proxyConfig = null;

    // Event system
    private array $eventHandlers    = [];
    private array $rawUpdateHandlers = [];
    private bool  $shouldStop       = false;

    // Peer resolution cache [username/phone/id_key => ['type','id','access_hash']]
    private array $peerCache = [];

    // Directory where phone-based sessions are auto-stored.
    // Default: getcwd()/sessions — configurable via setSessionsDir().
    private static ?string $sessionsDir = null;

    private const LAYER = 214;

    private const DC_OPTIONS = [
        1 => ['ip' => '149.154.175.53',  'port' => 443],
        2 => ['ip' => '149.154.167.51',  'port' => 443],
        3 => ['ip' => '149.154.175.100', 'port' => 443],
        4 => ['ip' => '149.154.167.91',  'port' => 443],
        5 => ['ip' => '91.108.56.130',   'port' => 443],
    ];

    /**
     * Buat Telegram client.
     *
     * $session bisa berupa:
     *   - string           → nama sesi, otomatis pakai FileSession (disimpan ke file .session)
     *   - AbstractSession  → objek sesi custom
     *   - null             → MemorySession (tidak disimpan saat script selesai)
     *
     * Contoh paling mudah:
     *   $client = new TelegramClient(API_ID, API_HASH, 'my_account');
     */
    public function __construct(int $apiId, string $apiHash, string|AbstractSession|null $session = null)
    {
        $this->apiId   = $apiId;
        $this->apiHash = $apiHash;

        if (is_string($session)) {
            $file          = str_ends_with($session, '.session') || str_ends_with($session, '.json')
                             ? $session
                             : $session . '.session';
            $this->session = new \XnoxsProto\Sessions\FileSession($file);
        } else {
            $this->session = $session ?? new MemorySession();
        }

        RSA::initDefaultKeys();
        $this->auth       = new Auth($this);
        $this->messages   = new Messages($this);
        $this->account    = new Account($this);
        $this->downloader = new FileDownloader($this);
    }

    /**
     * Factory shortcut — paling mudah untuk pemula.
     *
     * Contoh:
     *   $client = TelegramClient::create(API_ID, API_HASH);
     *   $client->start('+62812...');
     *   // Session otomatis disimpan ke sessions/session_628xxx.json
     *   // 2FA otomatis ditangani (prompt STDIN)
     *
     * @param int                       $apiId       API ID dari my.telegram.org/apps
     * @param string                    $apiHash     API Hash dari my.telegram.org/apps
     * @param string|AbstractSession|null $session   Opsional: nama/objek session custom.
     *                                               Jika null (default), session dibuat otomatis
     *                                               berdasarkan nomor telepon saat start() dipanggil.
     */
    public static function create(int $apiId, string $apiHash, string|AbstractSession|null $session = null): static
    {
        return new static($apiId, $apiHash, $session);
    }

    /**
     * Ubah direktori tempat session phone-based disimpan.
     * Default: getcwd()/sessions
     *
     * Panggil sebelum start():
     *   TelegramClient::setSessionsDir('/var/lib/myapp/sessions');
     */
    public static function setSessionsDir(string $dir): void
    {
        self::$sessionsDir = $dir;
    }

    // =========================================================================
    // Connection management
    // =========================================================================

    public function connect(?int $dcId = null, bool $isReconnect = false): void
    {
        if ($dcId === null) {
            $savedDC = $this->session->getDC();
            $dcId    = $savedDC['dc_id'] ?? 2;
        }

        if (!isset(self::DC_OPTIONS[$dcId])) {
            throw new \InvalidArgumentException("Invalid DC ID: $dcId");
        }

        if ($isReconnect && $this->connection) {
            $this->connection->close();
        }

        $dc = self::DC_OPTIONS[$dcId];

        if ($this->proxyConfig !== null) {
            $this->connection = new Socks5Connection(
                $dc['ip'],
                $dc['port'],
                $this->proxyConfig['host'],
                $this->proxyConfig['port'],
                $this->proxyConfig['user'] ?? null,
                $this->proxyConfig['pass'] ?? null
            );
        } else {
            $this->connection = new Connection($dc['ip'], $dc['port']);
        }

        $this->connection->connect();

        $this->session->setDC($dcId, $dc['ip'], $dc['port']);

        if ($this->session->getAuthKey() === null || $isReconnect) {
            $authenticator = new Authenticator($this->connection);
            $authKey       = $authenticator->doAuthentication();
            $this->session->setAuthKey($authKey->getKey());
            $this->timeOffset = $authenticator->getTimeOffset();
        }

        $authKeyObj   = new AuthKey($this->session->getAuthKey());
        $this->sender = new MTProtoSender($this->connection, $authKeyObj, $this->timeOffset);

        // ── Auto-detect the highest API layer supported by this Telegram server ──
        //
        // Cases:
        //   A) Returning session, same TCP lifetime (cachedLayer set, !isReconnect):
        //      Use cached layer. initConnection will be sent with the first real
        //      API call via wrapFirstRequest() — no extra round-trip.
        //
        //   B) New session OR forced reconnect:
        //      Run LayerDetector which sends invokeWithLayer + initConnection +
        //      help.getNearestDc. This IS the initConnection, so isFirstRequest = false.
        //      If detection fails, fall back to LAYER constant; isFirstRequest stays true
        //      so the first real API call still sends initConnection.
        $cachedLayer = $this->session->getLayer();

        if ($cachedLayer !== null && !$isReconnect) {
            // Case A: reuse cached layer; first real API call sends initConnection.
            $this->detectedLayer  = $cachedLayer;
            $this->isFirstRequest = true;
        } else {
            // Case B: detect layer (also serves as the initConnection round-trip).
            $detectionOk = false;
            try {
                $info = LayerDetector::detect($this->sender, $this->apiId, self::LAYER);
                $this->detectedLayer = $info['layer'];
                $this->session->setLayer($info['layer']);
                $detectionOk = true;
            } catch (\Exception) {
                // Network hiccup or unknown response — fall back to compile-time constant.
                $this->detectedLayer = self::LAYER;
            }
            // If detection sent initConnection successfully, no need to wrap again.
            // If it failed, wrapFirstRequest() will send initConnection on the next call.
            $this->isFirstRequest = !$detectionOk;
        }
    }

    /**
     * Wrap a request with invokeWithLayer + initConnection on the very first call
     * of a new session (when the layer could NOT be auto-detected at connect time).
     *
     * Under normal operation isFirstRequest is always false after connect(), so this
     * method simply returns the request as-is.  It remains here as a safety net.
     */
    public function wrapFirstRequest($request)
    {
        if (!$this->isFirstRequest) return $request;
        $this->isFirstRequest = false;

        return new InvokeWithLayerRequest(
            $this->detectedLayer,
            new InitConnectionRequest(
                $this->apiId,
                'XnoxsProto',
                php_uname('s') . ' ' . php_uname('r'),
                '1.0.0',
                'en', '', 'en',
                $request
            )
        );
    }

    /**
     * Returns the API layer that was negotiated with Telegram during the last connect().
     */
    public function getLayer(): int
    {
        return $this->detectedLayer;
    }

    /**
     * Configure SOCKS5 proxy. Must be called before connect().
     *
     * @param string      $host Proxy host/IP
     * @param int         $port Proxy port
     * @param string|null $user Username (optional)
     * @param string|null $pass Password (optional)
     */
    public function setProxy(string $host, int $port, ?string $user = null, ?string $pass = null): void
    {
        $this->proxyConfig = compact('host', 'port', 'user', 'pass');
    }

    /**
     * Remove proxy configuration.
     */
    public function clearProxy(): void
    {
        $this->proxyConfig = null;
    }

    /**
     * Start client and login if not already authorized.
     *
     * For user accounts:
     *   $client->start('+6281234567890');  // prompts for code on STDIN
     *   $client->start('+62...', fn() => trim(fgets(STDIN)));
     *
     * For bots:
     *   $client->start(botToken: '123456:ABC...');
     *
     * @param string        $phone            Nomor telepon (contoh: '+6281234567890')
     * @param callable|null $codeCallback     Callable yang mengembalikan kode OTP.
     *                                        Jika null, prompt otomatis via STDIN.
     * @param callable|null $passwordCallback Callable yang mengembalikan password 2FA jika diperlukan.
     *                                        Jika null, prompt otomatis via STDIN.
     * @param string        $botToken         Bot token dari @BotFather (alternatif phone)
     */
    public function start(
        string    $phone            = '',
        ?callable $codeCallback     = null,
        ?callable $passwordCallback = null,
        string    $botToken         = ''
    ): void {
        // ── Step 1: Auto-setup phone-based session sebelum connect ────────
        // Hanya aktif jika session saat ini masih MemorySession (default).
        // Jika user sudah pass FileSession eksplisit, tidak diubah.
        if (!empty($phone)) {
            $this->ensurePhoneSession($phone);
        }

        // ── Step 2: Connect (menggunakan session yang sudah di-setup) ─────
        if (!$this->isConnected()) {
            $this->connect();
        }

        // ── Step 3: Sudah login? Sync state lalu selesai ─────────────────
        if ($this->auth->isAuthorized()) {
            $this->syncUpdateState();
            return;
        }

        // ── Step 4: Bot login ─────────────────────────────────────────────
        if (!empty($botToken)) {
            $this->auth->loginAsBot($botToken);
            $this->syncUpdateState();
            return;
        }

        if (empty($phone)) {
            throw new \InvalidArgumentException('Phone number or bot token required for first login');
        }

        // ── Step 5: Kirim kode OTP ────────────────────────────────────────
        $sentCode = $this->auth->sendCode($phone);

        // ── Step 6: Baca kode OTP ─────────────────────────────────────────
        if ($codeCallback !== null) {
            $code = $codeCallback();
        } else {
            echo "✅  Kode dikirim! Cek SMS atau aplikasi Telegram.\n";
            echo "🔑  Masukkan kode verifikasi: ";
            $code = trim(fgets(STDIN) ?: '');
        }

        // ── Step 7: Sign in + handle 2FA otomatis ────────────────────────
        try {
            $this->auth->signIn($phone, $sentCode['phone_code_hash'], $code);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 401 && str_contains($e->getMessage(), 'SESSION_PASSWORD_NEEDED')) {
                // 2FA diperlukan — prompt otomatis atau gunakan callback
                if ($passwordCallback !== null) {
                    $password = $passwordCallback();
                } else {
                    echo "🔒  Akun ini dilindungi Two-Step Verification (2FA).\n";
                    echo "🔑  Masukkan password 2FA: ";
                    $password = trim(fgets(STDIN) ?: '');
                }
                $this->auth->checkPassword($password);
            } else {
                throw $e;
            }
        }

        // ── Step 8: Sync update state agar server mulai push update ──────
        $this->syncUpdateState();
    }

    /**
     * Panggil updates.getState agar server Telegram tahu client siap menerima update.
     * Wajib dipanggil sekali setelah login — tanpa ini server tidak push update baru.
     */
    private function syncUpdateState(): void
    {
        try {
            $req = new UpdatesGetStateRequest();
            $req = $this->wrapFirstRequest($req);
            $this->sender->send($req);
        } catch (\Throwable) {
            // Non-fatal — jika gagal, update mungkin tidak terkirim tapi tidak crash
        }
    }

    /**
     * Auto-upgrade session dari MemorySession ke FileSession berbasis nomor telepon.
     *
     * Dipanggil otomatis oleh start() — tidak perlu dipanggil manual.
     * Session disimpan ke: {sessionsDir}/session_{phone}.json
     *
     * Tidak melakukan apa-apa jika session sudah berupa FileSession
     * (artinya user sudah set session secara eksplisit).
     */
    private function ensurePhoneSession(string $phone): void
    {
        // Hanya upgrade MemorySession — jangan timpa FileSession yang sudah diset user
        if (!($this->session instanceof MemorySession)) {
            return;
        }

        // Normalisasi nomor telepon
        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }
        $phoneClean = preg_replace('/[^0-9]/', '', $phone);

        // Buat folder sessions/ jika belum ada
        $dir = self::$sessionsDir ?? (getcwd() . DIRECTORY_SEPARATOR . 'sessions');
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Gagal membuat folder session: {$dir}");
            }
        }

        $sessionFile   = $dir . DIRECTORY_SEPARATOR . $phoneClean . '.session';
        $this->session = new \XnoxsProto\Sessions\FileSession($sessionFile);

        // Jika sudah terhubung dengan session lama (MemorySession), putus dulu
        // agar connect() berikutnya menggunakan auth key dari FileSession
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
            $this->connection = null;
            $this->sender     = null;
            $this->isFirstRequest = true;
        }
    }

    public function disconnect(): void
    {
        $this->shouldStop = true;
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    // =========================================================================
    // Getters (backward compat)
    // =========================================================================

    public function getSession(): AbstractSession      { return $this->session; }
    public function getAuth(): Auth                    { return $this->auth; }
    public function getMessages(): Messages            { return $this->messages; }
    public function getAccount(): Account              { return $this->account; }
    public function getDownloader(): FileDownloader    { return $this->downloader; }
    public function getSender(): ?MTProtoSender        { return $this->sender; }
    public function getApiId(): int                    { return $this->apiId; }
    public function getApiHash(): string               { return $this->apiHash; }
    public function isFirstRequest(): bool             { return $this->isFirstRequest; }
    public function markFirstRequestSent(): void       { $this->isFirstRequest = false; }

    // =========================================================================
    // getMe() — equivalent to Telethon's await client.get_me()
    // Returns array: ['id','first_name','last_name','username','phone','bot']
    // =========================================================================

    public function getMe(): array
    {
        $this->assertReady();

        $request = new UsersGetSelfRequest();
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $c      = $response['constructor'];
        $reader = $response['reader'];

        // Vector<User> → constructor 0x1cb5c415
        if ($c === 0x1cb5c415) {
            $count = $reader->readInt();
            if ($count > 0) {
                $uCtor = $reader->readInt();
                if ($uCtor !== User::CONSTRUCTOR_EMPTY) {
                    $user = User::fromReader($reader);
                    $me   = $this->userToArray($user);
                    $this->cacheUserPeer($user);
                    return $me;
                }
            }
        }

        throw new \RuntimeException(sprintf('Unexpected getMe response constructor: 0x%08x', $c));
    }

    // =========================================================================
    // invoke() — equivalent to Telethon's await client(Request(...))
    // =========================================================================

    public function invoke($request): array
    {
        $this->assertReady();
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return [
            'constructor' => $response['constructor'],
            'raw'         => $response,
        ];
    }

    // =========================================================================
    // sendMessage() — equivalent to Telethon's await client.send_message(peer, text)
    // peer can be '@username', '+phone', int user_id, or InputPeer
    // =========================================================================

    public function sendMessage(string|int|InputPeer $peer, string $text, ?int $replyTo = null): array
    {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendMessage($inputPeer, $text, $replyTo);
    }

    // =========================================================================
    // sendFile() — auto-detect tipe media dari ekstensi/MIME, lalu upload & kirim
    // Setara dengan: await client.send_file(peer, file, caption)
    // =========================================================================

    /**
     * Upload & kirim file dengan deteksi tipe otomatis.
     *
     * Aturan deteksi:
     *   JPG / PNG / WebP → foto (tampil inline)
     *   MP4 / MOV / AVI  → video (player inline)
     *   MP3 / OGG / FLAC → audio (player audio)
     *   GIF / lainnya    → dokumen
     *
     * @param string|int|InputPeer $peer          Username, nomor, ID, atau InputPeer
     * @param string               $filePath      Path file lokal
     * @param string               $caption       Caption / teks di bawah media
     * @param bool                 $forceDocument Paksa kirim sebagai dokumen (bypass auto-detect)
     * @param int|null             $replyTo       ID pesan yang dibalas (opsional)
     * @param callable|null        $onProgress    Callback progress: fn(int $part, int $total, int $pct)
     * @return array               ['sent', 'message_id', 'date', 'caption', 'type', 'mime', 'filename']
     */
    public function sendFile(
        string|int|InputPeer $peer,
        string               $filePath,
        string               $caption       = '',
        bool                 $forceDocument = false,
        ?int                 $replyTo       = null,
        ?callable            $onProgress    = null
    ): array {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendFile($inputPeer, $filePath, $caption, $forceDocument, $replyTo, $onProgress);
    }

    // =========================================================================
    // sendPhoto() — upload & kirim foto
    // =========================================================================

    /**
     * Upload & kirim foto (JPG, PNG, WebP — ditampilkan inline di chat).
     *
     * @param string|int|InputPeer $peer     Tujuan pengiriman
     * @param string               $filePath Path file gambar
     * @param string               $caption  Caption foto (opsional)
     * @param int|null             $replyTo  ID pesan yang dibalas (opsional)
     * @param callable|null        $onProgress Progress callback
     * @return array ['sent', 'message_id', 'date', 'caption', 'type']
     */
    public function sendPhoto(
        string|int|InputPeer $peer,
        string               $filePath,
        string               $caption    = '',
        ?int                 $replyTo    = null,
        ?callable            $onProgress = null
    ): array {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendPhoto($inputPeer, $filePath, $caption, $replyTo, $onProgress);
    }

    // =========================================================================
    // sendVideo() — upload & kirim video
    // =========================================================================

    /**
     * Upload & kirim video (MP4, MOV, AVI, MKV, dll.).
     *
     * @param string|int|InputPeer $peer      Tujuan pengiriman
     * @param string               $filePath  Path file video
     * @param string               $caption   Caption video (opsional)
     * @param float                $duration  Durasi dalam detik (0 = auto via ffprobe)
     * @param int                  $width     Lebar frame (0 = auto)
     * @param int                  $height    Tinggi frame (0 = auto)
     * @param int|null             $replyTo   ID pesan yang dibalas (opsional)
     * @param callable|null        $onProgress Progress callback
     * @return array
     */
    public function sendVideo(
        string|int|InputPeer $peer,
        string               $filePath,
        string               $caption    = '',
        float                $duration   = 0.0,
        int                  $width      = 0,
        int                  $height     = 0,
        ?int                 $replyTo    = null,
        ?callable            $onProgress = null
    ): array {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendVideo($inputPeer, $filePath, $caption, $duration, $width, $height, $replyTo, $onProgress);
    }

    // =========================================================================
    // sendAudio() — upload & kirim audio
    // =========================================================================

    /**
     * Upload & kirim audio (MP3, OGG, FLAC, WAV, dll.).
     *
     * @param string|int|InputPeer $peer       Tujuan pengiriman
     * @param string               $filePath   Path file audio
     * @param string               $caption    Caption audio (opsional)
     * @param int                  $duration   Durasi dalam detik (0 = auto via ffprobe)
     * @param string               $title      Judul lagu (opsional, untuk ID3 tag)
     * @param string               $performer  Nama artis (opsional)
     * @param int|null             $replyTo    ID pesan yang dibalas (opsional)
     * @param callable|null        $onProgress Progress callback
     * @return array
     */
    public function sendAudio(
        string|int|InputPeer $peer,
        string               $filePath,
        string               $caption    = '',
        int                  $duration   = 0,
        string               $title      = '',
        string               $performer  = '',
        ?int                 $replyTo    = null,
        ?callable            $onProgress = null
    ): array {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendAudio($inputPeer, $filePath, $caption, $duration, $title, $performer, $replyTo, $onProgress);
    }

    // =========================================================================
    // sendDocument() — upload & kirim file sebagai dokumen
    // =========================================================================

    /**
     * Upload & kirim file sebagai dokumen (tampil dengan ikon file, bukan inline).
     * Cocok untuk PDF, ZIP, APK, atau file apapun yang ingin dikirim apa adanya.
     *
     * @param string|int|InputPeer $peer      Tujuan pengiriman
     * @param string               $filePath  Path file lokal
     * @param string               $caption   Caption dokumen (opsional)
     * @param string               $filename  Nama file di chat (default: nama asli)
     * @param int|null             $replyTo   ID pesan yang dibalas (opsional)
     * @param callable|null        $onProgress Progress callback
     * @return array
     */
    public function sendDocument(
        string|int|InputPeer $peer,
        string               $filePath,
        string               $caption    = '',
        string               $filename   = '',
        ?int                 $replyTo    = null,
        ?callable            $onProgress = null
    ): array {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendDocument($inputPeer, $filePath, $caption, $filename, $replyTo, $onProgress);
    }

    // =========================================================================
    // deleteMessages() — delete messages by IDs
    // For channels use channelId; for user chats/groups pass channelId = null
    // =========================================================================

    /**
     * Delete messages.
     *
     * For user DMs and basic groups:
     *   $client->deleteMessages([101, 102, 103]);
     *
     * For channels/supergroups (pass the channel peer):
     *   $client->deleteMessages([101, 102], $client->resolvePeer('@channel'));
     *
     * @param int[]                     $ids      Message IDs to delete
     * @param InputPeer|string|int|null $peer     Required for channel messages; null for user/group
     * @param bool                      $revoke   Delete for everyone (default true)
     */
    public function deleteMessages(array $ids, string|int|InputPeer|null $peer = null, bool $revoke = true): array
    {
        $this->assertReady();

        if ($peer !== null) {
            $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
            $request   = new ChannelsDeleteMessagesRequest($inputPeer, $ids);
        } else {
            $request = new MessagesDeleteMessagesRequest($ids, $revoke);
        }

        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['deleted' => true, 'ids' => $ids];
    }

    // =========================================================================
    // editMessage() — edit text of an existing message
    // =========================================================================

    /**
     * Edit a message's text.
     *
     * @param string|int|InputPeer $peer    Chat where the message is
     * @param int                  $msgId   Message ID to edit
     * @param string               $text    New text
     * @return array ['edited' => true, 'message_id' => int]
     */
    public function editMessage(string|int|InputPeer $peer, int $msgId, string $text): array
    {
        $this->assertReady();
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        $request   = new MessagesEditMessageRequest($inputPeer, $msgId, $text);
        $request   = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['edited' => true, 'message_id' => $msgId];
    }

    // =========================================================================
    // downloadMedia() — download file from a message or document
    // =========================================================================

    /**
     * Download media from a message to a local file.
     *
     * @param array         $message    Message array from getHistory()
     * @param string        $savePath   Local path to save the file
     * @param callable|null $onProgress fn(int $received, int $total, int $pct)
     * @return string       The path where the file was saved
     */
    public function downloadMedia(array $message, string $savePath, ?callable $onProgress = null): string
    {
        return $this->downloader->downloadMedia($message, $savePath, $onProgress);
    }

    /**
     * Download a document by ID, access hash, and file reference.
     *
     * @param int|null $dcId      DC tempat file disimpan (null = auto dari session)
     * @param int      $totalSize Ukuran file total dalam bytes (untuk progress %)
     */
    public function downloadDocument(
        int       $docId,
        int       $accessHash,
        string    $fileRef,
        string    $savePath,
        ?callable $onProgress = null,
        ?int      $dcId       = null,
        int       $totalSize  = 0
    ): string {
        return $this->downloader->downloadDocument($docId, $accessHash, $fileRef, $savePath, $onProgress, $dcId, $totalSize);
    }

    /**
     * Download a photo by ID, access hash, and file reference.
     *
     * @param int|null $dcId DC tempat file disimpan (null = auto dari session)
     */
    public function downloadPhoto(
        int       $photoId,
        int       $accessHash,
        string    $fileRef,
        string    $savePath,
        ?callable $onProgress = null,
        string    $thumbSize  = 'y',
        ?int      $dcId       = null
    ): string {
        return $this->downloader->downloadPhoto($photoId, $accessHash, $fileRef, $savePath, $onProgress, $thumbSize, $dcId);
    }

    // =========================================================================
    // forwardMessages() — equivalent to Telethon's await client.forward_messages(to, ids, from)
    // =========================================================================

    public function forwardMessages(
        string|int|InputPeer $to,
        array                $ids,
        string|int|InputPeer $from,
        bool                 $dropAuthor = false
    ): array {
        $this->assertReady();

        $toPeer   = $to   instanceof InputPeer ? $to   : $this->resolvePeer($to);
        $fromPeer = $from instanceof InputPeer ? $from : $this->resolvePeer($from);

        $request = new MessagesForwardMessagesRequest($fromPeer, $ids, $toPeer, $dropAuthor);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['forwarded' => true, 'ids' => $ids];
    }

    // =========================================================================
    // joinChannel() — equivalent to Telethon's await client(JoinChannelRequest(url))
    // Accepts @username, t.me/username, or t.me/joinchat/hash or t.me/+hash
    // =========================================================================

    public function joinChannel(string $peer): array
    {
        $this->assertReady();

        // Detect invite link: t.me/joinchat/HASH or t.me/+HASH
        if (preg_match('~t\.me/joinchat/([A-Za-z0-9_-]+)~', $peer, $m) ||
            preg_match('~t\.me/\+([A-Za-z0-9_-]+)~', $peer, $m)) {
            $request = new MessagesImportChatInviteRequest($m[1]);
            $request = $this->wrapFirstRequest($request);
            try {
                $this->sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
            }
            return ['joined' => true, 'via' => 'invite_link'];
        }

        // Regular @username or t.me/username
        $resolved = $this->resolvePeer($peer);
        $info     = $this->peerCacheGet($peer);

        if (!$info || $info['type'] !== 'channel') {
            throw new \RuntimeException("joinChannel: '$peer' is not a channel/supergroup");
        }

        $request = new ChannelsJoinChannelRequest($info['id'], $info['access_hash'] ?? 0);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['joined' => true, 'peer' => $peer];
    }

    // =========================================================================
    // leaveChannel()
    // =========================================================================

    public function leaveChannel(string $peer): array
    {
        $this->assertReady();

        $info = $this->peerCacheGet($peer) ?? $this->resolveAndCache($peer);

        if (!$info || $info['type'] !== 'channel') {
            throw new \RuntimeException("leaveChannel: '$peer' is not a channel/supergroup");
        }

        $request = new ChannelsLeaveChannelRequest($info['id'], $info['access_hash'] ?? 0);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['left' => true, 'peer' => $peer];
    }

    // =========================================================================
    // getHistory() — equivalent to Telethon's await client(GetHistoryRequest(...))
    // Returns array of message arrays (each with 'id','text','date','out','reply_markup')
    // =========================================================================

    /**
     * Ambil daftar semua dialog (DM, grup, channel).
     * Equivalent: await client.get_dialogs()
     */
    public function getDialogs(int $limit = 100, bool $allPages = false): array
    {
        $this->assertReady();
        $dialogs = $this->messages->getDialogs($limit, $allPages);

        // Cache semua dialog ke peerCache agar resolvePeer tahu tipe & access_hash-nya
        // (Telethon pattern: _mb_entity_cache.extend(r.users, r.chats))
        foreach ($dialogs as $d) {
            $entry = [
                'type'        => $d['type'],
                'id'          => $d['id'],
                'access_hash' => $d['access_hash'] ?? 0,
                'username'    => $d['username'] ?? null,
                'title'       => $d['title'] ?? null,
            ];
            $this->peerCache['id:' . $d['id']] = $entry;
            if (!empty($d['username'])) {
                $this->peerCache[$d['username']] = $entry;
            }
        }

        // Kumpulkan user dialogs yang masih menampilkan User#ID (min-user tanpa nama)
        $minIds = [];
        foreach ($dialogs as $d) {
            if ($d['type'] === 'user' && str_starts_with($d['title'], 'User#')) {
                $minIds[] = ['id' => $d['id'], 'access_hash' => $d['access_hash'] ?? 0];
            }
        }

        // Batch-fetch nama asli untuk min-users (users.getUsers access_hash=0 bekerja untuk kontak)
        if ($minIds) {
            try {
                $fetched = $this->messages->batchFetchUsers($minIds);
                if ($fetched) {
                    foreach ($dialogs as &$d) {
                        if ($d['type'] === 'user' && isset($fetched[$d['id']])) {
                            $u = $fetched[$d['id']];
                            $d['title']       = $u->getDisplayName();
                            $d['username']    = $u->username    ?? $d['username'];
                            $d['access_hash'] = $u->accessHash ?? $d['access_hash'];
                            $d['phone']       = $u->phone      ?? $d['phone'] ?? null;
                            // Update peerCache dengan data terbaru
                            $this->peerCache['id:' . $d['id']] = [
                                'type'        => 'user',
                                'id'          => $d['id'],
                                'access_hash' => $d['access_hash'] ?? 0,
                                'username'    => $d['username'],
                                'title'       => $d['title'],
                            ];
                            if (!empty($d['username'])) {
                                $this->peerCache[$d['username']] = $this->peerCache['id:' . $d['id']];
                            }
                        }
                    }
                    unset($d);
                    // Simpan hasil fetch ke session
                    $toCache = [];
                    foreach ($fetched as $u) {
                        $row = ['id' => $u->id, 'type' => 'user', 'bot' => $u->bot];
                        if ($u->accessHash !== null) $row['access_hash'] = $u->accessHash;
                        if ($u->firstName  !== null) $row['first_name']  = $u->firstName;
                        if ($u->lastName   !== null) $row['last_name']   = $u->lastName;
                        if ($u->username   !== null) $row['username']    = $u->username;
                        if ($u->phone      !== null) $row['phone']       = $u->phone;
                        $toCache[] = $row;
                    }
                    if ($toCache) $this->session->processEntities($toCache);
                }
            } catch (\Throwable $e) {
                // Non-fatal — min-user tetap tampil sebagai User#ID
            }
        }

        return $dialogs;
    }

    /**
     * Ambil daftar kontak.
     * Equivalent: await client.get_contacts()
     *
     * Return array of:
     *   [ 'id', 'access_hash', 'first_name', 'last_name', 'username', 'phone', 'mutual', 'bot', 'display' ]
     */
    public function getContacts(): array
    {
        $this->assertReady();
        return $this->messages->getContacts();
    }

    /**
     * Ambil riwayat pesan dari sebuah peer.
     * Equivalent: await client.get_messages(peer, limit=N)
     *
     * Return array of:
     *   [ 'id', 'date', 'text', 'out', 'from', 'from_id', 'type', 'reply_markup' ]
     *
     * peer bisa berupa '@username', '+phone', int id, 'me' (Saved Messages),
     * atau InputPeer.
     */
    public function getHistory(
        string|int|InputPeer $peer,
        int                  $limit      = 20,
        int                  $offsetId   = 0,
        int                  $offsetDate = 0,
        int                  $addOffset  = 0,
        int                  $maxId      = 0,
        int                  $minId      = 0
    ): array {
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        $result    = $this->messages->getHistoryByPeer($inputPeer, $limit, $offsetId, $offsetDate, $addOffset, $maxId, $minId);
        // Unwrap ['messages' => [...], 'count' => N] → flat array of message dicts
        return $result['messages'] ?? $result;
    }

    // =========================================================================
    // startBot() — equivalent to Telethon's await client(StartBotRequest(...))
    // =========================================================================

    public function startBot(string|int $bot, string|int|InputPeer $peer, string $startParam = ''): array
    {
        $this->assertReady();

        // Resolve bot
        $botInfo  = $this->resolveAndCache($bot);
        $peerInfo = $peer instanceof InputPeer ? null : $this->resolveAndCache($peer);
        $peerPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);

        if (!$botInfo) throw new \RuntimeException("startBot: cannot resolve bot '$bot'");

        $request = new MessagesStartBotRequest(
            $botInfo['id'],
            $botInfo['access_hash'] ?? 0,
            $peerPeer,
            $startParam
        );
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['started' => true, 'start_param' => $startParam];
    }

    // =========================================================================
    // clickButton() — click inline keyboard button on a message
    // Called by FullMessage::click()
    // =========================================================================

    public function clickButton(InputPeer $peer, int $msgId, ?string $data, bool $isGame = false): ?array
    {
        $this->assertReady();

        $request = new MessagesGetBotCallbackAnswerRequest($peer, $msgId, $data, $isGame);
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
            return ['clicked' => true, 'constructor' => sprintf('0x%08x', $response['constructor'])];
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }
    }

    // =========================================================================
    // resolvePeerFromMessage() — build InputPeer from a FullMessage + context
    // Called by FullMessage::click()
    // =========================================================================

    public function resolvePeerFromMessage(FullMessage $msg): InputPeer
    {
        switch ($msg->peerType) {
            case 'user':
                $info = $this->peerCache['id:' . $msg->peerId] ?? null;
                return InputPeer::user($msg->peerId, $info['access_hash'] ?? 0);

            case 'chat':
                return InputPeer::chat($msg->peerId);

            case 'channel':
                $info = $this->peerCache['id:' . $msg->peerId] ?? null;
                return InputPeer::channel($msg->peerId, $info['access_hash'] ?? 0);

            default:
                return InputPeer::empty();
        }
    }

    // =========================================================================
    // Event system — equivalent to Telethon's @client.on(events.NewMessage(...))
    // =========================================================================

    /**
     * Register a handler for new incoming messages.
     *
     * Usage:
     *   $client->on(new Events\NewMessage('@bot', incoming: true), function($event) use ($client) {
     *       echo $event->rawText;
     *       $event->message->click(0, 0);
     *   });
     */
    public function on(NewMessageFilter $filter, callable $handler): void
    {
        $this->eventHandlers[] = ['filter' => $filter, 'callable' => $handler];
    }

    /**
     * Register a handler for ALL server-pushed updates.
     *
     * The handler receives a RawUpdateEvent. Check $event->type before accessing fields.
     *
     * Supported types: new_message, edit_message, delete_messages,
     *   read_history, pinned_messages, user_status
     *
     * Usage:
     *   $client->onUpdate(function(RawUpdateEvent $event) {
     *       if ($event->type === 'delete_messages') {
     *           echo "Deleted IDs: " . implode(', ', $event->ids);
     *       }
     *       if ($event->type === 'user_status') {
     *           echo "User {$event->user_id} is " . ($event->online ? 'online' : 'offline');
     *       }
     *       if ($event->type === 'edit_message') {
     *           echo "Message {$event->message->id} was edited";
     *       }
     *   });
     */
    public function onUpdate(callable $handler): void
    {
        $this->rawUpdateHandlers[] = $handler;
    }

    /**
     * Remove all registered event handlers (new message + raw update).
     */
    public function removeHandlers(): void
    {
        $this->eventHandlers    = [];
        $this->rawUpdateHandlers = [];
    }

    /**
     * Blocking event loop — equivalent to Telethon's await client.run_until_disconnected().
     *
     * Polls the open connection for server-pushed updates and dispatches
     * them to registered event handlers. Exits when disconnect() is called
     * or the connection is lost.
     */
    public function runUntilDisconnected(): void
    {
        $this->shouldStop = false;

        // Install SIGINT handler if pcntl extension available
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () { $this->shouldStop = true; });
            pcntl_signal(SIGTERM, function () { $this->shouldStop = true; });
        }

        while (!$this->shouldStop && $this->isConnected()) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                $update = $this->sender->receiveUpdate(1);
            } catch (\Exception $e) {
                // Connection error — break loop
                break;
            }

            if ($update !== null) {
                $this->dispatchUpdate($update);
            }
        }
    }

    // =========================================================================
    // Peer resolution
    // =========================================================================

    /**
     * Resolve a peer identifier to InputPeer.
     *
     * Accepts:
     *   '@username'          — bot or channel username
     *   '+62812...'          — phone number
     *   123456789 (int)      — user/chat/channel id
     *   't.me/username'      — public link
     *   InputPeer            — returned as-is
     */
    public function resolvePeer(string|int $peer): InputPeer
    {
        if (is_int($peer)) {
            return $this->resolvePeerById($peer);
        }

        $peer = trim($peer);

        // 'me' or 'self' → Saved Messages
        if ($peer === 'me' || $peer === 'self') {
            return InputPeer::self();
        }

        // Already int string
        if (ctype_digit(ltrim($peer, '-'))) {
            return $this->resolvePeerById((int)$peer);
        }

        // t.me/username link
        if (preg_match('~t\.me/([A-Za-z][A-Za-z0-9_]{3,})$~', $peer, $m)) {
            $peer = '@' . $m[1];
        }

        // @username
        if (str_starts_with($peer, '@')) {
            $username = ltrim($peer, '@');

            // Check peer cache first
            if (isset($this->peerCache[$username])) {
                return $this->buildInputPeerFromCacheEntry($this->peerCache[$username]);
            }

            // Check session entity cache
            // Skip channel/user entries with access_hash=0 — they may be stale (stored before a fix)
            $entity = $this->session->getEntityRowsByUsername($username);
            if ($entity && !(
                in_array($entity['type'] ?? '', ['channel', 'user'], true) &&
                ($entity['access_hash'] ?? 0) === 0
            )) {
                $this->peerCache[$username] = $entity;
                return $this->buildInputPeerFromCacheEntry($entity);
            }

            // Resolve via API
            $info = $this->resolveAndCache('@' . $username);
            if ($info) return $this->buildInputPeerFromCacheEntry($info);

            throw new \RuntimeException("Cannot resolve '@$username'");
        }

        // Phone number
        if (str_starts_with($peer, '+')) {
            $entity = $this->session->getEntityRowsByPhone(ltrim($peer, '+'));
            if ($entity) return $this->buildInputPeerFromCacheEntry($entity);
            throw new \RuntimeException("Phone '$peer' not in contacts");
        }

        // Plain username without '@' (e.g. 'username123')
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,}$/', $peer)) {
            // Check peer cache / session first
            if (isset($this->peerCache[$peer])) {
                return $this->buildInputPeerFromCacheEntry($this->peerCache[$peer]);
            }
            $entity = $this->session->getEntityRowsByUsername($peer);
            if ($entity && !(
                in_array($entity['type'] ?? '', ['channel', 'user'], true) &&
                ($entity['access_hash'] ?? 0) === 0
            )) {
                $this->peerCache[$peer] = $entity;
                return $this->buildInputPeerFromCacheEntry($entity);
            }
            // Resolve via API
            $info = $this->resolveAndCache('@' . $peer);
            if ($info) return $this->buildInputPeerFromCacheEntry($info);
        }

        throw new \InvalidArgumentException("Cannot resolve peer: '$peer'");
    }

    private function resolvePeerById(int $id): InputPeer
    {
        // Handle Telegram Bot API "-100" prefix untuk channel/supergroup.
        // Contoh: -1003939561830 → channel_id 3939561830
        // Format: abs($id) dimulai dengan "100" dan panjangnya > 10 digit
        if ($id < 0) {
            $abs = (string)(-$id);
            if (str_starts_with($abs, '100') && strlen($abs) > 10) {
                $channelId = (int)substr($abs, 3);
                if (isset($this->peerCache['id:' . $channelId])) {
                    return $this->buildInputPeerFromCacheEntry($this->peerCache['id:' . $channelId]);
                }
                $entity = $this->session->getEntityRowsById($channelId);
                if ($entity) {
                    $this->peerCache['id:' . $channelId] = $entity;
                    return $this->buildInputPeerFromCacheEntry($entity);
                }
                // Buat InputPeer channel tanpa access_hash sebagai fallback
                return InputPeer::channel($channelId, 0);
            }
        }

        if (isset($this->peerCache['id:' . $id])) {
            return $this->buildInputPeerFromCacheEntry($this->peerCache['id:' . $id]);
        }
        // Fallback: cek session entity cache (tersimpan dari getDialogs sebelumnya)
        $entity = $this->session->getEntityRowsById($id);
        if ($entity) {
            $this->peerCache['id:' . $id] = $entity;
            return $this->buildInputPeerFromCacheEntry($entity);
        }
        // Unknown — return as user (may fail if wrong type, but let Telegram decide)
        return InputPeer::user($id, 0);
    }

    private function buildInputPeerFromCacheEntry(array $entry): InputPeer
    {
        $hash = $entry['access_hash'] ?? 0;
        return match ($entry['type'] ?? 'user') {
            'user'    => InputPeer::user($entry['id'], $hash),
            'chat'    => InputPeer::chat($entry['id']),
            'channel' => InputPeer::channel($entry['id'], $hash),
            default   => InputPeer::user($entry['id'], $hash),
        };
    }

    private function peerCacheGet(string $peer): ?array
    {
        $key = ltrim($peer, '@');

        // t.me/username
        if (preg_match('~t\.me/([A-Za-z][A-Za-z0-9_]{3,})$~', $peer, $m)) {
            $key = $m[1];
        }

        return $this->peerCache[$key] ?? null;
    }

    /**
     * Resolve peer via API and store in cache. Returns cache entry or null.
     */
    private function resolveAndCache(string|int $peer): ?array
    {
        if (is_int($peer)) {
            return $this->peerCache['id:' . $peer] ?? null;
        }

        try {
            $result = $this->messages->resolveUsername(ltrim($peer, '@'));
        } catch (\Exception $e) {
            return null;
        }

        $entry = [
            'type'        => $result['type'],
            'id'          => $result['id'],
            'access_hash' => $result['access_hash'] ?? 0,
            'username'    => $result['username'] ?? ltrim($peer, '@'),
        ];

        $username = $entry['username'] ?? ltrim($peer, '@');
        if ($username) $this->peerCache[$username] = $entry;
        $this->peerCache['id:' . $entry['id']] = $entry;

        // Persist in session
        $this->session->processEntities([$entry]);

        return $entry;
    }

    // =========================================================================
    // Update dispatch (internal)
    // =========================================================================

    private function dispatchUpdate(array $update): void
    {
        if ($update['type'] === 'multi') {
            foreach ($update['updates'] as $sub) {
                $this->dispatchUpdate($sub);
            }
            return;
        }

        $type = $update['type'];

        // Cache entities available in any update
        foreach ($update['users'] ?? [] as $user) {
            $this->cacheUserPeer($user);
        }
        foreach ($update['chats'] ?? [] as $chat) {
            $this->cacheChatPeer($chat);
        }

        // --- Dispatch raw update handlers (all types) ---
        if (!empty($this->rawUpdateHandlers)) {
            $rawEvent = new RawUpdateEvent($type, $update);
            foreach ($this->rawUpdateHandlers as $handler) {
                try { $handler($rawEvent); } catch (\Exception $e) {}
            }
        }

        // --- Dispatch new_message event handlers ---
        if ($type === 'new_message' || $type === 'edit_message') {
            /** @var FullMessage $msg */
            $msg           = $update['message'];
            $peerInputPeer = $this->buildPeerForMessage($msg, $update['users'] ?? [], $update['chats'] ?? []);
            $msg->setClient($this, $peerInputPeer);

            if ($type === 'new_message' && !empty($this->eventHandlers)) {
                foreach ($this->eventHandlers as $handler) {
                    /** @var NewMessageFilter $filter */
                    $filter = $handler['filter'];
                    if ($filter->matches($update, $this->peerCache)) {
                        $event = new NewMessageEvent($update);
                        try {
                            ($handler['callable'])($event);
                        } catch (\Exception $e) {
                            // Handler exception — continue to next handler
                        }
                    }
                }
            }
        }
    }

    private function buildPeerForMessage(FullMessage $msg, array $users, array $chats): InputPeer
    {
        switch ($msg->peerType) {
            case 'user':
                if (isset($users[$msg->peerId])) {
                    $u = $users[$msg->peerId];
                    return InputPeer::user($msg->peerId, $u->accessHash ?? 0);
                }
                if (isset($this->peerCache['id:' . $msg->peerId])) {
                    $e = $this->peerCache['id:' . $msg->peerId];
                    return InputPeer::user($msg->peerId, $e['access_hash'] ?? 0);
                }
                // Sender might be the peer in DMs
                if ($msg->fromUserId !== null && isset($users[$msg->fromUserId])) {
                    $u = $users[$msg->fromUserId];
                    return InputPeer::user($msg->fromUserId, $u->accessHash ?? 0);
                }
                return InputPeer::user($msg->peerId, 0);

            case 'chat':
                return InputPeer::chat($msg->peerId);

            case 'channel':
                if (isset($chats[$msg->peerId])) {
                    $c = $chats[$msg->peerId];
                    return InputPeer::channel($msg->peerId, $c->accessHash ?? 0);
                }
                if (isset($this->peerCache['id:' . $msg->peerId])) {
                    $e = $this->peerCache['id:' . $msg->peerId];
                    return InputPeer::channel($msg->peerId, $e['access_hash'] ?? 0);
                }
                return InputPeer::channel($msg->peerId, 0);

            default:
                return InputPeer::empty();
        }
    }

    private function cacheUserPeer(\XnoxsProto\TL\Types\User $user): void
    {
        $entry = [
            'type'        => 'user',
            'id'          => $user->id,
            'access_hash' => $user->accessHash ?? 0,
            'username'    => $user->username,
            'phone'       => $user->phone,
        ];
        if ($user->username) $this->peerCache[$user->username] = $entry;
        if ($user->phone)    $this->peerCache[$user->phone]    = $entry;
        $this->peerCache['id:' . $user->id] = $entry;
        $this->session->processEntities([$entry]);
    }

    private function cacheChatPeer(\XnoxsProto\TL\Types\Chat $chat): void
    {
        $type  = ($chat->isChannel() || $chat->isSupergroup()) ? 'channel' : 'chat';
        $entry = [
            'type'        => $type,
            'id'          => $chat->id,
            'access_hash' => $chat->accessHash ?? 0,
            'username'    => $chat->username,
        ];
        if ($chat->username) $this->peerCache[$chat->username] = $entry;
        $this->peerCache['id:' . $chat->id] = $entry;
        $this->session->processEntities([$entry]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function userToArray(User $user): array
    {
        return [
            'id'         => $user->id,
            'first_name' => $user->firstName ?? '',
            'last_name'  => $user->lastName  ?? '',
            'username'   => $user->username,
            'phone'      => $user->phone,
            'bot'        => $user->bot,
            'verified'   => $user->verified,
            'premium'    => $user->premium,
        ];
    }

    /**
     * Receive one pending update and dispatch it to registered handlers.
     * Returns true if an update was received, false if timeout (no data).
     *
     * Use this to build a manual event loop with a custom timeout:
     *   $end = time() + 15;
     *   while (time() < $end && $client->isConnected()) {
     *       $client->pollOnce(1);
     *   }
     */
    public function pollOnce(int $timeoutSeconds = 1): bool
    {
        if (!$this->sender) return false;
        try {
            $update = $this->sender->receiveUpdate($timeoutSeconds);
        } catch (\Exception $e) {
            return false;
        }
        if ($update === null) return false;
        $this->dispatchUpdate($update);
        return true;
    }

    // =========================================================================
    // sendVoice() — upload and send a voice note
    // =========================================================================

    /**
     * Upload & kirim voice note dari path lokal.
     * Equivalent: await client.send_file(peer, file, voice_note=True)
     *
     * @param string|int|InputPeer $peer         Tujuan pesan
     * @param string               $filePath     Path file OGG/OGA/MP3
     * @param int                  $duration     Durasi dalam detik
     * @param int|null             $replyTo      Balas pesan tertentu
     * @param callable|null        $onProgress   fn(int $part, int $total, int $pct)
     * @return array ['sent', 'message_id', 'date', 'type']
     */
    public function sendVoice(
        string|int|InputPeer $peer,
        string               $filePath,
        int                  $duration   = 0,
        ?int                 $replyTo    = null,
        ?callable            $onProgress = null
    ): array {
        $this->assertReady();
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->sendVoice($inputPeer, $filePath, $duration, $replyTo, $onProgress);
    }

    // =========================================================================
    // sendPoll() — create and send a poll
    // =========================================================================

    /**
     * Buat dan kirim poll ke sebuah peer.
     * Equivalent: await client.send_message(peer, file=...)  with poll media
     *
     * @param string|int|InputPeer $peer           Tujuan pesan
     * @param string               $question       Pertanyaan poll
     * @param string[]             $answers        Array jawaban (2-10 pilihan)
     * @param bool                 $isQuiz         Quiz mode (satu jawaban benar)
     * @param int                  $correctIndex   Index jawaban benar (quiz mode)
     * @param string               $solution       Penjelasan jawaban (quiz mode)
     * @param bool                 $multipleChoice Boleh pilih lebih dari satu
     * @param bool                 $publicVoters   Perlihatkan siapa memilih
     * @param int                  $closePeriod    Auto-close setelah N detik (0 = tidak)
     * @param int|null             $replyTo        Balas pesan tertentu
     * @return array ['sent', 'message_id', 'date', 'type']
     */
    public function sendPoll(
        string|int|InputPeer $peer,
        string               $question,
        array                $answers,
        bool                 $isQuiz         = false,
        int                  $correctIndex   = 0,
        string               $solution       = '',
        bool                 $multipleChoice = false,
        bool                 $publicVoters   = false,
        int                  $closePeriod    = 0,
        ?int                 $replyTo        = null
    ): array {
        $this->assertReady();
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);

        $poll = InputMediaPoll::create($question, $answers);
        if ($multipleChoice) $poll->setMultipleChoice(true);
        if ($publicVoters)   $poll->setPublicVoters(true);
        if ($closePeriod > 0) $poll->setClosePeriod($closePeriod);
        if ($isQuiz)         $poll->setQuiz($correctIndex, $solution);

        return $this->messages->sendPoll($inputPeer, $poll, $replyTo);
    }

    // =========================================================================
    // pinMessage() / unpinMessage() — pin or unpin a message in a chat/channel
    // =========================================================================

    /**
     * Pin pesan di dalam chat atau channel.
     * Equivalent: await client.pin_message(peer, message_id)
     *
     * @param string|int|InputPeer $peer     Peer (chat, channel, DM)
     * @param int                  $msgId    ID pesan yang akan di-pin
     * @param bool                 $silent   Pin tanpa notifikasi
     * @return array ['pinned', 'message_id']
     */
    public function pinMessage(
        string|int|InputPeer $peer,
        int                  $msgId,
        bool                 $silent = false
    ): array {
        return $this->doPinMessage($peer, $msgId, $silent, false);
    }

    /**
     * Unpin pesan dari chat atau channel.
     * Equivalent: await client.unpin_message(peer, message_id)
     */
    public function unpinMessage(string|int|InputPeer $peer, int $msgId): array
    {
        return $this->doPinMessage($peer, $msgId, false, true);
    }

    private function doPinMessage(
        string|int|InputPeer $peer,
        int                  $msgId,
        bool                 $silent,
        bool                 $unpin
    ): array {
        $this->assertReady();
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        $info      = $this->peerCache['id:' . $inputPeer->getId()] ?? null;
        $peerType  = $info['type'] ?? 'user';

        // messages.updatePinnedMessage#d2aaf7ec — bekerja untuk semua tipe peer
        // (channels.updatePinnedMessage sudah dihapus dari TDLib Layer 214 schema)
        $request = new MessagesUpdatePinnedMessageRequest($inputPeer, $msgId, $silent, $unpin, false);

        $request = $this->wrapFirstRequest($request);
        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return $unpin
            ? ['unpinned' => true, 'message_id' => $msgId]
            : ['pinned'   => true, 'message_id' => $msgId];
    }

    // =========================================================================
    // Invite user ke channel/supergroup — inviteToChannel
    // =========================================================================

    /**
     * Invite satu atau lebih user ke channel/supergroup.
     * Equivalent: await client(InviteToChannelRequest(channel, [user]))
     *
     * @param string|int|InputPeer                        $channel  Channel atau supergroup
     * @param string|int|InputPeer|array<string|int|InputPeer> $users User atau array of user
     * @return array ['invited', 'channel_id', 'user_ids']
     */
    public function inviteToChannel(
        string|int|InputPeer       $channel,
        string|int|InputPeer|array $users
    ): array {
        $this->assertReady();

        $channelPeer = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $channelInfo = $this->peerCache['id:' . $channelPeer->getId()]
                    ?? $this->session->getEntityRowsById($channelPeer->getId())
                    ?? null;
        $channelId   = $channelPeer->getId();
        $channelHash = $channelInfo['access_hash'] ?? $channelPeer->getAccessHash();

        $userList = is_array($users) ? $users : [$users];
        $usersPayload = [];
        $userIds      = [];

        foreach ($userList as $u) {
            $userPeer  = $u instanceof InputPeer ? $u : $this->resolvePeer($u);
            $userInfo  = $this->peerCache['id:' . $userPeer->getId()]
                      ?? $this->session->getEntityRowsById($userPeer->getId())
                      ?? null;
            $uid  = $userPeer->getId();
            $uhash = $userInfo['access_hash'] ?? $userPeer->getAccessHash();
            $usersPayload[] = ['user_id' => $uid, 'user_hash' => $uhash];
            $userIds[]      = $uid;
        }

        $request = new \XnoxsProto\TL\Functions\ChannelsInviteToChannelRequest(
            $channelId, $channelHash, $usersPayload
        );
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return ['invited' => true, 'channel_id' => $channelId, 'user_ids' => $userIds];
    }

    // =========================================================================
    // Admin management — promoteAdmin / demoteAdmin
    // =========================================================================

    /**
     * Jadikan user sebagai admin di channel/supergroup.
     * Equivalent: await client.edit_admin(channel, user, ChatAdminRights(...), rank='')
     *
     * @param string|int|InputPeer $channel  Channel atau supergroup
     * @param string|int|InputPeer $user     User yang akan dijadikan admin
     * @param int                  $rights   Flags dari ChannelsEditAdminRequest::RIGHT_* (0 = default admin)
     * @param string               $rank     Custom title / rank (opsional)
     * @return array ['promoted', 'user_id', 'rights']
     */
    public function promoteAdmin(
        string|int|InputPeer $channel,
        string|int|InputPeer $user,
        int                  $rights = 0,
        string               $rank   = ''
    ): array {
        $this->assertReady();
        ['peer_type' => $peerType, 'channel_id' => $cId, 'channel_hash' => $cHash,
         'user_id'   => $uId,     'user_hash'   => $uHash] = $this->resolveChannelAndUser($channel, $user);

        // Basic group — gunakan messages.editChatAdmin (rank tidak didukung)
        if ($peerType === InputPeer::CHAT) {
            $request = new MessagesEditChatAdminRequest($cId, $uId, $uHash, true);
            $request = $this->wrapFirstRequest($request);
            try {
                $this->sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
            }
            return ['promoted' => true, 'user_id' => $uId, 'rights' => 0, 'rank' => '',
                    'note' => 'basic group — rank dan custom rights tidak didukung'];
        }

        // Supergroup / Channel — gunakan channels.editAdmin
        if ($rights === 0) {
            $rights = ChannelsEditAdminRequest::RIGHT_CHANGE_INFO
                    | ChannelsEditAdminRequest::RIGHT_DELETE_MESSAGES
                    | ChannelsEditAdminRequest::RIGHT_BAN_USERS
                    | ChannelsEditAdminRequest::RIGHT_INVITE_USERS
                    | ChannelsEditAdminRequest::RIGHT_PIN_MESSAGES
                    | ChannelsEditAdminRequest::RIGHT_MANAGE_CALL
                    | ChannelsEditAdminRequest::RIGHT_OTHER;
        }

        $request = new ChannelsEditAdminRequest($cId, $cHash, $uId, $uHash, $rights, $rank);
        $request = $this->wrapFirstRequest($request);
        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['promoted' => true, 'user_id' => $uId, 'rights' => $rights, 'rank' => $rank];
    }

    /**
     * Cabut status admin dari user di channel/supergroup/basic group.
     * Equivalent: await client.edit_admin(channel, user, ChatAdminRights())
     */
    public function demoteAdmin(string|int|InputPeer $channel, string|int|InputPeer $user): array
    {
        $this->assertReady();
        ['peer_type' => $peerType, 'channel_id' => $cId, 'channel_hash' => $cHash,
         'user_id'   => $uId,     'user_hash'   => $uHash] = $this->resolveChannelAndUser($channel, $user);

        // Basic group
        if ($peerType === InputPeer::CHAT) {
            $request = new MessagesEditChatAdminRequest($cId, $uId, $uHash, false);
            $request = $this->wrapFirstRequest($request);
            try {
                $this->sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
            }
            return ['demoted' => true, 'user_id' => $uId];
        }

        // Supergroup / Channel
        $request = new ChannelsEditAdminRequest($cId, $cHash, $uId, $uHash, 0, '');
        $request = $this->wrapFirstRequest($request);
        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['demoted' => true, 'user_id' => $uId];
    }

    // =========================================================================
    // Ban management — banUser / unbanUser / kickUser / restrictUser
    // =========================================================================

    /**
     * Ban (hapus & cegah kembali) user dari channel/supergroup.
     * Equivalent: await client.edit_permissions(channel, user, view_messages=False)
     *
     * @param string|int|InputPeer $channel    Channel atau supergroup
     * @param string|int|InputPeer $user       User yang akan di-ban
     * @param int                  $untilDate  Unix timestamp kapan ban berakhir (0 = selamanya)
     * @return array ['banned', 'user_id', 'until']
     */
    public function banUser(
        string|int|InputPeer $channel,
        string|int|InputPeer $user,
        int                  $untilDate = 0
    ): array {
        $this->assertReady();
        ['peer_type' => $peerType, 'channel_id' => $cId, 'channel_hash' => $cHash,
         'user_id'   => $uId,     'user_hash'   => $uHash] = $this->resolveChannelAndUser($channel, $user);

        // Basic group — pakai messages.deleteChatUser (tidak bisa ban sementara, untilDate diabaikan)
        // Di basic group, "ban" berarti hapus permanen dari grup
        if ($peerType === InputPeer::CHAT) {
            $request = new MessagesDeleteChatUserRequest($cId, $uId, $uHash, false);
            $request = $this->wrapFirstRequest($request);
            try {
                $this->sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
            }
            return ['banned' => true, 'user_id' => $uId, 'until' => 0,
                    'note' => 'basic group — user dikeluarkan permanen, gunakan inviteToChannel untuk mengembalikan'];
        }

        // Supergroup / Channel
        $bannedFlags = ChannelsEditBannedRequest::BAN_VIEW_MESSAGES
                     | ChannelsEditBannedRequest::BAN_SEND_MESSAGES
                     | ChannelsEditBannedRequest::BAN_SEND_MEDIA
                     | ChannelsEditBannedRequest::BAN_SEND_STICKERS
                     | ChannelsEditBannedRequest::BAN_SEND_GIFS
                     | ChannelsEditBannedRequest::BAN_SEND_GAMES
                     | ChannelsEditBannedRequest::BAN_SEND_INLINE
                     | ChannelsEditBannedRequest::BAN_EMBED_LINKS
                     | ChannelsEditBannedRequest::BAN_SEND_POLLS
                     | ChannelsEditBannedRequest::BAN_CHANGE_INFO
                     | ChannelsEditBannedRequest::BAN_INVITE_USERS
                     | ChannelsEditBannedRequest::BAN_PIN_MESSAGES;

        $participant = InputPeer::user($uId, $uHash);
        $request     = new ChannelsEditBannedRequest($cId, $cHash, $participant, $bannedFlags, $untilDate);
        $request     = $this->wrapFirstRequest($request);
        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['banned' => true, 'user_id' => $uId, 'until' => $untilDate];
    }

    /**
     * Unban user.
     * Supergroup/channel: hapus semua restriksi (bisa join kembali).
     * Basic group: tidak ada ban list — throw exception.
     */
    public function unbanUser(string|int|InputPeer $channel, string|int|InputPeer $user): array
    {
        $this->assertReady();
        ['peer_type' => $peerType, 'channel_id' => $cId, 'channel_hash' => $cHash,
         'user_id'   => $uId,     'user_hash'   => $uHash] = $this->resolveChannelAndUser($channel, $user);

        // Basic group tidak punya ban list — tidak ada yang bisa di-unban
        if ($peerType === InputPeer::CHAT) {
            throw new \RuntimeException(
                'Basic group tidak mendukung unban. ' .
                'Gunakan inviteToChannel() untuk menambahkan kembali user yang sudah dikeluarkan.'
            );
        }

        $participant = InputPeer::user($uId, $uHash);
        $request     = new ChannelsEditBannedRequest($cId, $cHash, $participant, 0, 0);
        $request     = $this->wrapFirstRequest($request);
        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['unbanned' => true, 'user_id' => $uId];
    }

    /**
     * Kick user dari grup/supergroup/channel.
     * Supergroup/channel: ban lalu langsung unban — user bisa join kembali.
     * Basic group: hapus langsung via messages.deleteChatUser — user bisa di-invite kembali.
     */
    public function kickUser(string|int|InputPeer $channel, string|int|InputPeer $user): array
    {
        $this->assertReady();
        ['peer_type' => $peerType, 'channel_id' => $cId, 'channel_hash' => $cHash,
         'user_id'   => $uId,     'user_hash'   => $uHash] = $this->resolveChannelAndUser($channel, $user);

        // Basic group — satu panggilan cukup
        if ($peerType === InputPeer::CHAT) {
            $request = new MessagesDeleteChatUserRequest($cId, $uId, $uHash, false);
            $request = $this->wrapFirstRequest($request);
            try {
                $this->sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
            }
            return ['kicked' => true, 'user_id' => $uId];
        }

        // Supergroup / Channel — ban + unban
        $this->banUser($channel, $user);
        $this->unbanUser($channel, $user);
        return ['kicked' => true, 'user_id' => $uId];
    }

    /**
     * Batasi hak user (mute, larang media, dll).
     * Hanya berlaku untuk supergroup/channel.
     * Basic group tidak mendukung restriksi parsial — gunakan kickUser untuk mengeluarkan user.
     *
     * @param string|int|InputPeer $channel      Channel atau supergroup
     * @param string|int|InputPeer $user         User yang akan dibatasi
     * @param int                  $bannedFlags  Kombinasi flag ChannelsEditBannedRequest::BAN_*
     * @param int                  $untilDate    Unix timestamp kapan restriksi berakhir (0 = selamanya)
     * @return array ['restricted', 'user_id', 'flags', 'until']
     */
    public function restrictUser(
        string|int|InputPeer $channel,
        string|int|InputPeer $user,
        int                  $bannedFlags,
        int                  $untilDate = 0
    ): array {
        $this->assertReady();
        ['peer_type' => $peerType, 'channel_id' => $cId, 'channel_hash' => $cHash,
         'user_id'   => $uId,     'user_hash'   => $uHash] = $this->resolveChannelAndUser($channel, $user);

        // Basic group tidak mendukung restriksi parsial
        if ($peerType === InputPeer::CHAT) {
            throw new \RuntimeException(
                'Basic group tidak mendukung restrictUser (mute/limit). ' .
                'Untuk mengeluarkan user gunakan kickUser(). ' .
                'Konversi grup ke supergroup untuk menggunakan fitur restrict.'
            );
        }

        $participant = InputPeer::user($uId, $uHash);
        $request     = new ChannelsEditBannedRequest($cId, $cHash, $participant, $bannedFlags, $untilDate);
        $request     = $this->wrapFirstRequest($request);
        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        return ['restricted' => true, 'user_id' => $uId, 'flags' => $bannedFlags, 'until' => $untilDate];
    }

    /**
     * Mute user — larang kirim pesan, tapi masih bisa melihat chat.
     * Hanya berlaku untuk supergroup/channel. Basic group tidak mendukung mute.
     *
     * Shortcut dari: restrictUser($peer, $user, TelegramClient::BAN_SEND_MESSAGES, $untilDate)
     *
     * @param string|int|InputPeer $channel  Supergroup atau channel
     * @param string|int|InputPeer $user     User yang akan di-mute
     * @param int                  $seconds  Durasi mute (0 = selamanya, 3600 = 1 jam)
     * @return array ['restricted', 'user_id', 'muted_until']
     */
    public function muteUser(
        string|int|InputPeer $channel,
        string|int|InputPeer $user,
        int                  $seconds = 0
    ): array {
        $until  = $seconds > 0 ? (time() + $seconds) : 0;
        $result = $this->restrictUser($channel, $user, self::BAN_SEND_MESSAGES, $until);
        return [
            'restricted'  => true,
            'user_id'     => $result['user_id'],
            'muted_until' => $until === 0 ? 'selamanya' : date('Y-m-d H:i:s', $until),
        ];
    }

    /**
     * Read-only — user hanya bisa baca, tidak bisa kirim pesan, media, stiker, atau link.
     * Hanya berlaku untuk supergroup/channel. Basic group tidak mendukung restrict.
     *
     * Shortcut dari: restrictUser() dengan semua flag media diaktifkan.
     *
     * @param string|int|InputPeer $channel  Supergroup atau channel
     * @param string|int|InputPeer $user     User yang akan dibuat read-only
     * @param int                  $seconds  Durasi (0 = selamanya, 3600 = 1 jam)
     * @return array ['restricted', 'user_id', 'until']
     */
    public function readOnlyUser(
        string|int|InputPeer $channel,
        string|int|InputPeer $user,
        int                  $seconds = 0
    ): array {
        $flags = self::BAN_SEND_MESSAGES
               | self::BAN_SEND_MEDIA
               | self::BAN_SEND_STICKERS
               | self::BAN_SEND_GIFS
               | self::BAN_SEND_GAMES
               | self::BAN_SEND_INLINE
               | self::BAN_EMBED_LINKS
               | self::BAN_SEND_POLLS
               | self::BAN_SEND_PHOTOS
               | self::BAN_SEND_VIDEOS
               | self::BAN_SEND_AUDIOS
               | self::BAN_SEND_DOCS;
        $until  = $seconds > 0 ? (time() + $seconds) : 0;
        $result = $this->restrictUser($channel, $user, $flags, $until);
        return [
            'restricted' => true,
            'user_id'    => $result['user_id'],
            'until'      => $until === 0 ? 'selamanya' : date('Y-m-d H:i:s', $until),
        ];
    }

    // =========================================================================
    // search() / searchGlobal() — message search
    // =========================================================================

    /**
     * Cari pesan berisi kata kunci di dalam sebuah peer.
     * Equivalent: await client.get_messages(peer, search='query')
     *
     * @param string|int|InputPeer $peer      Peer tujuan pencarian
     * @param string               $query     Kata kunci
     * @param int                  $limit     Maksimum hasil (default 20)
     * @param int                  $offsetId  Mulai dari message ID tertentu
     * @param int                  $filter    Filter tipe (MessagesSearchRequest::FILTER_*)
     * @return array  Flat array of message arrays (same format as getHistory)
     */
    public function search(
        string|int|InputPeer $peer,
        string               $query,
        int                  $limit    = 20,
        int                  $offsetId = 0,
        int                  $filter   = MessagesSearchRequest::FILTER_EMPTY
    ): array {
        $this->assertReady();
        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        return $this->messages->search($inputPeer, $query, $limit, $filter, $offsetId);
    }

    /**
     * Cari pesan berisi kata kunci di semua chat.
     * Equivalent: await client.get_messages(None, search='query')
     *
     * @param string $query  Kata kunci
     * @param int    $limit  Maksimum hasil (default 20)
     * @return array  Flat array of message arrays
     */
    public function searchGlobal(string $query, int $limit = 20): array
    {
        $this->assertReady();
        return $this->messages->searchGlobal($query, $limit);
    }

    // =========================================================================
    // getFullUser() — get complete user info including bio and common chats
    // =========================================================================

    /**
     * Ambil info lengkap seorang user (termasuk bio, common chats, dll.).
     * Equivalent: await client(GetFullUserRequest(peer))
     *
     * @param string|int|InputPeer $user  User (@username, phone, ID, atau InputPeer)
     * @return array [
     *   'id', 'first_name', 'last_name', 'username', 'phone', 'bot', 'premium',
     *   'is_blocked', 'about', 'common_chats_count', 'pinned_msg_id'
     * ]
     */
    public function getFullUser(string|int|InputPeer $user): array
    {
        $this->assertReady();

        $inputPeer = $user instanceof InputPeer ? $user : $this->resolvePeer($user);
        $userInfo  = $this->peerCache['id:' . $inputPeer->getId()] ?? null;
        $userId    = $inputPeer->getId();
        $userHash  = $userInfo['access_hash'] ?? 0;

        if ($userId === 0) {
            $request = UsersGetFullUserRequest::self();
        } else {
            $request = UsersGetFullUserRequest::byId($userId, $userHash);
        }
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $result = [
            'id'                 => $userId,
            'first_name'         => $userInfo['first_name']  ?? null,
            'last_name'          => $userInfo['last_name']   ?? null,
            'username'           => $userInfo['username']    ?? null,
            'phone'              => $userInfo['phone']       ?? null,
            'bot'                => false,
            'premium'            => false,
            'is_blocked'         => false,
            'about'              => null,
            'common_chats_count' => 0,
            'pinned_msg_id'      => null,
        ];

        if ($response['constructor'] !== 0x3b6d152e) {
            return $result; // Unexpected response — return what we have
        }

        $reader = $response['reader'];

        try {
            // full_user:UserFull — boxed type, starts with its own constructor ID
            $reader->readInt(); // UserFull constructor (layer-dependent, don't validate)
            $flags  = $reader->readInt();
            $flags2 = $reader->readInt();
            $id     = $reader->readLong();

            $result['id']         = $id;
            $result['is_blocked'] = (bool)($flags & 0x01);

            // about:flags.1?string
            if ($flags & (1 << 1)) {
                $result['about'] = $reader->readString();
            }

            // settings:PeerSettings (always present)
            TLSkipHelper::skipPeerSettings($reader);

            // personal_photo:flags.21?Photo
            if ($flags & (1 << 21)) TLSkipHelper::skipPhoto($reader);
            // profile_photo:flags.2?Photo
            if ($flags & (1 << 2))  TLSkipHelper::skipPhoto($reader);
            // fallback_photo:flags.22?Photo
            if ($flags & (1 << 22)) TLSkipHelper::skipPhoto($reader);

            // notify_settings:PeerNotifySettings (always present)
            TLSkipHelper::skipPeerNotifySettings($reader);

            // bot_info:flags.3?BotInfo
            if ($flags & (1 << 3)) TLSkipHelper::skipBotInfo($reader);

            // pinned_msg_id:flags.6?int
            if ($flags & (1 << 6)) {
                $result['pinned_msg_id'] = $reader->readInt();
            }

            // common_chats_count:int (always present)
            $result['common_chats_count'] = $reader->readInt();

        } catch (\Exception $e) {
            // Partial parse — continue with what we have
        }

        // Parse chats vector (may fail if UserFull was not fully parsed)
        try {
            $reader->readInt(); // vector ctor 0x1cb5c415
            $chatCount = $reader->readInt();
            for ($i = 0; $i < $chatCount; $i++) {
                $ctor = $reader->readInt();
                Chat::fromReader($reader, $ctor); // skip chat
            }
        } catch (\Exception $e) {
            // Skip — chats not critical for user info
        }

        // Parse users vector to enrich result with User object fields
        try {
            $reader->readInt(); // vector ctor
            $userCount = $reader->readInt();
            for ($i = 0; $i < $userCount; $i++) {
                $u = User::fromReader($reader);
                if ($u->id === $result['id'] || $i === 0) {
                    $result['id']         = $u->id;
                    $result['first_name'] = $u->firstName  ?? $result['first_name'];
                    $result['last_name']  = $u->lastName   ?? $result['last_name'];
                    $result['username']   = $u->username   ?? $result['username'];
                    $result['phone']      = $u->phone      ?? $result['phone'];
                    $result['bot']        = $u->bot;
                    $result['premium']    = $u->premium;
                }
            }
        } catch (\Exception $e) {
            // Skip — user fields populated from cache
        }

        return $result;
    }

    // =========================================================================
    // getFullChat() — get full info for a basic group
    // =========================================================================

    /**
     * Ambil info lengkap sebuah basic group (non-supergroup).
     * Equivalent: await client(GetFullChatRequest(chat_id))
     *
     * @param int $chatId  Numeric chat ID (without -100 prefix)
     * @return array [
     *   'id', 'title', 'about', 'participants_count',
     *   'username', 'type', 'access_hash'
     * ]
     */
    public function getFullChat(int $chatId): array
    {
        $this->assertReady();

        $request = new MessagesGetFullChatRequest($chatId);
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $result = [
            'id'                 => $chatId,
            'title'              => null,
            'about'              => null,
            'participants_count' => 0,
            'type'               => 'chat',
        ];

        if ($response['constructor'] !== 0xe5d7d19c) {
            return $result;
        }

        $reader = $response['reader'];

        try {
            // full_chat:ChatFull (boxed — starts with ChatFull constructor)
            $reader->readInt(); // ChatFull constructor
            $flags = $reader->readInt();
            $id    = $reader->readLong();
            $about = $reader->readString(); // always present
            $result['id']    = $id;
            $result['about'] = $about;

            // participants:ChatParticipants (always present) — read ctor then skip
            $participantsCtor = $reader->readInt();
            if ($participantsCtor === 0x8763d3e1 || $participantsCtor === 0x8763d3d7) {
                // chatParticipantsForbidden (Layer 214 #8763d3e1 | legacy #8763d3d7)
                // flags:# chat_id:long self_participant:flags.0?ChatParticipant
                $pFlags = $reader->readInt();
                $reader->readLong(); // chat_id
                if ($pFlags & (1 << 0)) TLSkipHelper::skipChatParticipant($reader);
            } else {
                // chatParticipants#3cbc93f8 (L214) | #3f460fed (legacy)
                // chat_id:long participants:Vector<ChatParticipant> version:int
                $reader->readLong(); // chat_id
                $reader->readInt();  // vector constructor 0x1cb5c415
                $participantsCount = $reader->readInt();
                $result['participants_count'] = $participantsCount;
                for ($i = 0; $i < $participantsCount; $i++) {
                    TLSkipHelper::skipChatParticipant($reader);
                }
                $reader->readInt(); // version
            }
            // Skip sisa field chatFull setelah participants — sama seperti parseChatFullMembers
            // (referensi: chatFull#2633421b, TIDAK ada flags2)
            if ($flags & (1 <<  2)) TLSkipHelper::skipPhoto($reader);              // chat_photo
            TLSkipHelper::skipPeerNotifySettings($reader);                          // always present
            if ($flags & (1 << 13)) TLSkipHelper::skipExportedChatInvite($reader); // exported_invite
            if ($flags & (1 <<  3)) TLSkipHelper::skipVector($reader, fn($x) => TLSkipHelper::skipBotInfo($x)); // bot_info
            if ($flags & (1 <<  6)) $reader->readInt();                             // pinned_msg_id
            if ($flags & (1 << 11)) $reader->readInt();                             // folder_id
            if ($flags & (1 << 12)) TLSkipHelper::skipInputGroupCall($reader);     // call (bit 12)
            if ($flags & (1 << 14)) $reader->readInt();                             // ttl_period
            if ($flags & (1 << 15)) TLSkipHelper::skipPeer($reader);               // groupcall_default_join_as
            if ($flags & (1 << 16)) $reader->readString();                          // theme_emoticon
            if ($flags & (1 << 17)) $reader->readInt();                             // requests_pending
            if ($flags & (1 << 17)) TLSkipHelper::skipVector($reader, fn($r) => $r->readLong()); // recent_requesters
            if ($flags & (1 << 18)) $this->skipChatReactions($reader);             // available_reactions
            if ($flags & (1 << 20)) $reader->readInt();                             // reactions_limit
        } catch (\Exception $e) {
            // Partial parse — continue
        }

        // Parse chats vector untuk ambil judul
        try {
            $reader->readInt(); // vector ctor
            $chatCount = $reader->readInt();
            for ($i = 0; $i < $chatCount; $i++) {
                $ctor  = $reader->readInt();
                $chat  = Chat::fromReader($reader, $ctor);
                if ($chat->id === $result['id'] || $i === 0) {
                    $result['title'] = $chat->title ?? $result['title'];
                    if ($result['participants_count'] === 0 && ($chat->participantsCount ?? 0) > 0) {
                        $result['participants_count'] = $chat->participantsCount;
                    }
                }
            }
        } catch (\Exception $e) {}

        return $result;
    }

    // =========================================================================
    // getFullChannel() — get full info for a channel or supergroup
    // =========================================================================

    /**
     * Ambil info lengkap sebuah channel atau supergroup.
     * Equivalent: await client(GetFullChannelRequest(channel))
     *
     * @param string|int|InputPeer $channel  Channel (@username, ID, atau InputPeer)
     * @return array [
     *   'id', 'title', 'about', 'participants_count', 'admins_count',
     *   'banned_count', 'online_count', 'username', 'type', 'access_hash'
     * ]
     */
    public function getFullChannel(string|int|InputPeer $channel): array
    {
        $this->assertReady();

        $inputPeer = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $info      = $this->peerCache['id:' . $inputPeer->getId()] ?? null;
        $channelId = $inputPeer->getId();
        $channelHash = $info['access_hash'] ?? 0;

        $request = new ChannelsGetFullChannelRequest($channelId, $channelHash);
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(sprintf('[%d] %s', $e->errorCode, $e->errorMessage), $e->errorCode, $e);
        }

        $result = [
            'id'                 => $channelId,
            'title'              => $info['title']    ?? null,
            'username'           => $info['username'] ?? null,
            'about'              => null,
            'participants_count' => 0,
            'admins_count'       => 0,
            'banned_count'       => 0,
            'online_count'       => 0,
            'type'               => 'channel',
            'access_hash'        => $channelHash,
        ];

        if ($response['constructor'] !== 0xe5d7d19c) {
            return $result;
        }

        $reader = $response['reader'] ?? null;
        if ($reader === null) {
            return $result;
        }

        try {
            // full_chat:ChannelFull (boxed — starts with ChannelFull constructor)
            $reader->readInt(); // ChannelFull constructor (e.g. channelFull#73a379eb)
            $flags  = $reader->readInt();
            $flags2 = $reader->readInt();
            $id     = $reader->readLong();
            $about  = $reader->readString(); // always present

            $result['id']    = $id;
            $result['about'] = $about;

            // participants_count:flags.0?int
            if ($flags & (1 << 0)) $result['participants_count'] = $reader->readInt();
            // admins_count:flags.1?int
            if ($flags & (1 << 1)) $result['admins_count']       = $reader->readInt();
            // kicked_count:flags.2?int + banned_count:flags.2?int
            if ($flags & (1 << 2)) {
                $result['banned_count'] = $reader->readInt(); // kicked_count
                $reader->readInt();                           // banned_count
            }
            // online_count:flags.13?int
            if ($flags & (1 << 13)) $result['online_count'] = $reader->readInt();

            // read_inbox_max_id:int, read_outbox_max_id:int, unread_count:int (always)
            $reader->readInt(); $reader->readInt(); $reader->readInt();

            // chat_photo:Photo (always)
            TLSkipHelper::skipPhoto($reader);

            // notify_settings:PeerNotifySettings (always)
            TLSkipHelper::skipPeerNotifySettings($reader);

        } catch (\Throwable $e) {
            // Partial parse — continue with what we have
        }

        // Parse chats vector for additional info
        try {
            $reader->readInt(); // vector ctor
            $chatCount = $reader->readInt();
            for ($i = 0; $i < $chatCount; $i++) {
                $ctor = $reader->readInt();
                $chat = Chat::fromReader($reader, $ctor);
                if ($chat->id === $result['id'] || $i === 0) {
                    $result['title']    = $chat->title    ?? $result['title'];
                    $result['username'] = $chat->username ?? $result['username'];
                    if ($result['participants_count'] === 0 && ($chat->participantsCount ?? 0) > 0) {
                        $result['participants_count'] = $chat->participantsCount;
                    }
                }
            }
        } catch (\Throwable $e) {}

        return $result;
    }

    // =========================================================================
    // getAdminChannels() — daftar channel/supergroup di mana user adalah admin
    // =========================================================================

    /**
     * Ambil daftar channel dan supergroup di mana akun ini adalah admin atau creator.
     *
     * Cara kerja:
     *   1. Ambil semua dialog, filter yang bertipe channel/supergroup.
     *   2. Untuk channel di mana Chat::$creator = true → langsung masuk (creator = admin).
     *   3. Untuk sisanya → panggil channels.getParticipants(filter=admins) dan cari
     *      user ID kita di daftar admin. Jika ada → masuk.
     *
     * @param int $dialogLimit  Berapa dialog yang di-fetch (default 200)
     * @return array  Array of:
     *   [ 'id', 'access_hash', 'title', 'username', 'members',
     *     'is_supergroup', 'is_channel', 'role' => 'creator'|'admin' ]
     */
    public function getAdminChannels(int $dialogLimit = 200): array
    {
        $this->assertReady();

        // Pastikan kita punya user ID
        $myId = $this->session->getUserId();
        if ($myId === null) {
            try {
                $me   = $this->getMe();
                $myId = (int)($me['id'] ?? 0) ?: null;
            } catch (\Exception $e) {
                $myId = null;
            }
        }
        if ($myId === null) return [];

        $dialogs       = $this->getDialogs($dialogLimit, false);
        $adminChannels = [];

        foreach ($dialogs as $d) {
            $type = $d['type'] ?? '';

            // ── Basic group (type='chat') ──────────────────────────────
            // Cek creator flag yang sudah diparse dari Chat object.
            // Dalam basic group, hanya creator yang bisa kita deteksi langsung
            // tanpa panggil API extra (messages.getFullChat lebih berat).
            if ($type === 'chat') {
                if (!empty($d['creator'])) {
                    $adminChannels[] = [
                        'id'            => (int)$d['id'],
                        'access_hash'   => 0,
                        'title'         => $d['title'],
                        'username'      => $d['username'] ?? null,
                        'members'       => $d['members'] ?? 0,
                        'is_supergroup' => false,
                        'is_channel'    => false,
                        'role'          => 'creator',
                    ];
                }
                continue;
            }

            // ── Channel / Supergroup (type='channel') ─────────────────
            if ($type !== 'channel') continue;

            $id   = (int)$d['id'];
            $hash = (int)($d['access_hash'] ?? 0);
            if ($hash === 0) continue;

            $entry = [
                'id'            => $id,
                'access_hash'   => $hash,
                'title'         => $d['title'],
                'username'      => $d['username'] ?? null,
                'members'       => $d['members'] ?? 0,
                'is_supergroup' => $d['is_supergroup'] ?? false,
                'is_channel'    => $d['is_channel']    ?? false,
                'role'          => null,
            ];

            // Shortcut: jika creator flag sudah diset di dialog, tidak perlu
            // panggil channels.getParticipants (hemat 1 request per channel).
            if (!empty($d['creator'])) {
                $entry['role']   = 'creator';
                $adminChannels[] = $entry;
                continue;
            }

            // Fallback: tanya server siapa saja admin di channel ini.
            // channels.getParticipants dengan filter=admins lalu cek apakah
            // user ID kita ada di daftar.
            try {
                $admins = $this->getChannelAdminIds($id, $hash);
                if (in_array($myId, $admins['creators'], true)) {
                    $entry['role']   = 'creator';
                    $adminChannels[] = $entry;
                } elseif (in_array($myId, $admins['admins'], true)) {
                    $entry['role']   = 'admin';
                    $adminChannels[] = $entry;
                }
            } catch (\Throwable $e) {
                // Channel tidak bisa diakses (misal diblokir/kicked) — lewati
            }
        }

        return $adminChannels;
    }

    /**
     * Ambil user ID semua admin (dan creator) di sebuah channel/supergroup.
     * Digunakan secara internal oleh getAdminChannels().
     *
     * @return array ['admins' => int[], 'creators' => int[]]
     */
    private function getChannelAdminIds(int $channelId, int $channelHash): array
    {
        $request  = new ChannelsGetParticipantsRequest(
            $channelId, $channelHash,
            ChannelsGetParticipantsRequest::FILTER_ADMINS,
            offset: 0, limit: 200
        );
        $request  = $this->wrapFirstRequest($request);
        $response = $this->sender->send($request);

        $respCtor = $response['constructor'] ?? 0;
        if (!$response || ($respCtor !== 0xf0173fe9 && $respCtor !== 0x9ab0feaf)) {
            return ['admins' => [], 'creators' => []];
        }
        // channels.channelParticipantsNotModified#f0173fe9 has NO fields — nothing to parse
        if ($respCtor === 0xf0173fe9) {
            return ['admins' => [], 'creators' => []];
        }

        return $this->parseParticipantsAdminIds($response['reader']);
    }

    /**
     * Ambil daftar anggota (participants) sebuah channel atau supergroup.
     *
     * @param string|int|InputPeer $channel  Channel/supergroup target
     * @param string               $filter   'recent' | 'admins' | 'bots' | 'banned'
     * @param int                  $offset   Offset (untuk pagination)
     * @param int                  $limit    Maksimum hasil (maks 200 per request)
     * @return array  Array of:
     *   [ 'user_id', 'username', 'first_name', 'last_name', 'display',
     *     'phone', 'bot', 'role' => 'creator'|'admin'|'member'|'banned'|'left',
     *     'rank', 'date', 'access_hash' ]
     */
    public function getChannelMembers(
        string|int|InputPeer $channel,
        string               $filter = 'recent',
        int                  $offset = 0,
        int                  $limit  = 100
    ): array {
        $this->assertReady();

        $inputPeer = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);

        // Cek tipe peer — regular group (chat) harus pakai messages.getFullChat
        $info   = $this->peerCache['id:' . $inputPeer->getId()]
               ?? $this->session->getEntityRowsById($inputPeer->getId());
        $isChat = ($info['type'] ?? null) === 'chat'
               || $inputPeer->getType() === InputPeer::CHAT;

        if ($isChat) {
            return $this->fetchChatMembersViaFullChat($inputPeer->getId());
        }

        $channelId   = $inputPeer->getId();
        $channelHash = $info['access_hash'] ?? 0;

        $filterCtor = match ($filter) {
            'admins'  => ChannelsGetParticipantsRequest::FILTER_ADMINS,
            'bots'    => ChannelsGetParticipantsRequest::FILTER_BOTS,
            'banned'  => ChannelsGetParticipantsRequest::FILTER_BANNED,
            default   => ChannelsGetParticipantsRequest::FILTER_RECENT,
        };

        $request  = new ChannelsGetParticipantsRequest(
            $channelId, $channelHash, $filterCtor,
            offset: $offset, limit: min($limit, 200)
        );
        $request  = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        $respCtor = $response['constructor'] ?? 0;
        if (!$response || ($respCtor !== 0xf0173fe9 && $respCtor !== 0x9ab0feaf)) {
            return [];
        }
        // channels.channelParticipantsNotModified#f0173fe9 has NO fields — nothing to parse
        if ($respCtor === 0xf0173fe9) {
            return [];
        }

        return $this->parseChannelParticipants($response['reader']);
    }

    // =========================================================================
    // getChatMembers() — daftar anggota grup biasa (basic group)
    // =========================================================================

    /**
     * Ambil daftar seluruh anggota grup biasa (basic group / type='chat').
     *
     * Berbeda dengan getChannelMembers() yang juga mendukung supergroup/channel,
     * method ini khusus untuk grup biasa dan selalu mengembalikan SEMUA anggota
     * dalam satu request (tidak ada pagination karena Telegram membatasi
     * basic group maks 200 anggota).
     *
     * @param int|string|InputPeer $chat  ID numerik, username, atau InputPeer grup biasa
     * @return array  Array of:
     *   [ 'user_id', 'username', 'first_name', 'last_name', 'display',
     *     'phone', 'bot', 'role' => 'creator'|'admin'|'member',
     *     'rank', 'date', 'access_hash' ]
     *
     * @throws \RuntimeException jika RPC gagal atau peer bukan grup biasa
     */
    public function getChatMembers(int|string|InputPeer $chat): array
    {
        $this->assertReady();

        $inputPeer = $chat instanceof InputPeer ? $chat : $this->resolvePeer($chat);

        // Pastikan peer adalah basic group (chat), bukan channel/supergroup
        $info   = $this->peerCache['id:' . $inputPeer->getId()]
               ?? $this->session->getEntityRowsById($inputPeer->getId());
        $isChat = ($info['type'] ?? null) === 'chat'
               || $inputPeer->getType() === InputPeer::CHAT;

        if (!$isChat) {
            throw new \InvalidArgumentException(
                'getChatMembers() hanya untuk grup biasa (type=chat). ' .
                'Untuk supergroup/channel gunakan getChannelMembers().'
            );
        }

        return $this->fetchChatMembersViaFullChat($inputPeer->getId());
    }

    /**
     * Ambil anggota regular group via messages.getFullChat.
     */
    private function fetchChatMembersViaFullChat(int $chatId): array
    {
        $request = new MessagesGetFullChatRequest($chatId);
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        // messages.chatFull#e5d7d19c
        if (!$response || ($response['constructor'] ?? 0) !== 0xe5d7d19c) {
            return [];
        }

        return $this->parseChatFullMembers($response['reader']);
    }

    /**
     * Parse response messages.chatFull#e5d7d19c dan kembalikan flat list anggota.
     *
     * Struktur:
     *   full_chat:ChatFull   chats:Vector<Chat>   users:Vector<User>
     *
     * ChatFull#4dbdc099:
     *   flags:# id:long about:string participants:ChatParticipants
     *   chat_photo:f.2?Photo notify_settings:PeerNotifySettings
     *   exported_invite:f.13?ExportedChatInvite bot_info:f.3?Vector<BotInfo>
     *   pinned_msg_id:f.6?int folder_id:f.11?int ttl_period:f.14?int
     *   groupcall:f.15?InputGroupCall available_reactions:f.18?ChatReactions
     *   reactions_limit:f.17?int
     */
    private function parseChatFullMembers(BinaryReader $reader): array
    {
        $rawParticipants = [];
        $users           = [];

        try {
            // --- full_chat:ChatFull ---
            // Layer 214: chatFull#2633421b (adds flags2:#)
            // Legacy:    chatFull#4dbdc099
            $chatFullCtor = $reader->readInt();
            if ($chatFullCtor !== 0x4dbdc099 && $chatFullCtor !== 0x2633421b) return [];

            $flags = $reader->readInt();
            $reader->readLong();   // id
            $reader->readString(); // about

            // participants:ChatParticipants (mandatory)
            $partCtor = $reader->readInt();
            if ($partCtor === 0x3f460fed || $partCtor === 0x3cbc93f8) {
                // chatParticipants#3cbc93f8 (L214) | #3f460fed (legacy)
                // chat_id:long participants:Vector<ChatParticipant> version:int
                $reader->readLong(); // chat_id
                $reader->readInt();  // vector ctor
                $numP = $reader->readInt();
                for ($i = 0; $i < $numP; $i++) {
                    $pCtor = $reader->readInt();
                    switch ($pCtor) {
                        case 0x38e79fde: // chatParticipant (Layer 214 — TDLib)
                            // flags:# user_id:long inviter_id:long date:int rank:flags.0?string
                            $pf   = $reader->readInt();
                            $uid  = $reader->readLong();
                            $reader->readLong(); // inviter_id
                            $date = $reader->readInt();
                            if ($pf & (1 << 0)) $reader->readString(); // rank
                            $rawParticipants[] = ['user_id' => $uid, 'role' => 'member', 'date' => $date];
                            break;
                        case 0x0360d5d2: // chatParticipantAdmin (Layer 214 — TDLib)
                            // flags:# user_id:long inviter_id:long date:int rank:flags.0?string
                            $pf   = $reader->readInt();
                            $uid  = $reader->readLong();
                            $reader->readLong(); // inviter_id
                            $date = $reader->readInt();
                            if ($pf & (1 << 0)) $reader->readString(); // rank
                            $rawParticipants[] = ['user_id' => $uid, 'role' => 'admin', 'date' => $date];
                            break;
                        case 0xe1f867b8: // chatParticipantCreator (Layer 214 — TDLib)
                        case 0xda13538a: // chatParticipantCreator (legacy — same wire format)
                            // flags:# user_id:long rank:flags.0?string
                            $pf  = $reader->readInt();
                            $uid = $reader->readLong();
                            if ($pf & (1 << 0)) $reader->readString(); // rank
                            $rawParticipants[] = ['user_id' => $uid, 'role' => 'creator', 'date' => 0];
                            break;
                        case 0xc02d4007: // chatParticipant (mid-layer — user_id:long, no flags)
                        case 0xc8d7493e: // chatParticipant (legacy   — user_id:long, no flags)
                            $uid = $reader->readLong();
                            $reader->readLong(); // inviter_id
                            $date = $reader->readInt();
                            $rawParticipants[] = ['user_id' => $uid, 'role' => 'member', 'date' => $date];
                            break;
                        case 0xa0933f5b: // chatParticipantAdmin (mid-layer — user_id:long, no flags)
                        case 0xe2d6e436: // chatParticipantAdmin (legacy   — user_id:long, no flags)
                            $uid = $reader->readLong();
                            $reader->readLong(); // inviter_id
                            $date = $reader->readInt();
                            $rawParticipants[] = ['user_id' => $uid, 'role' => 'admin', 'date' => $date];
                            break;
                        case 0xe46bcee4: // chatParticipantCreator (mid-layer — user_id:long, no flags)
                            $uid = $reader->readLong();
                            $rawParticipants[] = ['user_id' => $uid, 'role' => 'creator', 'date' => 0];
                            break;
                        default:
                            break 2; // ctor tak dikenal — hentikan loop
                    }
                }
                $reader->readInt(); // version
            } elseif ($partCtor === 0x8763d3e1 || $partCtor === 0x8763d3d7) {
                // chatParticipantsForbidden (L214 #8763d3e1 | legacy #8763d3d7)
                // flags:# chat_id:long self_participant:flags.0?ChatParticipant
                $pf = $reader->readInt();
                $reader->readLong(); // chat_id
                if ($pf & 1) TLSkipHelper::skipChatParticipant($reader);
            }

            // Skip sisa field chatFull#2633421b setelah participants
            // Referensi TDLib: chatFull#2633421b (tidak ada flags2!)
            // Semua field opsional ada di bawah satu flags:# yang sama
            if ($flags & (1 <<  2)) TLSkipHelper::skipPhoto($reader);              // chat_photo
            TLSkipHelper::skipPeerNotifySettings($reader);                          // always present
            if ($flags & (1 << 13)) TLSkipHelper::skipExportedChatInvite($reader); // exported_invite
            if ($flags & (1 <<  3)) TLSkipHelper::skipVector($reader, fn($x) => TLSkipHelper::skipBotInfo($x)); // bot_info
            if ($flags & (1 <<  6)) $reader->readInt();                             // pinned_msg_id
            if ($flags & (1 << 11)) $reader->readInt();                             // folder_id
            if ($flags & (1 << 12)) TLSkipHelper::skipInputGroupCall($reader);     // call (bit 12, bukan 15!)
            if ($flags & (1 << 14)) $reader->readInt();                             // ttl_period
            if ($flags & (1 << 15)) TLSkipHelper::skipPeer($reader);               // groupcall_default_join_as (Peer!)
            if ($flags & (1 << 16)) $reader->readString();                          // theme_emoticon
            if ($flags & (1 << 17)) $reader->readInt();                             // requests_pending
            if ($flags & (1 << 17)) TLSkipHelper::skipVector($reader, fn($r) => $r->readLong()); // recent_requesters
            if ($flags & (1 << 18)) $this->skipChatReactions($reader);             // available_reactions
            if ($flags & (1 << 20)) $reader->readInt();                             // reactions_limit
        } catch (\Throwable) {
            // Lanjutkan — coba baca chats/users meski skip chatFull parsial gagal
        }

        try {
            // chats:Vector<Chat>
            $reader->readInt();
            $chatCount = $reader->readInt();
            for ($i = 0; $i < $chatCount; $i++) {
                $ctor = $reader->readInt();
                try { Chat::fromReader($reader, $ctor); } catch (\Throwable) { break; }
            }

            // users:Vector<User>
            $reader->readInt();
            $userCount = $reader->readInt();
            for ($i = 0; $i < $userCount; $i++) {
                $ctor = $reader->readInt();
                if ($ctor === User::CONSTRUCTOR_EMPTY) { $reader->readLong(); continue; }
                try {
                    $u = User::fromReader($reader);
                    $users[$u->id] = $u;
                } catch (\Throwable) { continue; }
            }
        } catch (\Throwable) {}

        // Enrich participants dengan user info
        $results = [];
        foreach ($rawParticipants as $p) {
            $uid = $p['user_id'] ?? null;
            $u   = $uid ? ($users[$uid] ?? null) : null;
            $results[] = [
                'user_id'     => $uid,
                'username'    => $u?->username,
                'first_name'  => $u?->firstName ?? '',
                'last_name'   => $u?->lastName  ?? '',
                'display'     => $u ? $u->getDisplayName() : ('User#' . $uid),
                'phone'       => $u?->phone,
                'bot'         => $u?->bot ?? false,
                'role'        => $p['role'],
                'rank'        => null,
                'date'        => $p['date'] ?? 0,
                'access_hash' => $u?->accessHash ?? 0,
            ];
            if ($u && $u->accessHash) {
                $this->peerCache['id:' . $u->id] = [
                    'type'        => 'user',
                    'id'          => $u->id,
                    'access_hash' => $u->accessHash,
                    'first_name'  => $u->firstName ?? '',
                    'last_name'   => $u->lastName  ?? '',
                    'username'    => $u->username,
                    'phone'       => $u->phone,
                    'bot'         => $u->bot,
                ];
                if ($u->username) {
                    $this->peerCache[$u->username] = $this->peerCache['id:' . $u->id];
                }
            }
        }
        return $results;
    }

    /**
     * Skip ChatReactions — tiga subtipe:
     *   chatReactionsNone#0eaa8ca4
     *   chatReactionsAll#e466d4ac  flags:#
     *   chatReactionsSome#661d4037 reactions:Vector<Reaction>
     */
    private function skipChatReactions(BinaryReader $r): void
    {
        $c = $r->readInt();
        switch ($c) {
            case 0x0eaa8ca4: // chatReactionsNone — no fields
                break;
            case 0xe466d4ac: // chatReactionsAll flags:#
                $r->readInt();
                break;
            case 0x661d4037: // chatReactionsSome reactions:Vector<Reaction>
                $r->readInt(); // vector ctor
                $count = $r->readInt();
                for ($i = 0; $i < $count; $i++) {
                    $rc = $r->readInt();
                    switch ($rc) {
                        case 0x79f5d419: break;              // reactionEmpty
                        case 0xbb35f04c: break;              // reactionPaid
                        case 0x1b2286be: $r->readString(); break; // reactionEmoji
                        case 0x8935fc73: $r->readLong();   break; // reactionCustomEmoji
                        default: break;
                    }
                }
                break;
            default: break;
        }
    }

    // -------------------------------------------------------------------------
    // Parser untuk response channels.channelParticipants#f0173fe9
    // -------------------------------------------------------------------------

    /**
     * Parse response channels.channelParticipants dan kembalikan flat list anggota.
     */
    private function parseChannelParticipants(BinaryReader $reader): array
    {
        $results = [];

        try {
            $count = $reader->readInt(); // count:int

            // participants:Vector<ChannelParticipant>
            $reader->readInt(); // vector ctor
            $numParticipants = $reader->readInt();
            $rawParticipants = [];
            for ($i = 0; $i < $numParticipants; $i++) {
                $ctor = $reader->readInt();
                $p    = $this->parseOneParticipant($reader, $ctor);
                if ($p !== null) $rawParticipants[] = $p;
            }

            // chats:Vector<Chat>
            $chats = [];
            try {
                $reader->readInt(); // vector ctor
                $chatCount = $reader->readInt();
                for ($i = 0; $i < $chatCount; $i++) {
                    $ctor = $reader->readInt();
                    try {
                        $chat = Chat::fromReader($reader, $ctor);
                        $chats[$chat->id] = $chat;
                    } catch (\Throwable $e) { break; }
                }
            } catch (\Throwable $e) {}

            // users:Vector<User>
            $users = [];
            try {
                $reader->readInt(); // vector ctor
                $userCount = $reader->readInt();
                for ($i = 0; $i < $userCount; $i++) {
                    $ctor = $reader->readInt();
                    if ($ctor === User::CONSTRUCTOR_EMPTY) { $reader->readLong(); continue; }
                    try {
                        $u = User::fromReader($reader);
                        $users[$u->id] = $u;
                    } catch (\Throwable $e) { continue; }
                }
            } catch (\Throwable $e) {}

            // Enrich participants dengan user info
            foreach ($rawParticipants as $p) {
                $userId = $p['user_id'] ?? null;
                $u      = $userId ? ($users[$userId] ?? null) : null;
                $results[] = [
                    'user_id'    => $userId,
                    'username'   => $u?->username,
                    'first_name' => $u?->firstName ?? '',
                    'last_name'  => $u?->lastName  ?? '',
                    'display'    => $u ? $u->getDisplayName() : ('User#' . $userId),
                    'phone'      => $u?->phone,
                    'bot'        => $u?->bot ?? false,
                    'role'       => $p['role'],
                    'rank'       => $p['rank'] ?? null,
                    'date'       => $p['date'] ?? 0,
                    'access_hash'=> $u?->accessHash ?? 0,
                ];
                // Perbarui peer cache supaya bisa dipakai sebagai TEST_USER
                if ($u && $u->accessHash) {
                    $this->peerCache['id:' . $u->id] = [
                        'type'         => 'user',
                        'id'           => $u->id,
                        'access_hash'  => $u->accessHash,
                        'first_name'   => $u->firstName ?? '',
                        'last_name'    => $u->lastName  ?? '',
                        'username'     => $u->username,
                        'phone'        => $u->phone,
                        'bot'          => $u->bot,
                    ];
                    if ($u->username) {
                        $this->peerCache[$u->username] = $this->peerCache['id:' . $u->id];
                    }
                }
            }

        } catch (\Throwable $e) {
            // Kembalikan apa yang sudah berhasil di-parse
        }

        return $results;
    }

    /**
     * Parse satu ChannelParticipant dari reader dan kembalikan array mentah.
     */
    private function parseOneParticipant(BinaryReader $reader, int $ctor): ?array
    {
        try {
            switch ($ctor) {
                case ChannelsGetParticipantsRequest::PARTICIPANT_MEMBER:
                    // channelParticipant#1bd54456 (current — TDLib)
                    // flags:# user_id:long date:int subscription_until_date:flags.0?int rank:flags.2?string
                    $flags  = $reader->readInt();
                    $userId = $reader->readLong();
                    $date   = $reader->readInt();
                    if ($flags & (1 << 0)) $reader->readInt();    // subscription_until_date
                    if ($flags & (1 << 2)) $reader->readString(); // rank
                    return ['user_id' => $userId, 'role' => 'member', 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_MEMBER_OLD:
                    // channelParticipant#cb397619 (old)
                    // flags:# user_id:long date:int subscription_until_date:flags.0?int
                    $flags  = $reader->readInt();
                    $userId = $reader->readLong();
                    $date   = $reader->readInt();
                    if ($flags & (1 << 0)) $reader->readInt(); // subscription_until_date
                    return ['user_id' => $userId, 'role' => 'member', 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_SELF:
                    // channelParticipantSelf#a9478a1a (current — TDLib)
                    // flags:# via_request:flags.0?true user_id:long inviter_id:long date:int
                    //   subscription_until_date:flags.1?int rank:flags.2?string
                    // inviter_id selalu hadir (bukan conditional!)
                    $flags  = $reader->readInt();
                    $userId = $reader->readLong();
                    $reader->readLong(); // inviter_id — always present
                    $date   = $reader->readInt();
                    if ($flags & (1 << 1)) $reader->readInt();    // subscription_until_date
                    if ($flags & (1 << 2)) $reader->readString(); // rank
                    return ['user_id' => $userId, 'role' => 'member', 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_SELF_OLD:
                    // channelParticipantSelf#4f607bef (old)
                    // flags:# via_request:flags.0?true user_id:long inviter_id:flags.1?long date:int
                    // inviter_id conditional di versi lama
                    $flags  = $reader->readInt();
                    $userId = $reader->readLong();
                    if ($flags & (1 << 1)) $reader->readLong(); // inviter_id — conditional
                    $date   = $reader->readInt();
                    return ['user_id' => $userId, 'role' => 'member', 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_CREATOR:
                    // channelParticipantCreator#2fe601d3
                    // flags:# user_id:long admin_rights:ChatAdminRights rank:flags.0?string
                    $flags  = $reader->readInt();
                    $userId = $reader->readLong();
                    TLSkipHelper::skipChatAdminRights($reader);
                    $rank   = ($flags & (1 << 0)) ? $reader->readString() : null;
                    return ['user_id' => $userId, 'role' => 'creator', 'rank' => $rank, 'date' => 0];

                case ChannelsGetParticipantsRequest::PARTICIPANT_ADMIN:
                    // channelParticipantAdmin#34c3bb53
                    // flags:# can_edit:f.0 self:f.1 user_id:long inviter_id:flags.1?long
                    //   promoted_by:long date:int admin_rights:ChatAdminRights rank:flags.2?string
                    $flags  = $reader->readInt();
                    $userId = $reader->readLong();
                    if ($flags & (1 << 1)) $reader->readLong(); // inviter_id (hanya jika self)
                    $reader->readLong(); // promoted_by
                    $date   = $reader->readInt();
                    TLSkipHelper::skipChatAdminRights($reader);
                    $rank   = ($flags & (1 << 2)) ? $reader->readString() : null;
                    return ['user_id' => $userId, 'role' => 'admin', 'rank' => $rank, 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_BANNED:
                    // channelParticipantBanned#d5f0ad91 (current — TDLib)
                    // flags:# left:flags.0?true peer:Peer kicked_by:long date:int
                    //   banned_rights:ChatBannedRights rank:flags.2?string
                    $flags  = $reader->readInt();
                    $reader->readInt(); // peer ctor
                    $userId = $reader->readLong(); // peer id
                    $reader->readLong(); // kicked_by
                    $date   = $reader->readInt();
                    TLSkipHelper::skipChatBannedRights($reader);
                    if ($flags & (1 << 2)) $reader->readString(); // rank
                    return ['user_id' => $userId, 'role' => 'banned', 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_BANNED_OLD:
                    // channelParticipantBanned#6df8014e (old — tanpa rank)
                    // flags:# left:flags.0?true peer:Peer kicked_by:long date:int banned_rights
                    $reader->readInt(); // flags
                    $reader->readInt(); // peer ctor
                    $userId = $reader->readLong();
                    $reader->readLong(); // kicked_by
                    $date   = $reader->readInt();
                    TLSkipHelper::skipChatBannedRights($reader);
                    return ['user_id' => $userId, 'role' => 'banned', 'date' => $date];

                case ChannelsGetParticipantsRequest::PARTICIPANT_LEFT:
                    // channelParticipantLeft#1b03f006 peer:Peer
                    $reader->readInt(); // peer ctor
                    $userId = $reader->readLong();
                    return ['user_id' => $userId, 'role' => 'left', 'date' => 0];

                default:
                    return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Parse admins response dan kembalikan hanya admin/creator user IDs.
     * Digunakan oleh getAdminChannels().
     */
    private function parseParticipantsAdminIds(BinaryReader $reader): array
    {
        $admins   = [];
        $creators = [];

        try {
            $reader->readInt(); // count

            $reader->readInt(); // vector ctor
            $num = $reader->readInt();
            for ($i = 0; $i < $num; $i++) {
                $ctor = $reader->readInt();
                $p    = $this->parseOneParticipant($reader, $ctor);
                if (!$p) continue;
                if ($p['role'] === 'creator') $creators[] = $p['user_id'];
                if ($p['role'] === 'admin')   $admins[]   = $p['user_id'];
            }
            // Chats & users vectors tidak perlu di-parse untuk admin check
        } catch (\Throwable $e) {}

        return ['admins' => $admins, 'creators' => $creators];
    }

    // =========================================================================
    // Group & Channel management — createChat / createChannel
    // =========================================================================

    /**
     * Buat grup biasa (basic group) baru.
     * Equivalent: await client(CreateChatRequest(users=[...], title='...'))
     *
     * @param string                                   $title  Judul grup (1–255 karakter)
     * @param string|int|InputPeer|array               $users  User atau array of user yang diundang
     * @return array ['created', 'title', 'user_ids']
     */
    public function createChat(string $title, string|int|InputPeer|array $users): array
    {
        $this->assertReady();

        $userList = is_array($users) ? $users : [$users];
        $usersPayload = [];
        $userIds      = [];

        foreach ($userList as $u) {
            $userPeer = $u instanceof InputPeer ? $u : $this->resolvePeer($u);
            $userInfo = $this->peerCache['id:' . $userPeer->getId()]
                     ?? $this->session->getEntityRowsById($userPeer->getId())
                     ?? null;
            $uid   = $userPeer->getId();
            $uhash = $userInfo['access_hash'] ?? $userPeer->getAccessHash();
            $usersPayload[] = ['user_id' => $uid, 'user_hash' => $uhash];
            $userIds[]      = $uid;
        }

        $request = new MessagesCreateChatRequest($title, $usersPayload);
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        // Parse Updates response untuk ambil chat_id dan simpan ke peerCache
        $chatId = null;
        try {
            $chatId = $this->parseCreatedChatIdFromUpdates($response);
        } catch (\Throwable $e) {}

        $result = ['created' => true, 'title' => $title, 'user_ids' => $userIds];
        if ($chatId !== null) {
            $result['chat_id'] = $chatId;
            // Simpan ke peerCache agar langsung bisa dipakai tanpa getDialogs
            $this->peerCache['id:' . $chatId] = [
                'type'        => 'chat',
                'id'          => $chatId,
                'access_hash' => 0,
                'title'       => $title,
                'username'    => null,
            ];
        }

        return $result;
    }

    /**
     * Buat supergroup atau channel broadcast baru.
     * Equivalent: await client(CreateChannelRequest(title, about, megagroup=True))
     *
     * @param string $title      Judul channel/supergroup
     * @param string $about      Deskripsi (opsional)
     * @param bool   $megagroup  true = supergroup (default false = broadcast channel)
     * @param bool   $forum      true = aktifkan mode topik/forum (hanya untuk megagroup)
     * @return array ['created', 'title', 'megagroup']
     */
    public function createChannel(
        string $title,
        string $about     = '',
        bool   $megagroup = false,
        bool   $forum     = false
    ): array {
        $this->assertReady();

        $request = new ChannelsCreateChannelRequest($title, $about, $megagroup, $forum);
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        // Parse Updates response untuk ambil channel_id + access_hash dan simpan ke peerCache
        $channelInfo = null;
        try {
            $channelInfo = $this->parseCreatedChannelFromUpdates($response);
        } catch (\Throwable $e) {}

        $result = [
            'created'   => true,
            'title'     => $title,
            'about'     => $about,
            'megagroup' => $megagroup,
            'forum'     => $forum,
        ];

        if ($channelInfo !== null) {
            $result['channel_id']   = $channelInfo['id'];
            $result['access_hash']  = $channelInfo['access_hash'];
            // Simpan ke peerCache agar langsung bisa dipakai
            $this->peerCache['id:' . $channelInfo['id']] = [
                'type'        => 'channel',
                'id'          => $channelInfo['id'],
                'access_hash' => $channelInfo['access_hash'],
                'title'       => $title,
                'username'    => null,
            ];
        }

        return $result;
    }

    // =========================================================================
    // Group & Channel management — deleteChat / deleteChannel
    // =========================================================================

    /**
     * Hapus grup, channel, atau supergroup secara permanen.
     * Mendeteksi tipe peer secara otomatis:
     *   - basic group (chat) → messages.deleteChat
     *   - channel/supergroup → channels.deleteChannel
     *
     * Hanya bisa dilakukan oleh creator/owner.
     *
     * @param string|int|InputPeer $peer  Peer yang akan dihapus
     * @return array ['deleted', 'peer_id']
     */
    public function deleteChat(string|int|InputPeer $peer): array
    {
        $this->assertReady();

        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        $info      = $this->peerCache['id:' . $inputPeer->getId()]
                  ?? $this->session->getEntityRowsById($inputPeer->getId())
                  ?? null;
        $peerId    = $inputPeer->getId();

        if ($inputPeer->getType() === InputPeer::CHAT) {
            // Basic group
            $request = new MessagesDeleteChatRequest($peerId);
        } else {
            // Channel atau Supergroup
            $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();
            $request    = new ChannelsDeleteChannelRequest($peerId, $accessHash);
        }

        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        // Hapus dari peerCache
        unset($this->peerCache['id:' . $peerId]);

        return ['deleted' => true, 'peer_id' => $peerId];
    }

    // =========================================================================
    // Group & Channel management — migrateChat
    // =========================================================================

    /**
     * Upgrade basic group ke supergroup.
     * Setelah migrate, chat_id lama tidak bisa dipakai lagi.
     * Supergroup baru akan muncul di getDialogs() sebagai channel (is_supergroup=true).
     *
     * @param int $chatId  ID basic group (tanpa prefix)
     * @return array ['migrated', 'old_chat_id']
     */
    public function migrateChat(int $chatId): array
    {
        $this->assertReady();

        $request = new MessagesMigrateChatRequest($chatId);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        // Hapus cache lama — supergroup baru akan dikenali lewat getDialogs()
        unset($this->peerCache['id:' . $chatId]);

        return ['migrated' => true, 'old_chat_id' => $chatId];
    }

    // =========================================================================
    // Group & Channel management — editChatTitle / editChatAbout
    // =========================================================================

    /**
     * Ubah judul grup, channel, atau supergroup.
     * Mendeteksi tipe peer otomatis:
     *   - basic group → messages.editChatTitle
     *   - channel/supergroup → channels.editTitle
     *
     * @param string|int|InputPeer $peer   Target peer
     * @param string               $title  Judul baru (1–255 karakter)
     * @return array ['updated', 'peer_id', 'title']
     */
    public function editChatTitle(string|int|InputPeer $peer, string $title): array
    {
        $this->assertReady();

        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        $info      = $this->peerCache['id:' . $inputPeer->getId()]
                  ?? $this->session->getEntityRowsById($inputPeer->getId())
                  ?? null;
        $peerId    = $inputPeer->getId();

        if ($inputPeer->getType() === InputPeer::CHAT) {
            $request = new MessagesEditChatTitleRequest($peerId, $title);
        } else {
            $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();
            $request    = new ChannelsEditTitleRequest($peerId, $accessHash, $title);
        }

        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        // Update peerCache
        if (isset($this->peerCache['id:' . $peerId])) {
            $this->peerCache['id:' . $peerId]['title'] = $title;
        }

        return ['updated' => true, 'peer_id' => $peerId, 'title' => $title];
    }

    /**
     * Ubah deskripsi/bio channel atau supergroup.
     * Basic group tidak memiliki deskripsi — migrate dulu ke supergroup.
     *
     * @param string|int|InputPeer $channel  Channel atau supergroup
     * @param string               $about    Deskripsi baru (kosongkan string = hapus)
     * @return array ['updated', 'peer_id', 'about']
     */
    public function editChatAbout(string|int|InputPeer $channel, string $about): array
    {
        $this->assertReady();

        $inputPeer  = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $info       = $this->peerCache['id:' . $inputPeer->getId()]
                   ?? $this->session->getEntityRowsById($inputPeer->getId())
                   ?? null;
        $channelId  = $inputPeer->getId();
        $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();

        if ($inputPeer->getType() === InputPeer::CHAT) {
            throw new \RuntimeException(
                'editChatAbout: basic group tidak memiliki deskripsi. ' .
                'Gunakan migrateChat() untuk upgrade ke supergroup terlebih dahulu.'
            );
        }

        $request = new ChannelsEditAboutRequest($channelId, $accessHash, $about);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return ['updated' => true, 'peer_id' => $channelId, 'about' => $about];
    }

    // =========================================================================
    // Group management — addChatUser (basic group)
    // =========================================================================

    /**
     * Tambahkan user ke basic group.
     * Equivalent: await client(AddChatUserRequest(chat_id, user, fwd_limit))
     *
     * Untuk supergroup/channel gunakan inviteToChannel().
     *
     * @param int                  $chatId    ID basic group
     * @param string|int|InputPeer $user      User yang akan ditambah
     * @param int                  $fwdLimit  Berapa pesan terakhir yang bisa dilihat user baru (0–100)
     * @return array ['added', 'chat_id', 'user_id']
     */
    public function addChatUser(
        int                  $chatId,
        string|int|InputPeer $user,
        int                  $fwdLimit = 100
    ): array {
        $this->assertReady();

        $userPeer = $user instanceof InputPeer ? $user : $this->resolvePeer($user);
        $userInfo = $this->peerCache['id:' . $userPeer->getId()]
                 ?? $this->session->getEntityRowsById($userPeer->getId())
                 ?? null;
        $userId   = $userPeer->getId();
        $userHash = $userInfo['access_hash'] ?? $userPeer->getAccessHash();

        $request = new MessagesAddChatUserRequest($chatId, $userId, $userHash, $fwdLimit);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return ['added' => true, 'chat_id' => $chatId, 'user_id' => $userId];
    }

    // =========================================================================
    // Channel management — toggleSlowMode
    // =========================================================================

    /**
     * Aktifkan atau nonaktifkan slow mode di supergroup.
     * Equivalent: await client(ToggleSlowModeRequest(channel, seconds))
     *
     * @param string|int|InputPeer $channel  Supergroup
     * @param int                  $seconds  Delay antar pesan (0=off, 10, 30, 60, 300, 900, 3600)
     * @return array ['updated', 'channel_id', 'slow_mode_seconds']
     */
    public function toggleSlowMode(string|int|InputPeer $channel, int $seconds): array
    {
        $this->assertReady();

        $inputPeer  = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $info       = $this->peerCache['id:' . $inputPeer->getId()]
                   ?? $this->session->getEntityRowsById($inputPeer->getId())
                   ?? null;
        $channelId  = $inputPeer->getId();
        $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();

        $request = new ChannelsToggleSlowModeRequest($channelId, $accessHash, $seconds);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return [
            'updated'           => true,
            'channel_id'        => $channelId,
            'slow_mode_seconds' => $seconds,
            'slow_mode_enabled' => $seconds > 0,
        ];
    }

    // =========================================================================
    // Channel management — exportInviteLink
    // =========================================================================

    /**
     * Generate link undangan baru untuk grup, supergroup, atau channel.
     * Equivalent: await client(ExportChatInviteRequest(peer))
     *
     * Respons berisi link undangan dari server. Jika $revokePermanent=true,
     * link permanen lama di-revoke dan link baru dibuat.
     *
     * @param string|int|InputPeer $peer             Target peer
     * @param bool                 $revokePermanent  true = revoke link lama, buat baru
     * @param bool                 $requestNeeded    true = join perlu persetujuan admin
     * @param int|null             $expireDate       Unix timestamp kadaluarsa (null = selamanya)
     * @param int|null             $usageLimit       Batas pemakaian link (null = unlimited)
     * @param string               $title            Nama/label link (opsional)
     * @return array ['link', 'expire_date', 'usage_limit', 'request_needed']
     */
    public function exportInviteLink(
        string|int|InputPeer $peer,
        bool                 $revokePermanent = false,
        bool                 $requestNeeded   = false,
        ?int                 $expireDate      = null,
        ?int                 $usageLimit      = null,
        string               $title           = ''
    ): array {
        $this->assertReady();

        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);

        $request = new MessagesExportChatInviteRequest(
            $inputPeer,
            $revokePermanent,
            $requestNeeded,
            $expireDate,
            $usageLimit,
            $title
        );
        $request = $this->wrapFirstRequest($request);

        try {
            $response = $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        $link      = null;
        $revoked   = false;
        $expireOut = null;
        $usageOut  = null;

        // chatInviteExported#a22cbd96 flags:# revoked:f.0?true link:string admin_id:long date:int
        //   start_date:f.4?int expire_date:f.1?int usage_limit:f.2?int usage:f.3?int
        //   requested:f.6?int title:f.5?string
        // (constructor may vary by layer; parse aggressively regardless of ctor)
        try {
            $reader = $response['reader'] ?? null;
            if ($reader !== null) {
                $flags   = $reader->readInt();
                $revoked = (bool)($flags & 0x1);
                $link    = $reader->readString();
                $reader->readLong(); // admin_id:long
                $reader->readInt();  // date:int
                if ($flags & (1 << 4)) $reader->readInt();            // start_date
                if ($flags & (1 << 1)) $expireOut = $reader->readInt(); // expire_date
                if ($flags & (1 << 2)) $usageOut  = $reader->readInt(); // usage_limit
            }
        } catch (\Throwable $e) {
            // parse gagal — kembalikan result minimal
        }

        return [
            'link'           => $link,
            'revoked'        => $revoked,
            'expire_date'    => $expireOut,
            'usage_limit'    => $usageOut,
            'request_needed' => $requestNeeded,
            'title'          => $title,
            'peer_id'        => $inputPeer->getId(),
        ];
    }

    // =========================================================================
    // Group & Channel management — setDefaultPermissions
    // =========================================================================

    /**
     * Set default permission anggota untuk grup atau supergroup.
     * Equivalent: await client(EditChatDefaultBannedRightsRequest(peer, banned_rights))
     *
     * Flag yang di-set = DILARANG. Flag tidak di-set = DIIZINKAN.
     * Gunakan konstanta dari MessagesEditChatDefaultBannedRightsRequest::BAN_*.
     *
     * Contoh — larang anggota kirim stiker dan GIF:
     *   $client->setDefaultPermissions($peer,
     *       MessagesEditChatDefaultBannedRightsRequest::BAN_SEND_STICKERS |
     *       MessagesEditChatDefaultBannedRightsRequest::BAN_SEND_GIFS
     *   );
     *
     * @param string|int|InputPeer $peer          Target peer (grup/supergroup)
     * @param int                  $bannedRights  Bitmask larangan (0 = izinkan semua)
     * @return array ['updated', 'peer_id', 'banned_rights']
     */
    public function setDefaultPermissions(
        string|int|InputPeer $peer,
        int                  $bannedRights
    ): array {
        $this->assertReady();

        $inputPeer = $peer instanceof InputPeer ? $peer : $this->resolvePeer($peer);
        $peerId    = $inputPeer->getId();

        $request = new MessagesEditChatDefaultBannedRightsRequest($inputPeer, $bannedRights);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return [
            'updated'       => true,
            'peer_id'       => $peerId,
            'banned_rights' => $bannedRights,
        ];
    }

    // =========================================================================
    // Channel management — toggleSignatures
    // =========================================================================

    /**
     * Aktifkan atau nonaktifkan tanda tangan admin di channel broadcast.
     * Saat aktif, pesan yang diposting admin akan menampilkan nama pengirim.
     * Hanya berlaku untuk channel broadcast (bukan supergroup).
     *
     * @param string|int|InputPeer $channel  Channel broadcast
     * @param bool                 $enabled  true = aktifkan, false = nonaktifkan
     * @return array ['updated', 'channel_id', 'signatures_enabled']
     */
    public function toggleSignatures(string|int|InputPeer $channel, bool $enabled): array
    {
        $this->assertReady();

        $inputPeer  = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $info       = $this->peerCache['id:' . $inputPeer->getId()]
                   ?? $this->session->getEntityRowsById($inputPeer->getId())
                   ?? null;
        $channelId  = $inputPeer->getId();
        $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();

        $request = new ChannelsToggleSignaturesRequest($channelId, $accessHash, $enabled);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return [
            'updated'             => true,
            'channel_id'          => $channelId,
            'signatures_enabled'  => $enabled,
        ];
    }

    // =========================================================================
    // Channel management — toggleJoinToSend / toggleJoinRequest
    // =========================================================================

    /**
     * Wajibkan user untuk join supergroup sebelum bisa mengirim pesan.
     * Saat diaktifkan, user yang belum join tidak bisa kirim pesan.
     *
     * @param string|int|InputPeer $channel  Supergroup
     * @param bool                 $enabled  true = wajib join dulu, false = tidak wajib
     * @return array ['updated', 'channel_id', 'join_to_send']
     */
    public function toggleJoinToSend(string|int|InputPeer $channel, bool $enabled): array
    {
        $this->assertReady();

        $inputPeer  = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $info       = $this->peerCache['id:' . $inputPeer->getId()]
                   ?? $this->session->getEntityRowsById($inputPeer->getId())
                   ?? null;
        $channelId  = $inputPeer->getId();
        $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();

        $request = new ChannelsToggleJoinToSendRequest($channelId, $accessHash, $enabled);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return [
            'updated'      => true,
            'channel_id'   => $channelId,
            'join_to_send' => $enabled,
        ];
    }

    /**
     * Wajibkan persetujuan admin sebelum user bisa bergabung ke supergroup/channel.
     * Saat diaktifkan, request join harus di-approve admin terlebih dahulu.
     *
     * @param string|int|InputPeer $channel  Supergroup atau channel
     * @param bool                 $enabled  true = wajib persetujuan, false = langsung join
     * @return array ['updated', 'channel_id', 'join_request']
     */
    public function toggleJoinRequest(string|int|InputPeer $channel, bool $enabled): array
    {
        $this->assertReady();

        $inputPeer  = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $info       = $this->peerCache['id:' . $inputPeer->getId()]
                   ?? $this->session->getEntityRowsById($inputPeer->getId())
                   ?? null;
        $channelId  = $inputPeer->getId();
        $accessHash = $info['access_hash'] ?? $inputPeer->getAccessHash();

        $request = new ChannelsToggleJoinRequestRequest($channelId, $accessHash, $enabled);
        $request = $this->wrapFirstRequest($request);

        try {
            $this->sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode, $e
            );
        }

        return [
            'updated'      => true,
            'channel_id'   => $channelId,
            'join_request' => $enabled,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve channel and user to their IDs and access hashes.
     * Returns array with keys: channel_id, channel_hash, user_id, user_hash
     */
    private function resolveChannelAndUser(
        string|int|InputPeer $channel,
        string|int|InputPeer $user
    ): array {
        $channelPeer = $channel instanceof InputPeer ? $channel : $this->resolvePeer($channel);
        $channelInfo = $this->peerCache['id:' . $channelPeer->getId()]
                    ?? $this->session->getEntityRowsById($channelPeer->getId())
                    ?? null;
        // Jika cache kosong tapi hash ada di InputPeer itu sendiri, pakai langsung
        $channelHash = $channelInfo['access_hash'] ?? $channelPeer->getAccessHash();

        $userPeer    = $user instanceof InputPeer ? $user : $this->resolvePeer($user);
        $userInfo    = $this->peerCache['id:' . $userPeer->getId()]
                    ?? $this->session->getEntityRowsById($userPeer->getId())
                    ?? null;
        $userHash    = $userInfo['access_hash'] ?? $userPeer->getAccessHash();

        return [
            'peer_type'    => $channelPeer->getType(),   // InputPeer::CHAT | CHANNEL | USER
            'channel_peer' => $channelPeer,
            'channel_id'   => $channelPeer->getId(),
            'channel_hash' => $channelHash,
            'user_id'      => $userPeer->getId(),
            'user_hash'    => $userHash,
        ];
    }

    /**
     * Parse Updates#74ae4240 response dari messages.createChat untuk mengambil chat_id.
     *
     * Updates: updates:Vector<Update>  users:Vector<User>  chats:Vector<Chat>  date  seq
     *
     * Strategi:
     *  - Skip updates vector (support konstruktor yang dikenal: updateMessageID, updateNewMessage header)
     *  - Jika users vector kosong, lanjut ke chats vector
     *  - Ambil chat pertama (basic group)
     */
    private function parseCreatedChatIdFromUpdates(array $response): ?int
    {
        $ctor   = $response['constructor'] ?? 0;
        $reader = $response['reader']      ?? null;
        if ($reader === null) return null;

        // messages.InvitedUsers#7f5defa6 — format baru Layer 166+
        // Structure: updates:Updates  missing_invitees:Vector<MissingInvitee>
        // messages.createChat di Layer 214 mengembalikan ini bukan Updates langsung.
        if ($ctor === 0x7f5defa6) {
            // Baca inner Updates constructor (4 bytes), lalu teruskan reader ke scanner
            $innerCtor = $reader->readInt();
            $response  = ['constructor' => $innerCtor, 'reader' => $reader];
        }

        $result = $this->parseChatsFromUpdatesResponse($response);

        // Pilih chat dengan criteria: creator=true AND date tertinggi (paling baru dibuat)
        $bestChat = null;
        $bestDate = -1;
        foreach ($result as $chat) {
            if ($chat->type !== 'chat' || $chat->id <= 0) continue;
            if (!$chat->creator) continue;
            if ($chat->date > $bestDate) {
                $bestDate = $chat->date;
                $bestChat = $chat;
            }
        }
        if ($bestChat !== null) return $bestChat->id;

        // Fallback: chat apa pun dengan date tertinggi
        $bestChat = null;
        $bestDate = -1;
        foreach ($result as $chat) {
            if ($chat->type !== 'chat' || $chat->id <= 0) continue;
            if ($chat->date > $bestDate) {
                $bestDate = $chat->date;
                $bestChat = $chat;
            }
        }
        return $bestChat?->id;
    }

    /**
     * Parse Updates#74ae4240 response dari channels.createChannel untuk mengambil channel_id + access_hash.
     *
     * Updates: updates:Vector<Update>  users:Vector<User>  chats:Vector<Chat>  date  seq
     *
     * Untuk createChannel, updates vector biasanya hanya berisi updateChannel#635b4c09
     * dan users vector kosong — sehingga bisa di-parse tanpa type registry lengkap.
     */
    private function parseCreatedChannelFromUpdates(array $response): ?array
    {
        $result = $this->parseChatsFromUpdatesResponse($response);
        foreach ($result as $chat) {
            if ($chat->type === 'channel' && $chat->id > 0 && $chat->accessHash !== null) {
                return [
                    'id'          => $chat->id,
                    'access_hash' => $chat->accessHash,
                    'title'       => $chat->title,
                    'megagroup'   => $chat->megagroup,
                ];
            }
        }
        return null;
    }

    /**
     * Parse chats vector dari Updates#74ae4240 response.
     *
     * Menggunakan strategi scan biner: cari pola (vector_ctor + count + known_chat_ctor)
     * langsung di raw bytes tanpa harus skip setiap Update satu per satu.
     * Ini menghindari ketergantungan pada type registry Updates yang lengkap.
     *
     * @param array $response ['constructor' => int, 'reader' => BinaryReader]
     * @return Chat[]
     */
    private function parseChatsFromUpdatesResponse(array $response): array
    {
        $reader = $response['reader'] ?? null;
        if ($reader === null) return [];

        // Terima semua constructor Updates (0x74ae4240=updates, 0x78d4dec1=updates_too_long,
        // 0x11f1331c=updateShortMessage, dll). Jika bukan Updates sama sekali, scan mungkin
        // tidak menemukan apa pun — tidak ada kerugian.
        $ctor = $response['constructor'] ?? 0;
        $knownUpdatesCtors = [
            0x74ae4240, // updates#74ae4240
            0x78d4dec1, // updatesTooLong
            0x9e0d9b1f, // updateShort
            0x11f1331c, // updateShortMessage
            0xae0b0d43, // updateShortChatMessage
            0x62d6b459, // updateShortSentMessage
            0x9ec20908, // updatesCombined
        ];
        if (!in_array($ctor, $knownUpdatesCtors, true)) return [];

        try {
            // Ambil semua sisa bytes dan simpan posisi awal
            $startPos = $reader->tell();
            $rawData  = $reader->getRemainingData();

            // Konstruktor Chat yang dikenal (little-endian uint32)
            $knownChatCtors = [
                0x41cbf256, // chat#41cbf256 (TL_chat_layer123 — server kirim ini)
                0x1c207ca0, // chat#1c207ca0
                0xa9eca0ab, // chatForbidden
                0xfe685355, // channel#fe685355 (TL_channel_layer216 — server kirim ini)
                0x1c32b11c, // channel#1c32b11c
                0x17d493d5, // channelForbidden
                0x0aadfc8f, // channel legacy
            ];

            // Vector constructor 0x1cb5c415 dalam little-endian bytes
            $vecCtorBytes = pack('V', 0x1cb5c415);
            $dataLen      = strlen($rawData);

            // Scan dari AKHIR ke AWAL: chats vector adalah vector terakhir sebelum date+seq
            // Ini lebih akurat daripada scan dari depan yang bisa match users/updates vector dulu
            $matches = [];
            for ($offset = 0; $offset <= $dataLen - 12; $offset++) {
                if (substr($rawData, $offset, 4) !== $vecCtorBytes) continue;

                $count = unpack('V', substr($rawData, $offset + 4, 4))[1];
                if ($count < 1 || $count > 20) continue;

                $nextCtor = unpack('V', substr($rawData, $offset + 8, 4))[1];
                if (!in_array($nextCtor, $knownChatCtors, true)) continue;

                $matches[] = $offset;
            }

            // Coba dari match TERAKHIR ke pertama (chats vector biasanya di akhir Updates)
            foreach (array_reverse($matches) as $offset) {
                // Posisikan reader ke sesudah vector_ctor + count
                $reader->seek($startPos + $offset + 8);
                $count = unpack('V', substr($rawData, $offset + 4, 4))[1];

                $chats = [];
                for ($i = 0; $i < $count; $i++) {
                    try {
                        $chatCtor = $reader->readInt();
                        $chat     = Chat::fromReader($reader, $chatCtor);
                        if ($chat->type !== 'empty' && $chat->type !== 'unknown' && $chat->id > 0) {
                            $chats[] = $chat;
                        }
                    } catch (\Throwable $e) {
                        break;
                    }
                }

                if (!empty($chats)) return $chats;

                // Jika parse gagal, lanjutkan scan dari posisi berikutnya
                $reader->seek($startPos);
            }

        } catch (\Throwable $e) {}

        return [];
    }

    // =========================================================================
    // Auth export — digunakan oleh FileDownloader untuk DC migration
    // =========================================================================

    /**
     * Export authorization ke DC lain untuk keperluan download file.
     *
     * Dipanggil otomatis oleh FileDownloader saat file berada di DC berbeda.
     *
     * @param int $dcId DC tujuan
     * @return array ['id' => int, 'bytes' => string]
     */
    public function exportAuthorization(int $dcId): array
    {
        $this->assertReady();

        $req = new AuthExportAuthorizationRequest($dcId);
        $req = $this->wrapFirstRequest($req);

        try {
            $response = $this->sender->send($req);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            throw new \RuntimeException(
                sprintf('[%d] %s', $e->errorCode, $e->errorMessage),
                $e->errorCode,
                $e
            );
        }

        $c = $response['constructor'];
        $r = $response['reader'];

        // auth.exportedAuthorization#b5f66a1c id:long bytes:bytes
        if ($c !== 0xb5f66a1c) {
            throw new \RuntimeException(sprintf('Unexpected exportAuthorization response: 0x%08x', $c));
        }

        return [
            'id'    => $r->readLong(),
            'bytes' => $r->readBytes(),
        ];
    }

    /**
     * Kembalikan ekstensi file yang sesuai berdasarkan info media dari message.
     *
     * @param array $media Array 'media' dari sebuah message
     * @return string Ekstensi tanpa titik (misal: 'jpg', 'mp4', 'ogg', 'pdf')
     */
    public function getMediaExtension(array $media): string
    {
        $type = $media['type']     ?? '';
        $mime = $media['mime']     ?? '';
        $file = $media['filename'] ?? '';

        if ($file !== '' && str_contains($file, '.')) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        $mimeMap = [
            'image/jpeg'       => 'jpg',  'image/png'       => 'png',
            'image/gif'        => 'gif',  'image/webp'      => 'webp',
            'video/mp4'        => 'mp4',  'video/quicktime' => 'mov',
            'video/x-matroska' => 'mkv',  'audio/mpeg'      => 'mp3',
            'audio/ogg'        => 'ogg',  'audio/opus'      => 'ogg',
            'audio/flac'       => 'flac', 'audio/wav'       => 'wav',
            'application/pdf'  => 'pdf',  'application/zip' => 'zip',
        ];
        if (isset($mimeMap[$mime])) return $mimeMap[$mime];

        return match($type) {
            'photo'   => 'jpg', 'video'  => 'mp4',
            'audio'   => 'mp3', 'voice'  => 'ogg',
            'gif'     => 'mp4', 'sticker'=> 'webp',
            default   => 'bin',
        };
    }

    private function assertReady(): void
    {
        if (!$this->isConnected()) throw new \RuntimeException('Not connected to Telegram');
        if (!$this->sender)        throw new \RuntimeException('MTProto sender not initialized');
    }
}
