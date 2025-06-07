<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;

final class TelegramLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $configName = 'telegram-logger';

        // Publish config
        $this->publishes([
            __DIR__."/../config/{$configName}.php" => config_path("{$configName}.php"),
        ], "{$configName}-config");

        // Merge config, use the package's config file as a fallback when the config file is not published
        $this->mergeConfigFrom(__DIR__."/../config/{$configName}.php", $configName);

        // Listen for log events
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            TelegramLogger::send($event->level, $event->message, $event->context);
        });
    }
}
