<?php

namespace MarekMiklusek\TelegramLogger;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;

class TelegramLoggerServiceProvider extends ServiceProvider
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

        // Call the config name anywhere in the package
        // $this->app->singleton('configName', fn() => $configName);

        // Publish config
        $this->publishes([
            __DIR__ . "/../config/{$configName}.php" => config_path("{$configName}.php"),
        ], "{$configName}-config");

        // Merge config, use the package's config file as a fallback when the config file is not published
        $this->mergeConfigFrom(__DIR__ . "/../config/{$configName}.php", $configName);

        // Listen for all logs and filter based on level
        Event::listen(MessageLogged::class, function ($event) {
            dd($event);
            TelegramLogger::send($event->message, $event->level, $event->context);
        });
    }
}
