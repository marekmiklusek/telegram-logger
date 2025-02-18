<?php

namespace MarekMiklusek\TelegramLogger;

final class TelegramLogger
{
    protected static array $logLevels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];

    public static function send($message, $level, $context = [])
    {
        $botToken = config('telegram-logger.bot_token');
        $chatId = config('telegram-logger.chat_id');
        $configuredLevel = config('telegram-logger.level', 'error');

        // Ensure levels exist
        if (!isset(self::$logLevels[$level]) || !isset(self::$logLevels[$configuredLevel])) {
            return;
        }

        // Only log if the event level is equal or more severe than the configured level
        if (self::$logLevels[$level] > self::$logLevels[$configuredLevel]) {
            return;
        }

        $text = "*Laravel Log*\n";
        $text .= "*Level:* " . strtoupper($level) . "\n";
        $text .= "*Message:* " . $message . "\n";
        $text .= "*Context:* " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        $text .= "*Time:* " . date('Y-m-d H:i:s');

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage?" . http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);

        file_get_contents($url);
    }
}
