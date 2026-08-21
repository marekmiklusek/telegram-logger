<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class TelegramLoggerServiceProvider extends ServiceProvider
{
    private const CONFIG_NAME = 'telegram-logger';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/'.self::CONFIG_NAME.'.php', self::CONFIG_NAME);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/'.self::CONFIG_NAME.'.php' => config_path(self::CONFIG_NAME.'.php'),
            ], self::CONFIG_NAME.'-config');
        }

        $this->validateLevel();

        Event::listen(MessageLogged::class, static function (MessageLogged $event): void {
            TelegramLogger::send($event->level, $event->message, $event->context);
        });
    }

    private function validateLevel(): void
    {
        $level = config(self::CONFIG_NAME.'.level', 'error');

        if (! is_string($level) || ! array_key_exists(strtolower($level), TelegramLogger::LEVELS)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s.level [%s]. Allowed values: %s.',
                self::CONFIG_NAME,
                is_string($level) ? $level : get_debug_type($level),
                implode(', ', array_keys(TelegramLogger::LEVELS)),
            ));
        }
    }
}
