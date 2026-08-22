<?php

declare(strict_types=1);

namespace MarekMiklusek\TelegramLogger\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MarekMiklusek\TelegramLogger\TelegramLoggerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeTelegram::reset();
    }

    protected function tearDown(): void
    {
        FakeTelegram::disable();

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TelegramLoggerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Testing App');
        $app['config']->set('app.env', 'testing');
        $app['config']->set('telegram-logger.bot_token', 'test-token');
        $app['config']->set('telegram-logger.chat_id', '12345');
        $app['config']->set('telegram-logger.level', 'error');
        $app['config']->set('telegram-logger.silent_notification', false);
        $app['config']->set('telegram-logger.is_enabled', true);
        $app['config']->set('telegram-logger.throw_on_failure', true);
    }
}
