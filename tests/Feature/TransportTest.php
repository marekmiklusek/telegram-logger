<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\TelegramLogger;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('delivers over the stream wrapper', function (): void {
    FakeTelegram::respondOk();

    Log::error('stream transport');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('reports an unreachable host as a delivery failure', function (): void {
    config()->set('telegram-logger.api_url', 'http://127.0.0.1:1');

    FakeTelegram::disable();

    expect(fn () => Log::error('nowhere to go'))
        ->toThrow(RuntimeException::class, 'no response');
});

it('explains that allow_url_fopen is required when it is disabled', function (): void {
    $guard = new ReflectionMethod(TelegramLogger::class, 'guardTransport');

    expect(fn (): mixed => $guard->invoke(null, false))
        ->toThrow(RuntimeException::class, 'allow_url_fopen is disabled');
});

it('allows delivery when allow_url_fopen is enabled', function (): void {
    $guard = new ReflectionMethod(TelegramLogger::class, 'guardTransport');

    expect(fn (): mixed => $guard->invoke(null, true))->not->toThrow(RuntimeException::class);
});

it('detects the allow_url_fopen setting', function (): void {
    $detect = new ReflectionMethod(TelegramLogger::class, 'urlFopenEnabled');

    expect($detect->invoke(null))->toBe(
        filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)
    );
});
