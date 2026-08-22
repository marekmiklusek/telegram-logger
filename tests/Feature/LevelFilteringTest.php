<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('sends a log at the configured level', function (): void {
    FakeTelegram::respondOk();

    Log::error('boom');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('sends logs more severe than the configured level', function (string $level): void {
    FakeTelegram::respondOk();

    Log::{$level}('boom');

    expect(FakeTelegram::requestCount())->toBe(1);
})->with(['emergency', 'alert', 'critical']);

it('ignores logs less severe than the configured level', function (string $level): void {
    Log::{$level}('quiet');

    expect(FakeTelegram::requestCount())->toBe(0);
})->with(['warning', 'notice', 'info', 'debug']);

it('sends every level when configured to debug', function (string $level): void {
    config()->set('telegram-logger.level', 'debug');

    FakeTelegram::respondOk();

    Log::{$level}('everything');

    expect(FakeTelegram::requestCount())->toBe(1);
})->with(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);

it('accepts an uppercase configured level', function (): void {
    config()->set('telegram-logger.level', 'WARNING');

    FakeTelegram::respondOk();

    Log::warning('shouty config');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('falls back to error when the configured level is unknown', function (): void {
    config()->set('telegram-logger.level', 'verbose');

    FakeTelegram::respondOk();

    Log::warning('below error');
    expect(FakeTelegram::requestCount())->toBe(0);

    Log::error('at error');
    expect(FakeTelegram::requestCount())->toBe(1);
});
