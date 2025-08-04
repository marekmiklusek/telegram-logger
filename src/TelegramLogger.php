<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use Throwable;
use Illuminate\Support\Facades\Log;

final class TelegramLogger
{
    /**
     * Log levels.
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
     * Log level emojis.
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
     * Send log message or exception to Telegram.
     * 
     * @param array<string, mixed> $context
     */
    public static function send(string $level, string $message, array $context = []): void
    {
        $isEnabled = config('telegram-logger.is_enabled');
        $configuredLevel = config('telegram-logger.level', 'error');

        if (! $isEnabled) {
            return;
        }

        if (! isset(self::$logLevels[$level]) || ! isset(self::$logLevels[$configuredLevel])) {
            return;
        }
        if (self::$logLevels[$level] > self::$logLevels[$configuredLevel]) {
            return;
        }

        $text = '🛠️ *Application:* '.self::escapeSpecialChars(config('app.name'))."\n";
        $text .= '🌍 *Environment:* '.self::escapeSpecialChars(config('app.env'))."\n\n";

        $levelIcon = self::$levelEmoji[$level] ?? '📛';
        $text .= "{$levelIcon} *Level:* ".strtoupper($level)."\n";

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

            $errorMessage = self::formatPath($errorMessage);

            if ($message !== $exception->getMessage()) {
                $text .= '📝 *Message:* `'.self::escapeSpecialChars($message)."`\n\n";
            }

            $text .= "🔥 *Exception Occurred \\!*\n";
            $text .= '💥 *Message:* `'.self::escapeSpecialChars($errorMessage)."`\n\n";
            $text .= "📌 *File:* ```\n".self::escapeCodeBlock(self::formatPath($filePath).":{$lineNumber}")."```\n\n";

        } else {
            $text .= '📝 *Message:* `'.self::escapeSpecialChars($message)."`\n\n";

            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

            foreach ($trace as $item) {
                if (! isset($item['file'])) {
                    continue;
                }

                $filePath = self::formatPath($item['file']);

                if (
                    ! str_contains($filePath, 'vendor') &&
                    ! str_contains($filePath, 'Illuminate') &&
                    ! str_contains($filePath, 'TelegramLogger')
                ) {
                    $text .= "📌 *File:* ```\n".self::escapeCodeBlock($filePath.":".$item['line'])."```\n\n";
                    break;
                }
            }

            if (! empty($context)) {
                $text .= "📂 *Context:* ```\n".self::escapeCodeBlock(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))."```\n\n";
            }
        }

        $text .= '⏳ *Time:* '.self::escapeSpecialChars(date('Y-m-d H:i:s')).'';

        $maxLength = 4096;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 50)."\n\n... (message truncated due to length)";
        }

        self::sendTelegramMessage($text);
    }

    /*
    |--------------------------------------------------------------------------
    | Private functions
    |--------------------------------------------------------------------------
    */

    /**
     * Send message to Telegram with proper error handling.
     */
    private static function sendTelegramMessage(string $text): void
    {
        $result = self::executeTelegramRequest($text);
        $response = $result['response'];
        $httpCode = $result['httpCode'];
        $error = $result['error'];

        if ($response === false || filled($error)) {
            Log::error('TelegramLogger cURL Error: '.$error);
        } elseif ($httpCode !== 200) {
            $responseData = json_decode($response, true);
            $errorDescription = $responseData['description'] ?? 'Unknown error';
            Log::error("TelegramLogger HTTP Error {$httpCode}: {$errorDescription}");

            if ($httpCode === 400 && str_contains($errorDescription, 'too long')) {
                $shortText = "🔥 *Error Log Too Long*\n\n";
                $shortText .= '🛠️ *Application:* '.self::escapeSpecialChars(config('app.name'))."\n";
                $shortText .= '🌍 *Environment:* '.self::escapeSpecialChars(config('app.env'))."\n";
                $shortText .= '⏳ *Time:* '.self::escapeSpecialChars(date('Y-m-d H:i:s'))."\n\n";
                $shortText .= 'The original log message was too long for Telegram. Check your application logs for details.';

                self::retrySendMessage($shortText);
            }
        }
    }

    /**
     * Retry sending a shorter message.
     */
    private static function retrySendMessage(string $text): void
    {
        $result = self::executeTelegramRequest($text);
        $response = $result['response'];
        $httpCode = $result['httpCode'];
        $error = $result['error'];

        if ($response === false || filled($error) || $httpCode !== 200) {
            Log::error('TelegramLogger: Failed to send even shortened message');
        }
    }

    /**
     * Execute cURL request to Telegram API.
     */
    private static function executeTelegramRequest(string $text): array
    {
        $data = [
            'chat_id' => config()->string('telegram-logger.chat_id'),
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'disable_notification' => config()->boolean('telegram-logger.silent_notification'),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.telegram.org/bot'.config()->string('telegram-logger.bot_token').'/sendMessage',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'response' => $response,
            'httpCode' => $httpCode,
            'error' => $error,
        ];
    }

    /**
     * Replace backslashes with double backslashes for Windows paths.
     */
    private static function formatPath(string $path): string
    {
        return str_replace('\\', '\\\\', $path);
    }

    /**
     * Escape special characters for content inside code blocks (```).
     * In MarkdownV2, only backticks and backslashes need to be escaped inside pre-formatted code.
     */
    private static function escapeCodeBlock(string $text): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\\`'], $text);
    }

    /**
     * Escape special characters in the text to prevent MarkdownV2 formatting issues.
     *
     * @see https://core.telegram.org/bots/api#markdownv2-style
     */
    private static function escapeSpecialChars(string $text): string
    {
        $specialCharacters = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!', '?', ':'];

        foreach ($specialCharacters as $character) {
            $text = str_replace($character, '\\'.$character, $text);
        }

        return $text;
    }
}
