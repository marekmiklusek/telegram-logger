<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use Throwable;
use RuntimeException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class TelegramLogger
{
    /**
     * @var array<string, int>
     */
    public const array LEVELS = [
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
    private const array EMOJI = [
        'emergency' => '🆘',
        'alert' => '🚨',
        'critical' => '🚑',
        'error' => '❌',
        'warning' => '⚠️',
        'notice' => '🔔',
        'info' => 'ℹ️',
        'debug' => '🔍',
    ];

    private const int MAX_TEXT_LENGTH = 4096;

    private const int MAX_MESSAGE_LENGTH = 1500;

    private const int MAX_HEADER_LENGTH = 200;

    private const string ELLIPSIS = '…';

    private const string REDACTED = '[REDACTED]';

    private const int MAX_REDACT_DEPTH = 8;

    private const string API_URL = 'https://api.telegram.org/bot%s/sendMessage';

    private const int JSON_FLAGS = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
        | JSON_THROW_ON_ERROR;

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
            self::handle(mb_strtolower($level), $message, $context);
        } catch (Throwable $throwable) {
            if (self::configBool('telegram-logger.throw_on_failure', false)) {
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
        $botToken = self::configString('telegram-logger.bot_token');
        $chatId = self::configString('telegram-logger.chat_id');

        if (! self::configBool('telegram-logger.is_enabled', true) || blank($botToken) || blank($chatId)) {
            return;
        }

        if (! self::shouldLog($level)) {
            return;
        }

        if (self::isDuplicate($level, $message) || self::isThrottled()) {
            return;
        }

        self::deliver($botToken, $chatId, self::buildText($level, $message, self::redact($context)));
    }

    private static function isDuplicate(string $level, string $message): bool
    {
        $seconds = self::configInt('telegram-logger.dedupe_seconds', 60);

        if ($seconds < 1) {
            return false;
        }

        $key = 'telegram-logger:seen:'.md5($level.'|'.$message);

        return ! self::cacheAdd($key, $seconds);
    }

    private static function isThrottled(): bool
    {
        $limit = self::configInt('telegram-logger.max_per_minute', 20);

        if ($limit < 1) {
            return false;
        }

        $cache = self::cache();

        if (! $cache instanceof CacheRepository) {
            return false;
        }

        $key = 'telegram-logger:sent:'.floor(time() / 60);

        $sent = $cache->get($key);
        $sent = is_int($sent) ? $sent : 0;

        if ($sent >= $limit) {
            return true;
        }

        $cache->put($key, $sent + 1, 120);

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function redact(array $context): array
    {
        $keys = self::redactKeys();

        if ($keys === []) {
            return $context;
        }

        $redacted = [];

        foreach ($context as $key => $value) {
            $redacted[$key] = self::isSensitiveKey($key, $keys)
                ? self::REDACTED
                : (is_array($value) ? self::redactArray($value, $keys, 1) : $value);
        }

        return $redacted;
    }

    /**
     * @param  array<mixed, mixed>  $values
     * @param  list<string>  $keys
     * @return array<mixed, mixed>
     */
    private static function redactArray(array $values, array $keys, int $depth): array
    {
        if ($depth > self::MAX_REDACT_DEPTH) {
            return $values;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key, $keys)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = is_array($value)
                ? self::redactArray($value, $keys, $depth + 1)
                : $value;
        }

        return $redacted;
    }

    /**
     * @param  list<string>  $keys
     */
    private static function isSensitiveKey(string $key, array $keys): bool
    {
        $needle = mb_strtolower($key);

        return array_any($keys, fn (string $sensitive): bool => str_contains($needle, $sensitive));
    }

    /**
     * @return list<string>
     */
    private static function redactKeys(): array
    {
        $keys = [];

        foreach (self::configArray('telegram-logger.redact_keys') as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = mb_strtolower($key);
            }
        }

        return $keys;
    }

    private static function shouldLog(string $level): bool
    {
        if (! isset(self::LEVELS[$level])) {
            return false;
        }

        $configured = mb_strtolower(self::configString('telegram-logger.level', 'error'));

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
        $application = mb_substr(self::configString('app.name'), 0, self::MAX_HEADER_LENGTH);
        $environment = mb_substr(self::configString('app.env'), 0, self::MAX_HEADER_LENGTH);

        $text = '🛠️ *Application:* '.self::escapeText($application)."\n";
        $text .= '🌍 *Environment:* '.self::escapeText($environment)."\n\n";
        $text .= (self::EMOJI[$level] ?? '📛').' *Level:* '.mb_strtoupper($level)."\n";

        $footer = '⏳ *Time:* '.self::escapeText(date('Y-m-d H:i:s'));

        $exception = $context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            $text .= self::formatException($message, $exception);
        } else {
            $text .= '📝 *Message:* `'.self::fitCode($message, self::MAX_MESSAGE_LENGTH)."`\n\n";
            $text .= self::formatCaller();
            $text .= self::formatContext($context, self::MAX_TEXT_LENGTH - mb_strlen($text) - mb_strlen($footer));
        }

        return self::fitWithin($text, $footer);
    }

    private static function fitWithin(string $text, string $footer): string
    {
        $budget = self::MAX_TEXT_LENGTH - mb_strlen($footer);

        if (mb_strlen($text) > $budget) {
            $text = mb_substr($text, 0, max(0, $budget - 1)).self::ELLIPSIS;
        }

        return $text.$footer;
    }

    private static function formatException(string $message, Throwable $throwable): string
    {
        $errorMessage = $throwable->getMessage();
        $file = $throwable->getFile();
        $line = (string) $throwable->getLine();

        if (preg_match('/^(.*), called in (.+) on line (\d+)$/s', $errorMessage, $matches) === 1) {
            [, $errorMessage, $file, $line] = $matches;
        }

        $text = '';

        if ($message !== $throwable->getMessage()) {
            $text .= '📝 *Message:* `'.self::fitCode($message, self::MAX_MESSAGE_LENGTH)."`\n\n";
        }

        $text .= '🔥 *Exception:* `'.self::escapeCode($throwable::class)."`\n";
        $text .= '💥 *Message:* `'.self::fitCode($errorMessage, self::MAX_MESSAGE_LENGTH)."`\n\n";
        $text .= "📌 *File:* ```\n".self::escapeCode($file)."```\n";

        return $text.('🎯 *Line:* `'.self::escapeCode($line)."`\n\n");
    }

    private static function formatCaller(): string
    {
        return self::formatFrames(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25));
    }

    /**
     * @param  list<array{file?: string, line?: int}>  $frames
     */
    private static function formatFrames(array $frames): string
    {
        foreach ($frames as $frame) {
            if (! isset($frame['file']) || self::isInternalFrame($frame['file'])) {
                continue;
            }

            return "📌 *File:* ```\n".self::escapeCode($frame['file'])."```\n"
                .'🎯 *Line:* `'.($frame['line'] ?? 0)."`\n\n";
        }

        return '';
    }

    private static function isInternalFrame(string $file): bool
    {
        return str_contains($file, 'vendor')
            || str_contains($file, 'Illuminate')
            || str_contains($file, 'TelegramLogger');
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

        return $prefix.self::fitCode(json_encode($context, self::JSON_FLAGS), $budget).$suffix;
    }

    private static function deliver(string $botToken, string $chatId, string $text): void
    {
        $silent = self::configBool('telegram-logger.silent_notification', false);

        $response = self::request($botToken, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'disable_notification' => $silent,
        ]);

        if ($response !== null && $response['ok']) {
            return;
        }

        if ($response !== null && $response['error_code'] === 400) {
            $fallback = self::request($botToken, [
                'chat_id' => $chatId,
                'text' => mb_substr(self::stripMarkdown($text), 0, self::MAX_TEXT_LENGTH),
                'disable_notification' => $silent,
            ]);

            if ($fallback !== null && $fallback['ok']) {
                return;
            }

            $response = $fallback ?? $response;
        }

        throw new RuntimeException('Telegram API request failed: '.($response['description'] ?? 'no response'));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, error_code: int|null, description: string|null}|null
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

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'ok' => (bool) ($decoded['ok'] ?? false),
            'error_code' => is_int($decoded['error_code'] ?? null) ? $decoded['error_code'] : null,
            'description' => is_string($decoded['description'] ?? null) ? $decoded['description'] : null,
        ];
    }

    private static function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    /**
     * @return array<mixed, mixed>
     */
    private static function configArray(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? $value : [];
    }

    private static function cacheAdd(string $key, int $seconds): bool
    {
        $cache = self::cache();

        if (! $cache instanceof CacheRepository) {
            return true;
        }

        return $cache->add($key, true, $seconds);
    }

    private static function cache(): ?CacheRepository
    {
        try {
            $cache = resolve('cache.store');

            return $cache instanceof CacheRepository ? $cache : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function configString(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    private static function configBool(string $key, bool $default): bool
    {
        $value = config($key, $default);

        return is_bool($value) ? $value : $default;
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
