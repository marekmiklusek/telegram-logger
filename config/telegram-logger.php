<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'level' => env('TELEGRAM_LOG_LEVEL', 'error'),
];