<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('sends nothing when disabled', function (): void {
    config()->set('telegram-logger.is_enabled', false);

    Log::error('disabled');

    expect(FakeTelegram::requestCount())->toBe(0);
});

it('sends nothing without a bot token', function (): void {
    config()->set('telegram-logger.bot_token', '');

    Log::error('no token');

    expect(FakeTelegram::requestCount())->toBe(0);
});

it('sends nothing without a chat id', function (): void {
    config()->set('telegram-logger.chat_id', '');

    Log::error('no chat');

    expect(FakeTelegram::requestCount())->toBe(0);
});

it('tolerates a legacy config missing the newer keys', function (): void {
    config()->offsetUnset('telegram-logger.throw_on_failure');
    config()->offsetUnset('telegram-logger.silent_notification');

    FakeTelegram::respondOk();

    Log::error('legacy config');

    expect(FakeTelegram::requestCount())->toBe(1)
        ->and(sentParam('disable_notification'))->toBe('0');
});

it('posts to the sendMessage endpoint of the configured bot', function (): void {
    FakeTelegram::respondOk();

    Log::error('routing');

    expect(sentUrl())
        ->toBe('https://api.telegram.org/bottest-token/sendMessage')
        ->and(sentParam('chat_id'))->toBe('12345')
        ->and(sentParam('parse_mode'))->toBe('MarkdownV2');
});

it('marks the message silent when configured', function (): void {
    config()->set('telegram-logger.silent_notification', true);

    FakeTelegram::respondOk();

    Log::error('quiet please');

    expect(sentParam('disable_notification'))->toBe('1');
});

it('includes the application name and environment', function (): void {
    FakeTelegram::respondOk();

    Log::error('context');

    expect(sentText())
        ->toContain('Testing App')
        ->toContain('testing');
});

it('tolerates a missing application name', function (): void {
    config()->offsetUnset('app.name');

    FakeTelegram::respondOk();

    Log::error('nameless');

    expect(FakeTelegram::requestCount())->toBe(1);
});
