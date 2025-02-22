<?php

namespace MarekMiklusek\TelegramLogger;

use Throwable;

final class TelegramLogger
{
    /**
     * Log levels
     */
    private static array $logLevels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];

    /**
     * Log level emojis
     */
    private static $levelEmoji = [
        'emergency' => '🆘',
        'alert'     => '🚨',
        'critical'  => '🚑',
        'error'     => '❌',
        'warning'   => '⚠️',
        'notice'    => '🔔',
        'info'      => 'ℹ️',
        'debug'     => '🔍',
    ];

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
        if (! isset(self::$logLevels[$level]) || ! isset(self::$logLevels[$configuredLevel])) {
            return;
        }

        // Only log if the event level is equal or more severe than the configured level
        // If .env is set to: TELEGRAM_LOG_LEVEL=error, only error, critical, alert, and emergency will be logged
        // If .env is set to: TELEGRAM_LOG_LEVEL=debug, all levels will be logged
        if (self::$logLevels[$level] > self::$logLevels[$configuredLevel]) {
            return;
        }

        $text = "🛠️ *Application:* `" . config('app.name') . "`\n";
        $text .= "🌍 *Environment:* `" . config('app.env') . "`\n\n";

        $levelIcon = self::$levelEmoji[$level] ?? '📛';
        $text .= "{$levelIcon} *Level:* `" . strtoupper($level) . "`\n";

        // Handle Exception in context
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];

            // Split the message at ", called in" if it exists
            $hasCalledIn = strpos($exception->getMessage(), ', called in') !== false;
            if ($hasCalledIn) {
                $parts = explode(', called in', $exception->getMessage());
                $errorMessage = $parts[0];
                $calledInPath = trim($parts[1]);

                // Remove the "on line" part from calledInPath and format with colon
                $filePath = preg_replace('/\s+on\s+line\s+(\d+)$/', ':$1', $calledInPath);
            } else {
                $errorMessage = $exception->getMessage();
                $filePath = $exception->getFile() . ':' . $exception->getLine();
            }

            // Only show the message if it's different from the exception message
            if ($message !== $exception->getMessage()) {
                $text .= "📝 *Message:* `{$message}`\n\n";
            }

            $text .= "🔥 *Exception Occurred \!*\n";
            $text .= "💥 *Message:* `" . self::normalizeFilePath($errorMessage) . ($hasCalledIn ? ', called in:' : '') . "`\n\n";

            $text .= "📌 *File:* ```copy\n" . self::normalizeFilePath($filePath) . "```\n\n";

        // Handle normal log message
        } else {
            $text .= "📝 *Message:* `{$message}`\n\n";
            $text .= "📌 *File:* ```copy\n" . self::getLogSource() . "```\n\n";

            if (! empty($context)) {
                $text .= "📂 *Context:* ```" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "```\n\n";
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
