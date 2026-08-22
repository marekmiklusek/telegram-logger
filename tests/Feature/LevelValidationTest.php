<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use MarekMiklusek\TelegramLogger\TelegramLoggerServiceProvider;

function bootWithLevel(mixed $level): void
{
    $app = new Application(__DIR__);

    $app->instance('config', new Repository([
        'telegram-logger' => ['level' => $level],
    ]));

    $provider = new TelegramLoggerServiceProvider($app);
    $provider->register();
    $provider->boot();
}

it('accepts a valid level', function (string $level): void {
    bootWithLevel($level);
})->with(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])
    ->throwsNoExceptions();

it('accepts a level in a different case', function (): void {
    bootWithLevel('ERROR');
})->throwsNoExceptions();

it('rejects an unknown level', function (): void {
    bootWithLevel('verbose');
})->throws(InvalidArgumentException::class, 'Invalid telegram-logger.level [verbose]');

it('rejects a non-string level', function (): void {
    bootWithLevel(3);
})->throws(InvalidArgumentException::class, 'must be a string, integer given');

it('rejects a null level', function (): void {
    bootWithLevel(null);
})->throws(InvalidArgumentException::class, 'must be a string, NULL given');
