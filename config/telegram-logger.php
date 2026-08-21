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
     *
     * @see https://core.telegram.org/bots/api
     */
    'bot_token' => (string) env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id' => (string) env('TELEGRAM_CHAT_ID', ''),
    'level' => (string) env('TELEGRAM_LOG_LEVEL', 'error'),
    'silent_notification' => (bool) env('TELEGRAM_LOG_SILENT', false),
    'is_enabled' => (bool) env('TELEGRAM_LOG_ENABLED', true),
    'throw_on_failure' => (bool) env('TELEGRAM_LOG_THROW_ON_FAILURE', false),

];
