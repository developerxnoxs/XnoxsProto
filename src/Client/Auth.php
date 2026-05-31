<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\AuthSendCodeRequest;
use XnoxsProto\TL\Functions\AuthSignInRequest;
use XnoxsProto\TL\Functions\AuthCheckPasswordRequest;
use XnoxsProto\TL\Functions\AccountGetPasswordRequest;
use XnoxsProto\TL\Functions\AuthImportBotAuthorizationRequest;
use XnoxsProto\TL\Functions\AuthExportLoginTokenRequest;
use XnoxsProto\TL\Functions\AuthImportLoginTokenRequest;
use XnoxsProto\TL\Types\AuthSentCode;
use XnoxsProto\TL\Types\AuthAuthorization;
use XnoxsProto\TL\Types\AuthLoginToken;
use XnoxsProto\TL\Types\AuthLoginTokenMigrateTo;
use XnoxsProto\TL\Types\AuthLoginTokenSuccess;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\Crypto\SRP;
use XnoxsProto\Helpers\QRCodeHelper;

class Auth
{
    private TelegramClient $client;
    private ?string $phoneNumber    = null;
    private ?string $phoneCodeHash  = null;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    // =========================================================================
    // Phone login
    // =========================================================================

    public function sendCode(string $phoneNumber): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender = $this->client->getSender();
        if (!$sender) {
            throw new \RuntimeException('MTProto sender not initialized');
        }

        $this->phoneNumber = $phoneNumber;

        $request = new AuthSendCodeRequest(
            $phoneNumber,
            $this->client->getApiId(),
            $this->client->getApiHash()
        );

        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);

            if ($response['constructor'] !== AuthSentCode::CONSTRUCTOR_ID) {
                throw new \RuntimeException(sprintf(
                    'Unexpected constructor: 0x%08x, expected auth.sentCode',
                    $response['constructor']
                ));
            }

            $sentCode = AuthSentCode::fromReader($response['reader']);
            $this->phoneCodeHash = $sentCode->phoneCodeHash;

            return [
                'phone_number'    => $phoneNumber,
                'phone_code_hash' => $sentCode->phoneCodeHash,
                'type'            => $sentCode->type
            ];
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            if ($e->errorCode === 303) {
                if (preg_match('/(PHONE|USER|NETWORK)_MIGRATE_(\d+)/', $e->errorMessage, $matches)) {
                    $newDc = (int)$matches[2];
                    $this->client->connect($newDc, true);
                    return $this->sendCode($phoneNumber);
                }
            }
            throw $e;
        }
    }

    public function signIn(string $phoneNumber, string $phoneCodeHash, string $phoneCode): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender = $this->client->getSender();
        if (!$sender) {
            throw new \RuntimeException('MTProto sender not initialized');
        }

        $request = new AuthSignInRequest($phoneNumber, $phoneCodeHash, $phoneCode);

        try {
            $response = $sender->send($request);

            if ($response['constructor'] !== AuthAuthorization::CONSTRUCTOR_ID) {
                throw new \RuntimeException(sprintf(
                    'Unexpected constructor: 0x%08x, expected auth.authorization',
                    $response['constructor']
                ));
            }

            $authorization = AuthAuthorization::fromReader($response['reader']);
            $this->client->getSession()->setAuthorized(true, $authorization->user->id ?? null);

            return [
                'user' => [
                    'id'         => $authorization->user->id,
                    'first_name' => $authorization->user->firstName,
                    'last_name'  => $authorization->user->lastName,
                    'username'   => $authorization->user->username,
                    'phone'      => $authorization->user->phone,
                    'authorized' => true
                ]
            ];
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            // SESSION_PASSWORD_NEEDED — account has 2FA enabled
            if ($e->errorMessage === 'SESSION_PASSWORD_NEEDED') {
                throw new \RuntimeException(
                    'SESSION_PASSWORD_NEEDED: Account has two-step verification. ' .
                    'Call checkPassword($password) to complete login.',
                    401
                );
            }
            throw $e;
        }
    }

    // =========================================================================
    // Two-Factor Authentication (2FA / Cloud Password)
    // =========================================================================

    /**
     * Check and submit the 2FA cloud password.
     *
     * Call this when signIn() throws SESSION_PASSWORD_NEEDED.
     *
     * @param string $password The user's cloud password
     * @return array User info array (same format as signIn())
     */
    public function checkPassword(string $password): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender = $this->client->getSender();

        // Step 1: Fetch SRP parameters from Telegram
        $pwdRequest  = new AccountGetPasswordRequest();
        $pwdRequest  = $this->client->wrapFirstRequest($pwdRequest);
        $pwdResponse = $sender->send($pwdRequest);

        $srpParams = $this->parseAccountPassword($pwdResponse);

        if (!$srpParams['has_password']) {
            throw new \RuntimeException('Account does not have a cloud password enabled');
        }

        // Step 2: Compute SRP proof
        $proof = SRP::computeCheck(
            $password,
            $srpParams['salt1'],
            $srpParams['salt2'],
            $srpParams['g'],
            $srpParams['p'],
            $srpParams['srp_B'],
            $srpParams['srp_id']
        );

        // Step 3: Send auth.checkPassword
        $request = new AuthCheckPasswordRequest(
            $proof['srp_id'],
            $proof['A'],
            $proof['M1']
        );

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            if (str_contains($e->errorMessage, 'PASSWORD_HASH_INVALID')) {
                throw new \RuntimeException('Wrong password. Please try again.', 400, $e);
            }
            throw $e;
        }

        if ($response['constructor'] !== AuthAuthorization::CONSTRUCTOR_ID) {
            throw new \RuntimeException(sprintf(
                'Unexpected constructor from checkPassword: 0x%08x',
                $response['constructor']
            ));
        }

        $authorization = AuthAuthorization::fromReader($response['reader']);
        $this->client->getSession()->setAuthorized(true, $authorization->user->id ?? null);

        return [
            'user' => [
                'id'         => $authorization->user->id,
                'first_name' => $authorization->user->firstName,
                'last_name'  => $authorization->user->lastName,
                'username'   => $authorization->user->username,
                'phone'      => $authorization->user->phone,
                'authorized' => true
            ]
        ];
    }

    /**
     * Check whether account has 2FA enabled without logging in.
     * Returns info about the current password state.
     */
    public function getPasswordInfo(): array
    {
        $sender     = $this->client->getSender();
        $request    = new AccountGetPasswordRequest();
        $request    = $this->client->wrapFirstRequest($request);
        $response   = $sender->send($request);
        $params     = $this->parseAccountPassword($response);

        return [
            'has_password' => $params['has_password'],
            'hint'         => $params['hint'] ?? '',
            'has_recovery' => $params['has_recovery'] ?? false,
        ];
    }

    // =========================================================================
    // Bot Token Login
    // =========================================================================

    /**
     * Login as a bot using a bot token from @BotFather.
     *
     * @param string $botToken Token in format "123456:ABC-DEF..."
     * @return array Bot info array
     */
    public function loginAsBot(string $botToken): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender  = $this->client->getSender();
        $request = new AuthImportBotAuthorizationRequest(
            $this->client->getApiId(),
            $this->client->getApiHash(),
            $botToken
        );
        $request = $this->client->wrapFirstRequest($request);

        try {
            $response = $sender->send($request);
        } catch (\XnoxsProto\Exceptions\RPCException $e) {
            if ($e->errorCode === 303) {
                if (preg_match('/(PHONE|USER|NETWORK)_MIGRATE_(\d+)/', $e->errorMessage, $matches)) {
                    $newDc = (int)$matches[2];
                    $this->client->connect($newDc, true);
                    return $this->loginAsBot($botToken);
                }
            }
            throw new \RuntimeException("[{$e->errorCode}] {$e->errorMessage}", $e->errorCode, $e);
        }

        if ($response['constructor'] !== AuthAuthorization::CONSTRUCTOR_ID) {
            throw new \RuntimeException(sprintf(
                'Unexpected constructor from bot login: 0x%08x',
                $response['constructor']
            ));
        }

        $authorization = AuthAuthorization::fromReader($response['reader']);
        $this->client->getSession()->setAuthorized(true, $authorization->user->id ?? null);

        return [
            'bot'  => true,
            'user' => [
                'id'         => $authorization->user->id,
                'first_name' => $authorization->user->firstName,
                'username'   => $authorization->user->username,
                'bot'        => true,
                'authorized' => true
            ]
        ];
    }

    // =========================================================================
    // QR-Code Login
    // =========================================================================

    /**
     * Export a QR login token from Telegram.
     *
     * Calls auth.exportLoginToken and returns the raw token info plus the
     * ready-made tg://login URL that should be encoded into a QR code.
     *
     * @param int[] $exceptIds  Already-authorised user IDs to exclude (optional)
     * @return array {
     *   'token'   => string (raw bytes),
     *   'expires' => int    (Unix timestamp),
     *   'url'     => string (tg://login?token=<base64url>),
     * }
     */
    public function exportLoginToken(array $exceptIds = []): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender  = $this->client->getSender();
        $request = new AuthExportLoginTokenRequest(
            $this->client->getApiId(),
            $this->client->getApiHash(),
            $exceptIds
        );
        $request  = $this->client->wrapFirstRequest($request);
        $response = $sender->send($request);

        return $this->parseLoginTokenResponse($response);
    }

    /**
     * Import a login token on a different DC (DC-migration step).
     *
     * Called automatically by loginWithQR() when auth.exportLoginToken returns
     * auth.loginTokenMigrateTo.  You normally do not need to call this directly.
     *
     * @param string $token Raw token bytes (from AuthLoginTokenMigrateTo::$token)
     * @return array Same structure as exportLoginToken() or ['authorized' => true]
     */
    public function importLoginToken(string $token): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender  = $this->client->getSender();
        $request = new AuthImportLoginTokenRequest($token);
        $request = $this->client->wrapFirstRequest($request);
        $response = $sender->send($request);

        return $this->parseLoginTokenResponse($response);
    }

    /**
     * Full QR-code login flow with automatic polling.
     *
     * The method calls auth.exportLoginToken, invokes $onQrUpdate to display
     * the QR code, then polls every ~5 seconds until:
     *   - auth.loginTokenSuccess  → login complete, returns user info
     *   - auth.loginTokenMigrateTo → migrates DC automatically and finalises
     *   - timeout ($maxWaitSecs)   → throws RuntimeException
     *
     * @param callable|null $onQrUpdate
     *        Signature: function(string $url, int $expires): void
     *        Called each time a new QR code is available.
     *        $url    = tg://login?token=<base64url> — render this as the QR.
     *        $expires = Unix timestamp when the token expires.
     *        If null, a default terminal renderer (unicode blocks) is used.
     *
     * @param callable|null $passwordCallback
     *        Signature: function(): string
     *        Called if the account has 2FA enabled after QR scan.
     *        If null, prompts via STDIN.
     *
     * @param int $maxWaitSecs
     *        Maximum seconds to wait for the QR to be scanned (default 120).
     *
     * @return array User info array, same structure as signIn().
     */
    public function loginWithQR(
        ?callable $onQrUpdate      = null,
        ?callable $passwordCallback = null,
        int       $maxWaitSecs     = 120
    ): array {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $deadline = time() + $maxWaitSecs;

        while (time() < $deadline) {
            // Request a fresh QR token
            $tokenInfo = $this->exportLoginToken();

            if (isset($tokenInfo['authorized']) && $tokenInfo['authorized']) {
                // loginTokenSuccess was returned immediately (unlikely but handle it)
                return $tokenInfo['user'];
            }

            if (isset($tokenInfo['migrate_to'])) {
                // DC migration required
                $newDc    = $tokenInfo['migrate_to']['dc_id'];
                $newToken = $tokenInfo['migrate_to']['token'];
                $this->client->connect($newDc, true);
                try {
                    $imported = $this->importLoginToken($newToken);
                } catch (\XnoxsProto\Exceptions\RPCException $e) {
                    if ($e->errorMessage === 'SESSION_PASSWORD_NEEDED') {
                        $authorization = $this->performQR2FA($passwordCallback);
                        $this->handleQRAuthorization($authorization, null);
                        return $this->buildUserArray($authorization);
                    }
                    throw $e;
                }
                if (isset($imported['authorized']) && $imported['authorized']) {
                    $this->handleQRAuthorization($imported['authorization'], $passwordCallback);
                    return $this->buildUserArray($imported['authorization']);
                }
                // Re-loop with the new DC
                continue;
            }

            // Display the QR code
            $url     = $tokenInfo['url'];
            $expires = $tokenInfo['expires'];

            if ($onQrUpdate !== null) {
                $onQrUpdate($url, $expires);
            } else {
                $this->defaultQRDisplay($url, $expires);
            }

            // Poll every 5 seconds until the token expires (or a bit before)
            $pollInterval = 5;
            $tokenTtl     = max(1, $expires - time());
            $polls        = (int)ceil($tokenTtl / $pollInterval);

            for ($p = 0; $p < $polls; $p++) {
                sleep(min($pollInterval, max(1, $expires - time())));

                if (time() >= $deadline) {
                    throw new \RuntimeException(
                        'QR login timed out after ' . $maxWaitSecs . ' seconds. ' .
                        'The QR code was not scanned in time.'
                    );
                }

                // Re-export to check status
                try {
                    $check = $this->exportLoginToken();
                } catch (\XnoxsProto\Exceptions\RPCException $e) {
                    if (str_contains($e->errorMessage, 'AUTH_TOKEN_EXPIRED')) {
                        break; // re-loop for a new QR
                    }
                    if ($e->errorMessage === 'SESSION_PASSWORD_NEEDED') {
                        // QR scanned — account has 2FA, complete with cloud password
                        $authorization = $this->performQR2FA($passwordCallback);
                        $this->handleQRAuthorization($authorization, null);
                        return $this->buildUserArray($authorization);
                    }
                    throw $e;
                }

                if (isset($check['authorized']) && $check['authorized']) {
                    // Login complete!
                    $this->handleQRAuthorization($check['authorization'], $passwordCallback);
                    return $this->buildUserArray($check['authorization']);
                }

                if (isset($check['migrate_to'])) {
                    $newDc    = $check['migrate_to']['dc_id'];
                    $newToken = $check['migrate_to']['token'];
                    $this->client->connect($newDc, true);
                    try {
                        $imported = $this->importLoginToken($newToken);
                    } catch (\XnoxsProto\Exceptions\RPCException $e) {
                        if ($e->errorMessage === 'SESSION_PASSWORD_NEEDED') {
                            $authorization = $this->performQR2FA($passwordCallback);
                            $this->handleQRAuthorization($authorization, null);
                            return $this->buildUserArray($authorization);
                        }
                        throw $e;
                    }
                    if (isset($imported['authorized']) && $imported['authorized']) {
                        $this->handleQRAuthorization($imported['authorization'], $passwordCallback);
                        return $this->buildUserArray($imported['authorization']);
                    }
                    break; // re-loop from the outer while
                }

                // Still returning auth.loginToken → show updated QR if URL changed
                if ($check['url'] !== $url) {
                    $url     = $check['url'];
                    $expires = $check['expires'];
                    if ($onQrUpdate !== null) {
                        $onQrUpdate($url, $expires);
                    } else {
                        $this->defaultQRDisplay($url, $expires);
                    }
                }
            }
            // Token expired → outer while loops for a fresh QR
        }

        throw new \RuntimeException(
            'QR login timed out after ' . $maxWaitSecs . ' seconds.'
        );
    }

    // =========================================================================
    // QR Login — private helpers
    // =========================================================================

    /**
     * Parse the response from auth.exportLoginToken or auth.importLoginToken.
     *
     * Returns one of:
     *   ['token' => bytes, 'expires' => int, 'url' => string]            → still waiting
     *   ['migrate_to' => ['dc_id' => int, 'token' => bytes]]             → DC migration
     *   ['authorized' => true, 'authorization' => AuthAuthorization]     → success
     */
    private function parseLoginTokenResponse(array $response): array
    {
        $ctor = $response['constructor'];
        $r    = $response['reader'];

        switch ($ctor) {
            case AuthLoginToken::CONSTRUCTOR_ID:
                $lt  = AuthLoginToken::fromReader($r);
                $url = QRCodeHelper::buildTgUrl($lt->token);
                return [
                    'token'   => $lt->token,
                    'expires' => $lt->expires,
                    'url'     => $url,
                ];

            case AuthLoginTokenMigrateTo::CONSTRUCTOR_ID:
                $mt = AuthLoginTokenMigrateTo::fromReader($r);
                return [
                    'migrate_to' => [
                        'dc_id' => $mt->dcId,
                        'token' => $mt->token,
                    ],
                ];

            case AuthLoginTokenSuccess::CONSTRUCTOR_ID:
                $ts = AuthLoginTokenSuccess::fromReader($r);
                return [
                    'authorized'    => true,
                    'authorization' => $ts->authorization,
                ];

            default:
                throw new \RuntimeException(sprintf(
                    'Unexpected constructor from auth.exportLoginToken: 0x%08x',
                    $ctor
                ));
        }
    }

    /**
     * Complete a QR login that triggered SESSION_PASSWORD_NEEDED (2FA).
     *
     * Behaviour mirrors start(): prompts automatically via STDIN when no
     * $passwordCallback is provided, and retries indefinitely on wrong password
     * (re-fetching fresh SRP nonces each round). When a $passwordCallback is
     * supplied it is called again on each retry so the caller can ask the user
     * for a new value from a GUI or other interface.
     *
     * @param callable|null $passwordCallback  fn(): string — return the cloud password
     */
    private function performQR2FA(?callable $passwordCallback): AuthAuthorization
    {
        $sender  = $this->client->getSender();
        $attempt = 0;
        $hint    = '';

        while (true) {
            $attempt++;

            // ── Fetch fresh SRP parameters every attempt (nonces change each round) ──
            $pwdRequest  = new AccountGetPasswordRequest();
            $pwdRequest  = $this->client->wrapFirstRequest($pwdRequest);
            $pwdResponse = $sender->send($pwdRequest);
            $srpParams   = $this->parseAccountPassword($pwdResponse);

            if (!$srpParams['has_password']) {
                throw new \RuntimeException(
                    'SERVER_ERROR: SESSION_PASSWORD_NEEDED tapi akun tidak punya cloud password.'
                );
            }

            // Show hint once on the first attempt (STDIN only)
            if ($attempt === 1 && $passwordCallback === null) {
                $hint = $srpParams['hint'] ?? '';
                echo "\n🔒  Akun ini dilindungi Two-Step Verification (2FA).\n";
                if ($hint !== '') {
                    echo "💡  Petunjuk password: {$hint}\n";
                }
            }

            // ── Obtain password ───────────────────────────────────────────────
            if ($passwordCallback !== null) {
                $password = (string)$passwordCallback();
            } else {
                echo "🔑  Masukkan cloud password (2FA): ";
                if (function_exists('readline')) {
                    $password = (string)readline('');
                } else {
                    $password = trim((string)fgets(STDIN));
                }
                echo "\n";
            }

            // ── Compute SRP proof ─────────────────────────────────────────────
            $proof = SRP::computeCheck(
                $password,
                $srpParams['salt1'],
                $srpParams['salt2'],
                $srpParams['g'],
                $srpParams['p'],
                $srpParams['srp_B'],
                $srpParams['srp_id']
            );

            // ── Send auth.checkPassword ───────────────────────────────────────
            $request = new AuthCheckPasswordRequest(
                $proof['srp_id'],
                $proof['A'],
                $proof['M1']
            );

            try {
                $response = $sender->send($request);
            } catch (\XnoxsProto\Exceptions\RPCException $e) {
                if (str_contains($e->errorMessage, 'PASSWORD_HASH_INVALID')) {
                    // Wrong password — retry (same behaviour as start())
                    if ($passwordCallback === null) {
                        echo "❌  Password salah. Coba lagi.\n";
                    } else {
                        echo "❌  Password 2FA salah (percobaan #{$attempt}). Callback dipanggil ulang…\n";
                    }
                    continue; // re-enter loop with fresh SRP params
                }
                throw $e;
            }

            if ($response['constructor'] !== AuthAuthorization::CONSTRUCTOR_ID) {
                throw new \RuntimeException(sprintf(
                    'Unexpected constructor from auth.checkPassword: 0x%08x',
                    $response['constructor']
                ));
            }

            return AuthAuthorization::fromReader($response['reader']);
        }
    }

    /**
     * After receiving loginTokenSuccess, persist the session and handle 2FA
     * if setup_password_required is set.
     */
    private function handleQRAuthorization(
        AuthAuthorization $authorization,
        ?callable         $passwordCallback
    ): void {
        $this->client->getSession()->setAuthorized(true, $authorization->user->id ?? null);

        // Cache the self user into the session entity store so that subsequent
        // calls (e.g. getDialogs) can resolve the user's own DM by name even
        // on a fresh session that has no prior entity cache (QR always starts fresh).
        $user = $authorization->user;
        if ($user && $user->id) {
            $row = ['id' => $user->id, 'type' => 'user', 'bot' => false];
            if ($user->accessHash !== null) $row['access_hash'] = $user->accessHash;
            if ($user->firstName  !== null) $row['first_name']  = $user->firstName;
            if ($user->lastName   !== null) $row['last_name']   = $user->lastName;
            if ($user->username   !== null) $row['username']    = $user->username;
            if ($user->phone      !== null) $row['phone']       = $user->phone;
            $this->client->getSession()->processEntities([$row]);
        }

        // If the account requires cloud password setup (rare after QR scan),
        // let it pass — the caller can check getPasswordInfo() separately.
        // Standard 2FA verification is not needed for QR-based sessions because
        // the authorising device already authenticated the session.
    }

    /**
     * Build the standard user info array from an AuthAuthorization object.
     */
    private function buildUserArray(AuthAuthorization $authorization): array
    {
        return [
            'user' => [
                'id'         => $authorization->user->id,
                'first_name' => $authorization->user->firstName,
                'last_name'  => $authorization->user->lastName,
                'username'   => $authorization->user->username,
                'phone'      => $authorization->user->phone,
                'authorized' => true,
            ],
        ];
    }

    /**
     * Default terminal QR display used when no $onQrUpdate callback is provided.
     * Clears the previous QR and prints a new one with instructions.
     */
    private function defaultQRDisplay(string $url, int $expires): void
    {
        // ANSI clear screen (works in most terminals)
        echo "\033[2J\033[H";

        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║            Login via QR Code — XnoxsProto               ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        $qr = QRCodeHelper::terminalQR($url);
        if ($qr !== '') {
            echo $qr;
        } else {
            // Fallback: URL only (data too long for embedded QR; unlikely)
            echo "  URL: $url\n";
        }

        $ttl = max(0, $expires - time());
        echo "\n📱  Scan kode QR di atas dengan aplikasi Telegram yang sudah login.\n";
        echo "     Menu → Settings → Devices → Link Desktop Device\n";
        echo "⏱   Berlaku: {$ttl} detik\n\n";
    }

    // =========================================================================
    // Status / Logout
    // =========================================================================

    public function isAuthorized(): bool
    {
        return $this->client->getSession()->isUserAuthorized();
    }

    public function logOut(): bool
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $this->client->getSession()->setAuthorized(false, null);
        $this->client->getSession()->setAuthKey(null);
        $this->phoneNumber   = null;
        $this->phoneCodeHash = null;

        return true;
    }

    // =========================================================================
    // Internal: parse account.Password response
    // =========================================================================

    private function parseAccountPassword(array $response): array
    {
        $c = $response['constructor'];
        $r = $response['reader'];

        // account.password#960ea646 (old) or account.password#957b50fb (new, Layer 166+)
        $isNewVersion = ($c === 0x957b50fb);
        if ($c !== 0x960ea646 && !$isNewVersion) {
            throw new \RuntimeException(sprintf('Unexpected account.getPassword response: 0x%08x', $c));
        }

        // ── TL field order — flag bits DIFFER between constructors ─────────────
        //
        // account.password#960ea646 (old):
        //   flags:#
        //   has_recovery:flags.9?true  has_secure_values:flags.13?true
        //   has_password:flags.2?true  current_algo:flags.2?PasswordKdfAlgo
        //   srp_B:flags.2?bytes  srp_id:flags.2?long  hint:flags.3?string
        //   email_unconfirmed_pattern:flags.4?string
        //   new_algo:PasswordKdfAlgo  new_secure_algo:SecurePasswordKdfAlgo
        //   secure_random:bytes  pending_reset_date:flags.4?int
        //   login_email_pattern:flags.11?string
        //
        // account.password#957b50fb (new, Layer 166+) — DIFFERENT flag bits:
        //   flags:#
        //   has_recovery:flags.0?true  has_secure_values:flags.1?true
        //   has_password:flags.2?true  current_algo:flags.2?PasswordKdfAlgo
        //   srp_B:flags.2?bytes  srp_id:flags.2?long  hint:flags.3?string
        //   email_unconfirmed_pattern:flags.4?string
        //   new_algo:PasswordKdfAlgo  new_secure_algo:SecurePasswordKdfAlgo
        //   secure_random:bytes  pending_reset_date:flags.5?int
        //   login_email_pattern:flags.6?string
        //   (NO flags2 field)
        //
        // PasswordKdfAlgo constructors (current_algo / new_algo):
        //   0x004a2ff3 — old ctor — salt1:bytes salt2:bytes g:int p:bytes
        //   0x3a912d4a — NEW ctor — salt1:bytes salt2:bytes g:int p:bytes
        //   0xd45ab096 — passwordKdfAlgoUnknown — no fields

        $flags = $r->readInt();

        // has_password is flags.2 in BOTH constructors
        $hasPassword = (bool)($flags & (1 << 2));

        // Other bit positions differ by constructor
        if ($isNewVersion) {
            $hasRecovery       = (bool)($flags & (1 << 0));  // flags.0
            $hasHint           = (bool)($flags & (1 << 3));  // flags.3
            $hasEmailUnconf    = (bool)($flags & (1 << 4));  // flags.4
            $hasPendingReset   = (bool)($flags & (1 << 5));  // flags.5
            $hasLoginEmail     = (bool)($flags & (1 << 6));  // flags.6
        } else {
            $hasRecovery       = (bool)($flags & (1 << 9));  // flags.9
            $hasHint           = (bool)($flags & (1 << 3));  // flags.3
            $hasEmailUnconf    = (bool)($flags & (1 << 4));  // flags.4
            $hasPendingReset   = (bool)($flags & (1 << 4));  // flags.4 (same bit)
            $hasLoginEmail     = (bool)($flags & (1 << 11)); // flags.11
        }

        $result = [
            'has_password' => $hasPassword,
            'has_recovery' => $hasRecovery,
            'salt1'        => '',
            'salt2'        => '',
            'g'            => 0,
            'p'            => '',
            'srp_B'        => '',
            'srp_id'       => 0,
            'hint'         => '',
        ];

        // ── Conditional fields gated on has_password (flags.2) ───────────────

        if ($hasPassword) {
            // Known PasswordKdfAlgo constructors that have salt1/salt2/g/p fields:
            //   0x004a2ff3 — original ctor
            //   0x3a912d4a — newer ctor (same fields, different CRC32)
            $curAlgoCtor = $r->readInt();

            if ($curAlgoCtor === 0x004a2ff3 || $curAlgoCtor === 0x3a912d4a) {
                $result['salt1'] = $r->readBytes();
                $result['salt2'] = $r->readBytes();
                $result['g']     = $r->readInt();
                $result['p']     = $r->readBytes();
            }
            // passwordKdfAlgoUnknown#d45ab096 or other — no extra fields

            $result['srp_B']  = $r->readBytes();
            $result['srp_id'] = $r->readLong();
        }

        // hint (flags.3 in both)
        if ($hasHint) {
            $result['hint'] = $r->readString();
        }

        // email_unconfirmed_pattern (flags.4 in both)
        if ($hasEmailUnconf) {
            $r->readString();
        }

        // ── Always-present fields ────────────────────────────────────────────

        // new_algo: PasswordKdfAlgo
        $newAlgoCtor = $r->readInt();
        $this->skipPasswordKdfAlgo($r, $newAlgoCtor);

        // new_secure_algo: SecurePasswordKdfAlgo
        $secAlgoCtor = $r->readInt();
        $this->skipSecurePasswordKdfAlgo($r, $secAlgoCtor);

        // secure_random: bytes
        $r->readBytes();

        // pending_reset_date — flags.4 (old) or flags.5 (new)
        if ($hasPendingReset) {
            $r->readInt();
        }

        // login_email_pattern — flags.11 (old) or flags.6 (new)
        if ($hasLoginEmail) {
            $r->readString();
        }

        return $result;
    }

    private function skipPasswordKdfAlgo(BinaryReader $r, int $ctor): void
    {
        // 0x004a2ff3 — passwordKdfAlgoSHA256...ModPow (original)
        // 0x3a912d4a — passwordKdfAlgoSHA256...ModPow (newer CRC, same fields)
        if ($ctor === 0x004a2ff3 || $ctor === 0x3a912d4a) {
            $r->readBytes(); // salt1
            $r->readBytes(); // salt2
            $r->readInt();   // g
            $r->readBytes(); // p
        }
        // passwordKdfAlgoUnknown#d45ab096 — no fields
    }

    private function skipSecurePasswordKdfAlgo(BinaryReader $r, int $ctor): void
    {
        // securePasswordKdfAlgoUnknown#004a2ff3        — NO fields
        // securePasswordKdfAlgoPBKDF2HMACSHA512iter100000#86471d92 — salt:bytes
        // securePasswordKdfAlgoSHA512#cdc27a1f          — salt:bytes
        if ($ctor === 0x86471d92 || $ctor === 0xcdc27a1f) {
            $r->readBytes(); // salt
        }
    }
}
