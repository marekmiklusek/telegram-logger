<?php

namespace MarekMiklusek\TelegramLogger;

use MarekMiklusek\TelegramLogger\Enums\LevelEnum;
use Throwable;

final class TelegramLogger
{
    
    /**
     * Send log message or exception to Telegram
     */
    public static function send(string $level, string $message, array $context = []): void
    {
        $botToken = config('telegram-logger.bot_token');
        $chatId = config('telegram-logger.chat_id');
        $configuredLevel = config('telegram-logger.level', 'error');
        $silentNotification = config('telegram-logger.silent_notification');
        $isEnabled = config('telegram-logger.is_enabled');

        // Ensure the logger is enabled
        if (! $isEnabled) return;

        // Ensure levels exist
        if (! LevelEnum::tryFrom($level) || ! LevelEnum::tryFrom($configuredLevel)) {
            return;
        }

        // Only log if the event level is equal or more severe than the configured level
        // If .env is set to: TELEGRAM_LOG_LEVEL=error, only error, critical, alert, and emergency will be logged
        // If .env is set to: TELEGRAM_LOG_LEVEL=debug, all levels will be logged
        if (LevelEnum::tryFrom($level)->value > LevelEnum::tryFrom($configuredLevel)->value) {
            return;
        }

        $text = "🛠️ *Application:* `" . config('app.name') . "`\n";
        $text .= "🌍 *Environment:* `" . config('app.env') . "`\n\n";        

        $levelEnum = LevelEnum::tryFrom($level);
        $levelIcon = $levelEnum ? $levelEnum->getEmoji() : '📛';

        $text .= "{$levelIcon} *Level:* `" . strtoupper($level) . "`\n";

        // Handle Exception in context
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $escapedFilePath = self::normalizeFilePath($exception->getFile() . ':' . $exception->getLine());

            // Only show the message if it's different from the exception message
            if ($message !== $exception->getMessage()) {
                $text .= "📝 *Message:* `{$message}`\n\n";
            }

            $text .= "🔥 *Exception Occurred \!*\n";
            $text .= "⚡ *Exception Message:* `" . $exception->getMessage() . "`\n\n";

            $text .= "📌 *File:* ```copy\n{$escapedFilePath}```\n\n";

        // Handle normal log message
        } else {
            $text .= "📝 *Message:* `{$message}`\n\n";
            $text .= "📌 *File:* ```copy\n" . self::getLogSource() . "```\n\n";

            if (! empty($context)) {
                $text .= "📂 *Context:* ```".json_encode($context, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."```\n\n";
            }
        }

        $text .= "⏳ *Time:* `" . date('Y-m-d H:i:s') . "`";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage?" . http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'disable_notification' => $silentNotification,
        ]);

        file_get_contents($url);
    }

    /**
     * Get the source of the log message
     */
    private static function getLogSource(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        foreach ($trace as $item) {
            if (! isset($item['file'])) continue;

            $filePath = self::normalizeFilePath($item['file']);

            // Exclude Laravel core, vendor files and TelegramLogger
            if (
                ! str_contains($filePath, 'vendor/') &&
                ! str_contains($filePath, 'Illuminate/') &&
                ! str_contains($filePath, 'TelegramLogger')
            ) {
                return $filePath . ':' . $item['line'];
            }
        }

        return 'Unknown';
    }

    /**
     * Normalize file path
     */
    private static function normalizeFilePath(string $filePath): string
    {
        return str_replace('\\', '/', $filePath);
    }
}
