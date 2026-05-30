<?php
require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;

$API_ID   = 19001991;
$API_HASH = 'f3eb78228439ad8ac3b81729df992a9a';

$sessions = glob(__DIR__ . '/sessions/*.session') ?: [];
if (empty($sessions)) die("[FATAL] Tidak ada session.\n");

$phone  = pathinfo($sessions[0], PATHINFO_FILENAME);
$client = new TelegramClient($API_ID, $API_HASH);
$client->setSessionsDir(__DIR__ . '/sessions');
$client->start($phone);

$me = $client->getMe();
echo "[INFO] Login sebagai: {$me['first_name']} (ID: {$me['id']})\n";
echo "[INFO] Mendengarkan... kirim pesan dari akun lain sekarang.\n";
flush();

$client->onUpdate(function ($event) {
    if ($event->type === 'new_message') {
        $m = $event->message;
        printf("[%s] Pesan dari ID:%s → \"%s\"\n",
            date('H:i:s'),
            $m->fromUserId ?? 'self',
            substr($m->text ?? '[non-teks]', 0, 80)
        );
    } elseif ($event->type === 'user_status') {
        printf("[%s] Status user ID:%s → %s\n",
            date('H:i:s'),
            $event->user_id,
            $event->online ? 'ONLINE' : 'offline'
        );
    } else {
        printf("[%s] Update: %s\n", date('H:i:s'), $event->type);
    }
    flush();
});

$client->runUntilDisconnected();
