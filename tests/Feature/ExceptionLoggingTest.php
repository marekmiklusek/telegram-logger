<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('renders an exception from the context', function (): void {
    FakeTelegram::respondOk();

    $exception = new RuntimeException('Database connection failed');

    Log::error('Unhandled exception occurred', ['exception' => $exception]);

    expect(sentText())
        ->toContain('💥 `RuntimeException`')
        ->toContain('Database connection failed')
        ->toContain('Unhandled exception occurred')
        ->toContain(':'.$exception->getLine().'`');
});

it('does not repeat the message when it equals the exception message', function (): void {
    FakeTelegram::respondOk();

    Log::error('Same text', ['exception' => new RuntimeException('Same text')]);

    expect(mb_substr_count(sentText(), 'Same text'))->toBe(1);
});

it('points at the call site of a php type error', function (): void {
    FakeTelegram::respondOk();

    Log::error('type problem', [
        'exception' => new TypeError('foo(): Argument #1 must be int, string given, called in /app/Http/Controllers/UserController.php on line 42'),
    ]);

    expect(sentText())
        ->toContain('/app/Http/Controllers/UserController.php')
        ->toContain('UserController.php:42');

    expect(sentText())->not->toContain('called in');
});

it('survives a malformed called-in suffix', function (): void {
    FakeTelegram::respondOk();

    $exception = new RuntimeException('boom, called in nowhere');

    Log::error('malformed', ['exception' => $exception]);

    expect(FakeTelegram::requestCount())->toBe(1)
        ->and(sentText())->toContain(':'.$exception->getLine().'`');
});

it('truncates a very long exception message', function (): void {
    FakeTelegram::respondOk();

    Log::error('long', ['exception' => new RuntimeException(str_repeat('x', 20_000))]);

    expect(mb_strlen(sentText()))->toBeLessThanOrEqual(4096);
});

it('ignores a non-throwable exception key', function (): void {
    FakeTelegram::respondOk();

    Log::error('not really an exception', ['exception' => 'just a string']);

    expect(sentText())
        ->not->toContain('💥 `')
        ->toContain('```json');
});
