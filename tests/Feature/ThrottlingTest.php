<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('sends an identical message only once within the dedupe window', function (): void {
    FakeTelegram::respondOk();
    FakeTelegram::respondOk();

    Log::error('the same failure');
    Log::error('the same failure');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('treats a different message as a new one', function (): void {
    FakeTelegram::respondOk();
    FakeTelegram::respondOk();

    Log::error('first failure');
    Log::error('second failure');

    expect(FakeTelegram::requestCount())->toBe(2);
});

it('treats the same message at another level as a new one', function (): void {
    config()->set('telegram-logger.level', 'debug');

    FakeTelegram::respondOk();
    FakeTelegram::respondOk();

    Log::error('same text');
    Log::warning('same text');

    expect(FakeTelegram::requestCount())->toBe(2);
});

it('sends duplicates again once deduplication is disabled', function (): void {
    config()->set('telegram-logger.dedupe_seconds', 0);

    FakeTelegram::respondOk();
    FakeTelegram::respondOk();

    Log::error('repeated');
    Log::error('repeated');

    expect(FakeTelegram::requestCount())->toBe(2);
});

it('stops sending once the per minute limit is reached', function (): void {
    config()->set('telegram-logger.dedupe_seconds', 0);
    config()->set('telegram-logger.max_per_minute', 3);

    foreach (range(1, 10) as $attempt) {
        FakeTelegram::respondOk();
        Log::error('storm '.$attempt);
    }

    expect(FakeTelegram::requestCount())->toBe(3);
});

it('keeps sending when throttling is disabled', function (): void {
    config()->set('telegram-logger.dedupe_seconds', 0);
    config()->set('telegram-logger.max_per_minute', 0);

    foreach (range(1, 6) as $attempt) {
        FakeTelegram::respondOk();
        Log::error('storm '.$attempt);
    }

    expect(FakeTelegram::requestCount())->toBe(6);
});

it('counts only delivered messages against the limit', function (): void {
    config()->set('telegram-logger.dedupe_seconds', 60);
    config()->set('telegram-logger.max_per_minute', 2);

    FakeTelegram::respondOk();
    FakeTelegram::respondOk();

    Log::error('repeated message');
    Log::error('repeated message');
    Log::error('repeated message');
    Log::error('a different message');

    expect(FakeTelegram::requestCount())->toBe(2);
});
