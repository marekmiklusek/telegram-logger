<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use RuntimeException;
use Throwable;

final class TelegramLogger
{
    /**
     * @var array<string, int>
     */
    public const LEVELS = [
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
     * @var array<string, string>
     */
    private const EMOJI = [
        'emergency' => '🆘',
        'alert' => '🚨',
        'critical' => '🚑',
        'error' => '❌',
        'warning' => '⚠️',
        'notice' => '🔔',
        'info' => 'ℹ️',
        'debug' => '🔍',
    ];

    private const MAX_TEXT_LENGTH = 4096;

    private const MAX_MESSAGE_LENGTH = 1500;

    private const ELLIPSIS = '…';

    private const API_URL = 'https://api.telegram.org/bot%s/sendMessage';

    private const JSON_FLAGS = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private static bool $sending = false;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function send(string $level, string $message, array $context = []): void
    {
        if (self::$sending) {
            return;
        }

        self::$sending = true;

        try {
            self::handle(strtolower($level), $message, $context);
        } catch (Throwable $throwable) {
            if (config()->boolean('telegram-logger.throw_on_failure', false)) {
                throw $throwable;
            }
        } finally {
            self::$sending = false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function handle(string $level, string $message, array $context): void
    {
        $botToken = config()->string('telegram-logger.bot_token', '');
        $chatId = config()->string('telegram-logger.chat_id', '');

        if (! config()->boolean('telegram-logger.is_enabled', true) || blank($botToken) || blank($chatId)) {
            return;
        }

        if (! self::shouldLog($level)) {
            return;
        }

        self::deliver($botToken, $chatId, self::buildText($level, $message, $context));
    }

    private static function shouldLog(string $level): bool
    {
        if (! isset(self::LEVELS[$level])) {
            return false;
        }

        $configured = strtolower(config()->string('telegram-logger.level', 'error'));

        if (! isset(self::LEVELS[$configured])) {
            $configured = 'error';
        }

        return self::LEVELS[$level] <= self::LEVELS[$configured];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function buildText(string $level, string $message, array $context): string
    {
        $text = '🛠️ *Application:* '.self::escapeText(config()->string('app.name', ''))."\n";
        $text .= '🌍 *Environment:* '.self::escapeText(config()->string('app.env', ''))."\n\n";
        $text .= self::EMOJI[$level].' *Level:* '.strtoupper($level)."\n";

        $footer = '⏳ *Time:* '.self::escapeText(date('Y-m-d H:i:s'));

        $exception = $context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            $text .= self::formatException($message, $exception);
        } else {
            $text .= '📝 *Message:* `'.self::fitCode($message, self::MAX_MESSAGE_LENGTH)."`\n\n";
            $text .= self::formatCaller();
            $text .= self::formatContext($context, self::MAX_TEXT_LENGTH - mb_strlen($text) - mb_strlen($footer));
        }

        return $text.$footer;
    }

    private static function formatException(string $message, Throwable $exception): string
    {
        $errorMessage = $exception->getMessage();
        $file = $exception->getFile();
        $line = (string) $exception->getLine();

        if (preg_match('/^(.*), called in (.+) on line (\d+)$/s', $errorMessage, $matches) === 1) {
            [, $errorMessage, $file, $line] = $matches;
        }

        $text = '';

        if ($message !== $exception->getMessage()) {
            $text .= '📝 *Message:* `'.self::fitCode($message, self::MAX_MESSAGE_LENGTH)."`\n\n";
        }

        $text .= '🔥 *Exception:* `'.self::escapeCode($exception::class)."`\n";
        $text .= '💥 *Message:* `'.self::fitCode($errorMessage, self::MAX_MESSAGE_LENGTH)."`\n\n";
        $text .= "📌 *File:* ```\n".self::escapeCode($file)."```\n";
        $text .= '🎯 *Line:* `'.self::escapeCode($line)."`\n\n";

        return $text;
    }

    private static function formatCaller(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            if (
                str_contains($frame['file'], 'vendor')
                || str_contains($frame['file'], 'Illuminate')
                || str_contains($frame['file'], 'TelegramLogger')
            ) {
                continue;
            }

            return "📌 *File:* ```\n".self::escapeCode($frame['file'])."```\n"
                .'🎯 *Line:* `'.($frame['line'] ?? 0)."`\n\n";
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function formatContext(array $context, int $budget): string
    {
        if ($context === []) {
            return '';
        }

        $prefix = "📂 *Context:* ```\n";
        $suffix = "```\n\n";

        $budget -= mb_strlen($prefix) + mb_strlen($suffix);

        if ($budget < 10) {
            return '';
        }

        $json = json_encode($context, self::JSON_FLAGS);

        if ($json === false) {
            return '';
        }

        return $prefix.self::fitCode($json, $budget).$suffix;
    }

    private static function deliver(string $botToken, string $chatId, string $text): void
    {
        $silent = config()->boolean('telegram-logger.silent_notification', false);

        $response = self::request($botToken, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'disable_notification' => $silent,
        ]);

        if ($response['ok'] ?? false) {
            return;
        }

        if (($response['error_code'] ?? null) === 400) {
            $fallback = self::request($botToken, [
                'chat_id' => $chatId,
                'text' => mb_substr(self::stripMarkdown($text), 0, self::MAX_TEXT_LENGTH),
                'disable_notification' => $silent,
            ]);

            if ($fallback['ok'] ?? false) {
                return;
            }

            $response = $fallback ?? $response;
        }

        throw new RuntimeException('Telegram API request failed: '.($response['description'] ?? 'no response'));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private static function request(string $botToken, array $params): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents(sprintf(self::API_URL, $botToken), false, $context);

        if ($body === false) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @see https://core.telegram.org/bots/api#markdownv2-style
     */
    private static function escapeText(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text) ?? $text;
    }

    private static function escapeCode(string $text): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\\`'], $text);
    }

    private static function fitCode(string $raw, int $budget): string
    {
        if ($budget < 1) {
            return '';
        }

        $escaped = self::escapeCode($raw);

        while (mb_strlen($escaped) > $budget) {
            $excess = mb_strlen($escaped) - $budget;
            $raw = mb_substr($raw, 0, max(0, mb_strlen($raw) - $excess - 1));
            $escaped = self::escapeCode($raw).self::ELLIPSIS;
        }

        return $escaped;
    }

    private static function stripMarkdown(string $text): string
    {
        return preg_replace('/\\\\([_*\[\]()~`>#+\-=|{}.!\\\\])/', '$1', $text) ?? $text;
    }
}
