<?php

namespace XnoxsProto\Client;

use XnoxsProto\TL\Functions\AuthSendCodeRequest;
use XnoxsProto\TL\Functions\AuthSignInRequest;
use XnoxsProto\TL\Functions\AuthCheckPasswordRequest;
use XnoxsProto\TL\Functions\AccountGetPasswordRequest;
use XnoxsProto\TL\Functions\AuthImportBotAuthorizationRequest;
use XnoxsProto\TL\Types\AuthSentCode;
use XnoxsProto\TL\Types\AuthAuthorization;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\Crypto\SRP;

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

        error_log(sprintf(
            '[XnoxsProto] account.password ctor=0x%08x flags=0x%08x hasPassword=%s hasRecovery=%s',
            $c, $flags, $hasPassword ? 'true' : 'false', $hasRecovery ? 'true' : 'false'
        ));

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
            error_log(sprintf('[XnoxsProto] curAlgoCtor=0x%08x', $curAlgoCtor));

            if ($curAlgoCtor === 0x004a2ff3 || $curAlgoCtor === 0x3a912d4a) {
                $result['salt1'] = $r->readBytes();
                $result['salt2'] = $r->readBytes();
                $result['g']     = $r->readInt();
                $result['p']     = $r->readBytes();
                error_log(sprintf(
                    '[XnoxsProto] SRP params: salt1_len=%d salt2_len=%d g=%d p_len=%d',
                    strlen($result['salt1']), strlen($result['salt2']),
                    $result['g'], strlen($result['p'])
                ));
            }
            // passwordKdfAlgoUnknown#d45ab096 or other — no extra fields

            $result['srp_B']  = $r->readBytes();
            $result['srp_id'] = $r->readLong();
            error_log(sprintf('[XnoxsProto] srp_B_len=%d srp_id=%d', strlen($result['srp_B']), $result['srp_id']));
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
