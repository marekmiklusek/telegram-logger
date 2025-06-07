<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use Throwable;

final class TelegramLogger
{
    /**
     * Log levels
     */
    private static array $logLevels = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /**
     * Log level emojis
     */
    private static $levelEmoji = [
        'emergency' => '🆘',
        'alert' => '🚨',
        'critical' => '🚑',
        'error' => '❌',
        'warning' => '⚠️',
        'notice' => '🔔',
        'info' => 'ℹ️',
        'debug' => '🔍',
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
        if (! $isEnabled) {
            return;
        }

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

        $text = '🛠️ *Application:* '.self::escapeSpecialChars(config('app.name'))."\n";
        $text .= '🌍 *Environment:* '.self::escapeSpecialChars(config('app.env'))."\n\n";

        $levelIcon = self::$levelEmoji[$level] ?? '📛';
        $text .= "{$levelIcon} *Level:* ".strtoupper($level)."\n";

        // Handle Exception in context
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $calledInPosition = strpos($exception->getMessage(), ', called in');

            if ($calledInPosition !== false) {
                // Extract the clean error message without the "called in" part
                $errorMessage = substr($exception->getMessage(), 0, $calledInPosition);

                // Extract and format the file path from the "called in" part
                $calledInPath = substr($exception->getMessage(), $calledInPosition + 11); // +11 to skip ", called in"

                // Split at "on line" to get the file path and line number
                [$filePath, $lineNumber] = explode(' on line ', trim($calledInPath));
            } else {
                $errorMessage = $exception->getMessage();
                $filePath = $exception->getFile();
                $lineNumber = $exception->getLine();
            }

            // Format the file path in exception message
            $errorMessage = self::formatPath($errorMessage);

            // Only show the message if it's different from the exception message
            if ($message !== $exception->getMessage()) {
                $text .= '📝 *Message:* `'.self::escapeSpecialChars($message)."`\n\n";
            }

            $text .= "🔥 *Exception Occurred \\!*\n";
            $text .= '💥 *Message:* `'.self::escapeSpecialChars($errorMessage)."`\n\n";
            $text .= "📌 *File:* ```\n".self::formatPath($filePath).":{$lineNumber}```\n\n";

            // Handle normal log message
        } else {
            $text .= '📝 *Message:* `'.self::escapeSpecialChars($message)."`\n\n";

            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

            foreach ($trace as $item) {
                if (! isset($item['file'])) {
                    continue;
                }

                $filePath = self::formatPath($item['file']);

                // Exclude Laravel core, vendor files and TelegramLogger
                if (
                    ! str_contains($filePath, 'vendor') &&
                    ! str_contains($filePath, 'Illuminate') &&
                    ! str_contains($filePath, 'TelegramLogger')
                ) {
                    $text .= "📌 *File:* ```\n".$filePath.":".$item['line']."```\n\n";
                    break;
                }
            }

            if (! empty($context)) {
                $text .= "📂 *Context:* ```\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."```\n\n";
            }
        }

        $text .= '⏳ *Time:* '.self::escapeSpecialChars(date('Y-m-d H:i:s')).'';

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage?".http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'disable_notification' => $silentNotification,
        ]);

        file_get_contents($url);
    }

    /*
    |--------------------------------------------------------------------------
    | Private static functions
    |--------------------------------------------------------------------------
    */

    /**
     * Replace backslashes with double backslashes for Windows paths
     */
    private static function formatPath(string $path): string
    {
        return str_replace('\\', '\\\\', $path);
    }

    /**
     * Escape special characters in the text to prevent MarkdownV2 formatting issues
     *
     * @see https://core.telegram.org/bots/api#markdownv2-style
     */
    private static function escapeSpecialChars(string $text): string
    {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!', '?', ':'];

        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\'.$char, $text);
        }

        return $text;
    }
}
