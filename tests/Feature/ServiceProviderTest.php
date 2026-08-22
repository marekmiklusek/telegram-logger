<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use MarekMiklusek\TelegramLogger\TelegramLogger;
use MarekMiklusek\TelegramLogger\TelegramLoggerServiceProvider;

it('merges the package configuration', function (): void {
    expect(config('telegram-logger'))
        ->toHaveKeys(['bot_token', 'chat_id', 'level', 'silent_notification', 'is_enabled', 'throw_on_failure']);
});

it('registers the config file for publishing', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        TelegramLoggerServiceProvider::class,
        'telegram-logger-config',
    );

    expect($paths)->not->toBeEmpty()
        ->and(array_key_first($paths))->toEndWith('telegram-logger.php');
});

it('listens for log events', function (): void {
    expect(Event::hasListeners(MessageLogged::class))->toBeTrue();
});

it('exposes the psr-3 levels ordered by severity', function (): void {
    expect(TelegramLogger::LEVELS)
        ->toBe([
            'emergency' => 0,
            'alert' => 1,
            'critical' => 2,
            'error' => 3,
            'warning' => 4,
            'notice' => 5,
            'info' => 6,
            'debug' => 7,
        ]);
});
