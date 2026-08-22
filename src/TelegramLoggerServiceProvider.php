<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use Override;
use InvalidArgumentException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;

final class TelegramLoggerServiceProvider extends ServiceProvider
{
    private const string CONFIG_NAME = 'telegram-logger';

    #[Override]
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

        Event::listen(MessageLogged::class, static function (MessageLogged $messageLogged): void {
            /** @var array<string, mixed> $context */
            $context = $messageLogged->context;

            TelegramLogger::send($messageLogged->level, $messageLogged->message, $context);
        });
    }

    private function validateLevel(): void
    {
        $level = config()->string(self::CONFIG_NAME.'.level', 'error');

        if (! array_key_exists(mb_strtolower($level), TelegramLogger::LEVELS)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s.level [%s]. Allowed values: %s.',
                self::CONFIG_NAME,
                $level,
                implode(', ', array_keys(TelegramLogger::LEVELS)),
            ));
        }
    }
}
