<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('still delivers when the cache store cannot be resolved', function (): void {
    app()->forgetInstance('cache.store');
    app()->bind('cache.store', function (): never {
        throw new RuntimeException('cache is unavailable');
    });

    FakeTelegram::respondOk();

    Log::error('cache is down but the log still matters');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('still delivers when the cache binding is not a repository', function (): void {
    app()->forgetInstance('cache.store');
    app()->bind('cache.store', fn (): string => 'not a cache');

    FakeTelegram::respondOk();

    Log::error('broken cache binding');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('does not throttle when the cache is unavailable', function (): void {
    app()->forgetInstance('cache.store');
    app()->bind('cache.store', function (): never {
        throw new RuntimeException('cache is unavailable');
    });

    config()->set('telegram-logger.max_per_minute', 1);

    foreach (range(1, 3) as $attempt) {
        FakeTelegram::respondOk();
        Log::error('storm '.$attempt);
    }

    expect(FakeTelegram::requestCount())->toBe(3);
});
