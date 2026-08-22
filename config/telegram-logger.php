<?php

declare(strict_types=1);

return [

    /**
     * bot_token           Telegram Bot API token.
     * chat_id             Chat ID where logs are sent.
     * level               Minimum severity: emergency, alert, critical, error, warning, notice, info, debug.
     * silent_notification Deliver without sound.
     * is_enabled          Master switch.
     * throw_on_failure    Throw on delivery failure instead of swallowing it. Keep false in production.
     * max_per_minute      Messages sent per minute before further logs are dropped. 0 disables throttling.
     * dedupe_seconds      Window in which an identical message is sent only once. 0 disables deduplication.
     * redact_keys         Context keys whose values are replaced with [REDACTED]. Matched case-insensitively
     *                     as a substring, so "password" also covers "password_confirmation".
     *
     * @see https://core.telegram.org/bots/api
     */
    'bot_token' => (string) env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id' => (string) env('TELEGRAM_CHAT_ID', ''),
    'level' => (string) env('TELEGRAM_LOG_LEVEL', 'error'),
    'silent_notification' => (bool) env('TELEGRAM_LOG_SILENT', false),
    'is_enabled' => (bool) env('TELEGRAM_LOG_ENABLED', true),
    'throw_on_failure' => (bool) env('TELEGRAM_LOG_THROW_ON_FAILURE', false),
    'max_per_minute' => (int) env('TELEGRAM_LOG_MAX_PER_MINUTE', 20),
    'dedupe_seconds' => (int) env('TELEGRAM_LOG_DEDUPE_SECONDS', 60),
    'redact_keys' => [
        'password',
        'secret',
        'token',
        'authorization',
        'api_key',
        'apikey',
        'credit_card',
        'cvv',
    ],

];
