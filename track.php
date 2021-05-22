#!/usr/bin/env php
<?php

require_once __DIR__.'/vendor/autoload.php';

class UserConfig implements ExpressRuSDK\Providers\UserConfigProviderInterface {

    protected $login;
    protected $sigkey;
    protected $authkey;

    public function __construct($login, $sigkey, $authkey) {
        $this->login = $login;
        $this->sigkey = $sigkey;
        $this->authkey = $authkey;
    }

    public function getLogin() {
        return $this->login;
    }

    public function getSignatureKey() {
        return $this->sigkey;
    }

    public function getAuthorizationKey() {
        return $this->authkey;
    }

}

class State {

    protected $__file;
    protected $__state = [];

    public function __construct(string $file) {
        $this->__file = $file;
        $this->load();
    }

    public function __destruct() {
        file_put_contents($this->__file, serialize($this->__state));
    }

    public function __get($key) {
        return $this->__state[$key] ?? null;
    }

    public function __set($key, $value) {
        $this->__state[$key] = $value;
    }

    protected function load() {
        if (file_exists($this->__file))
            $this->__state = unserialize(file_get_contents($this->__file));
    }

}

class TelegramNotifier {

    protected $token;
    protected $chat_id;

    public function __construct(string $token, int $chat_id) {
        $this->token = $token;
        $this->chat_id = $chat_id;
    }

    public function notify(string $html) {
        $ch = curl_init();
        $url = 'https://api.telegram.org/bot'.$this->token.'/sendMessage';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $this->chat_id,
            'text' => $html,
            'parse_mode' => 'html',
            'disable_web_page_preview' => 1
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

}

function getopts() {
    $keys = [
        'tracking-number',
        'tracking-date',
        'user-login',
        'user-signature-key',
        'user-authorization-key',
        'telegram-chat-id',
        'telegram-token',
    ];
    $options = getopt('', array_map(function($s) { return "$s:"; }, $keys));

    $err = false;
    foreach ($keys as $key) {
        if (!isset($options[$key])) {
            echo "--$key is required\n";
            $err = true;
        }
    }
    if ($err)
        exit(1);

    return $options;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$options = getopts();
$state = new State(getenv('HOME').'/.express-ru-tracker-'.$options['tracking-number']);
$telegram = new TelegramNotifier($options['telegram-token'], (int)$options['telegram-chat-id']);

$user_config = new UserConfig($options['user-login'], $options['user-signature-key'], $options['user-authorization-key']);
$sdk = new ExpressRuSDK\SDK($user_config);
$api = $sdk->getApiTransmitter();
$method = new ExpressRuSDK\Api\Methods\GetTrackingStatusesMethod($options['tracking-number'], $options['tracking-date']);
$response = $api->transmitMethod($method);

$result = $response->getResult();
if (empty($result))
    exit(2);

$new_entries = [];
$prev_time = $state->time ?? 0;
$entries = reset($result);
foreach ($entries as $entry) {
    $time = strtotime($entry['date']);
    if ($time <= $prev_time)
        continue;

    $new_entries[] = $entry;
    $prev_time = $time;
}

$state->time = $prev_time;

if (!empty($new_entries)) {
    $new_entries = array_map(function(array $entry) {
        $s = "<i>".$entry['date']."</i>\n";
        $s .= htmlspecialchars($entry['status'])."\n";
        $s .= htmlspecialchars($entry['note']);
        return $s;
    }, $new_entries);
    $text = "Отслеживание заказа <b>{$options['tracking-number']}</b>\n\n".implode("\n\n", $new_entries);
    $telegram->notify($text);
}