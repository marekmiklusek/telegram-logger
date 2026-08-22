<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger\Tests;

final class FakeTelegram
{
    /** @var resource|null */
    public $context;

    /**
     * @var list<array{url: string, params: array<string, string>}>
     */
    private static array $requests = [];

    /**
     * @var list<array<string, mixed>|null>
     */
    private static array $responses = [];

    private static bool $enabled = false;

    /** @var (callable(): void)|null */
    private static $onRequest;

    private string $body = '';

    private int $offset = 0;

    public static function reset(): void
    {
        self::$requests = [];
        self::$responses = [];
        self::$onRequest = null;

        self::enable();
    }

    public static function enable(): void
    {
        if (self::$enabled) {
            return;
        }

        stream_wrapper_unregister('https');
        stream_wrapper_register('https', self::class);

        self::$enabled = true;
    }

    public static function disable(): void
    {
        if (! self::$enabled) {
            return;
        }

        stream_wrapper_restore('https');

        self::$enabled = false;
    }

    /**
     * @param  callable(): void  $callback
     */
    public static function onRequest(callable $callback): void
    {
        self::$onRequest = $callback;
    }

    public static function respondOk(): void
    {
        self::$responses[] = ['ok' => true, 'result' => ['message_id' => 1]];
    }

    public static function respondError(int $code, string $description): void
    {
        self::$responses[] = ['ok' => false, 'error_code' => $code, 'description' => $description];
    }

    public static function respondConnectionFailure(): void
    {
        self::$responses[] = null;
    }

    public static function respondRaw(string $body): void
    {
        self::$responses[] = ['__raw' => $body];
    }

    /**
     * @return list<array{url: string, params: array<string, string>}>
     */
    public static function requests(): array
    {
        return self::$requests;
    }

    public static function requestCount(): int
    {
        return count(self::$requests);
    }

    /**
     * @return array{url: string, params: array<string, string>}|null
     */
    public static function request(int $index): ?array
    {
        return self::$requests[$index] ?? null;
    }

    public function stream_open(string $path): bool
    {
        self::$requests[] = ['url' => $path, 'params' => $this->capturedParams()];

        if (self::$onRequest !== null) {
            (self::$onRequest)();
        }

        $response = array_shift(self::$responses);

        if ($response === null) {
            return false;
        }

        $raw = $response['__raw'] ?? null;

        $this->body = is_string($raw) ? $raw : json_encode($response, JSON_THROW_ON_ERROR);

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = mb_substr($this->body, $this->offset, $count);
        $this->offset += mb_strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->offset >= mb_strlen($this->body);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    private function capturedParams(): array
    {
        if (! is_resource($this->context)) {
            return [];
        }

        $http = stream_context_get_options($this->context)['http'] ?? null;

        if (! is_array($http) || ! isset($http['content']) || ! is_string($http['content'])) {
            return [];
        }

        parse_str($http['content'], $parsed);

        $params = [];

        foreach ($parsed as $key => $value) {
            if (is_string($value)) {
                $params[(string) $key] = $value;
            }
        }

        return $params;
    }
}
