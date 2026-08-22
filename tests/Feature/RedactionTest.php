<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('redacts a sensitive context value', function (string $key): void {
    FakeTelegram::respondOk();

    Log::error('login failed', [$key => 'super-secret-value']);

    expect(sentText())->toContain('[REDACTED]');

    expect(sentText())->not->toContain('super\-secret\-value');
})->with(['password', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'credit_card', 'cvv']);

it('matches sensitive keys case insensitively', function (): void {
    FakeTelegram::respondOk();

    Log::error('login failed', ['PASSWORD' => 'hunter2']);

    expect(sentText())->toContain('[REDACTED]');
});

it('matches sensitive keys as a substring', function (string $key): void {
    FakeTelegram::respondOk();

    Log::error('login failed', [$key => 'hunter2']);

    expect(sentText())->toContain('[REDACTED]');
})->with(['password_confirmation', 'user_password', 'api_token', 'refresh_token']);

it('redacts nested values', function (): void {
    FakeTelegram::respondOk();

    Log::error('request failed', [
        'user' => ['id' => 42, 'password' => 'hunter2'],
        'headers' => ['authorization' => 'Bearer abc123'],
    ]);

    expect(sentText())
        ->toContain('[REDACTED]')
        ->toContain('42');

    expect(sentText())->not->toContain('hunter2');

    expect(sentText())->not->toContain('abc123');
});

it('keeps values that are not sensitive', function (): void {
    FakeTelegram::respondOk();

    Log::error('order placed', ['user_id' => 42, 'action' => 'checkout']);

    expect(sentText())
        ->toContain('"user_id": 42')
        ->toContain('"action": "checkout"');

    expect(sentText())->not->toContain('[REDACTED]');
});

it('sends the context untouched when redaction is disabled', function (): void {
    config()->set('telegram-logger.redact_keys', []);

    FakeTelegram::respondOk();

    Log::error('login failed', ['password' => 'hunter2']);

    expect(sentText())->toContain('hunter2');
});

it('ignores a malformed redact_keys config', function (): void {
    config()->set('telegram-logger.redact_keys', 'password');

    FakeTelegram::respondOk();

    Log::error('login failed', ['password' => 'hunter2']);

    expect(sentText())->toContain('hunter2');
});

it('skips non-string entries in redact_keys', function (): void {
    config()->set('telegram-logger.redact_keys', ['password', 42, '', null]);

    FakeTelegram::respondOk();

    Log::error('login failed', ['password' => 'hunter2', 'note' => 'visible']);

    expect(sentText())
        ->toContain('[REDACTED]')
        ->toContain('visible');
});

it('stops recursing into deeply nested arrays', function (): void {
    FakeTelegram::respondOk();

    $deep = ['password' => 'leaked'];

    foreach (range(1, 12) as $ignored) {
        $deep = ['nested' => $deep];
    }

    Log::error('deep context', $deep);

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('leaves the exception in the context usable', function (): void {
    FakeTelegram::respondOk();

    Log::error('failed', ['exception' => new RuntimeException('boom')]);

    expect(sentText())->toContain('🔥 *Exception:* `RuntimeException`');
});
