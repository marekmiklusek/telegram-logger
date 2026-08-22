<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\TelegramLogger;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('sends exactly one request on success', function (): void {
    FakeTelegram::respondOk();

    Log::error('once');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('retries as plain text when telegram rejects the formatting', function (): void {
    FakeTelegram::respondError(400, "Bad Request: can't parse entities");
    FakeTelegram::respondOk();

    Log::error('unparseable');

    expect(FakeTelegram::requestCount())->toBe(2)
        ->and(hasSentParam('parse_mode', 1))->toBeFalse()
        ->and(sentText(1))->not->toContain('\\*Level:\\*')
        ->and(sentText(1))->toContain('*Level:*');
});

it('keeps the silent flag on the plain text retry', function (): void {
    config()->set('telegram-logger.silent_notification', true);

    FakeTelegram::respondError(400, 'Bad Request');
    FakeTelegram::respondOk();

    Log::error('quiet retry');

    expect(sentParam('disable_notification', 1))->toBe('1');
});

it('does not retry on errors other than 400', function (): void {
    FakeTelegram::respondError(429, 'Too Many Requests: retry after 5');

    expect(fn () => Log::error('rate limited'))
        ->toThrow(RuntimeException::class, 'Too Many Requests');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('reports a connection failure', function (): void {
    FakeTelegram::respondConnectionFailure();

    expect(fn () => Log::error('offline'))
        ->toThrow(RuntimeException::class, 'no response');
});

it('reports the failure of a plain text retry', function (): void {
    FakeTelegram::respondError(400, 'Bad Request');
    FakeTelegram::respondError(400, 'Bad Request: chat not found');

    expect(fn () => Log::error('doomed'))
        ->toThrow(RuntimeException::class, 'chat not found');
});

it('swallows failures when throw_on_failure is disabled', function (): void {
    config()->set('telegram-logger.throw_on_failure', false);

    FakeTelegram::respondError(500, 'Internal Server Error');

    Log::error('silent failure');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('treats a non-json response as a failure', function (): void {
    FakeTelegram::respondRaw('<html><body>502 Bad Gateway</body></html>');

    expect(fn () => Log::error('proxy error'))
        ->toThrow(RuntimeException::class, 'no response');
});

it('swallows a malformed api response', function (): void {
    config()->set('telegram-logger.throw_on_failure', false);

    FakeTelegram::respondConnectionFailure();

    Log::error('garbage');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('does not recurse when logging happens during delivery', function (): void {
    config()->set('telegram-logger.throw_on_failure', false);

    FakeTelegram::respondOk();

    TelegramLogger::send('error', 'outer', ['inner' => fn () => Log::error('nested')]);

    expect(FakeTelegram::requestCount())->toBeLessThanOrEqual(1);
});

it('ignores an unknown log level', function (): void {
    TelegramLogger::send('verbose', 'unknown level');

    expect(FakeTelegram::requestCount())->toBe(0);
});
