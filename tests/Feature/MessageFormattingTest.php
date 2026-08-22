<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('renders the level with its emoji', function (string $level, string $emoji): void {
    config()->set('telegram-logger.level', 'debug');

    FakeTelegram::respondOk();

    Log::{$level}('levelled');

    expect(sentText())->toContain($emoji.' *'.mb_strtoupper($level).' \| Testing App \| testing*');
})->with([
    ['emergency', '🆘'],
    ['alert', '🚨'],
    ['critical', '🚑'],
    ['error', '❌'],
    ['warning', '⚠️'],
    ['notice', '🔔'],
    ['info', 'ℹ️'],
    ['debug', '🔍'],
]);

it('escapes MarkdownV2 reserved characters outside code entities', function (): void {
    config()->set('app.name', 'My App (prod) - v1.0!');

    FakeTelegram::respondOk();

    Log::error('escaping');

    expect(sentText())->toContain('My App \(prod\) \- v1\.0\!');
});

it('escapes backslashes inside code entities', function (): void {
    FakeTelegram::respondOk();

    Log::error('Call to undefined method App\Models\User::foo()');

    expect(sentText())->toContain('App\\\\Models\\\\User');
});

it('escapes backticks inside code entities', function (): void {
    FakeTelegram::respondOk();

    Log::error('a `backtick` message');

    expect(sentText())->toContain('a \`backtick\` message');
});

it('keeps unicode readable in the context payload', function (): void {
    FakeTelegram::respondOk();

    Log::error('unicode', ['message' => 'Žiadny záznam']);

    expect(sentText())->toContain('Žiadny záznam');

    expect(sentText())->not->toContain('\u017d');
});

it('sanitises invalid utf-8 in the message', function (): void {
    FakeTelegram::respondOk();

    Log::error("binary \xB1\x31\xFF payload");

    expect(mb_check_encoding(sentText(), 'UTF-8'))->toBeTrue()
        ->and(sentText())->not->toContain("\xB1");
});

it('sanitises invalid utf-8 in an exception message', function (): void {
    FakeTelegram::respondOk();

    Log::error('broken', ['exception' => new RuntimeException("bad \xFE\xFF bytes")]);

    expect(mb_check_encoding(sentText(), 'UTF-8'))->toBeTrue()
        ->and(sentText())->not->toContain("\xFE");
});

it('sanitises invalid utf-8 in the application name', function (): void {
    config()->set('app.name', "App \xC3\x28 name");

    FakeTelegram::respondOk();

    Log::error('broken app name');

    expect(mb_check_encoding(sentText(), 'UTF-8'))->toBeTrue();
});

it('survives invalid utf-8 in the context payload', function (): void {
    FakeTelegram::respondOk();

    Log::error('binary', ['blob' => "\xB1\x31"]);

    expect(FakeTelegram::requestCount())->toBe(1)
        ->and(sentText())->toContain('```json')
        ->and(mb_check_encoding(sentText(), 'UTF-8'))->toBeTrue();
});

it('renders the context as pretty printed json', function (): void {
    FakeTelegram::respondOk();

    Log::error('with context', ['user_id' => 42, 'action' => 'login']);

    expect(sentText())
        ->toContain('```json')
        ->toContain('"user_id": 42')
        ->toContain('"action": "login"');
});

it('omits the context block when there is no context', function (): void {
    FakeTelegram::respondOk();

    Log::error('bare');

    expect(sentText())->not->toContain('```json');
});

it('renders a short message in bold', function (): void {
    FakeTelegram::respondOk();

    Log::error('short and loud');

    expect(sentText())->toContain('*short and loud*');
});

it('renders a long message without bold', function (): void {
    FakeTelegram::respondOk();

    $message = str_repeat('word ', 60);

    Log::error($message);

    expect(sentText())->not->toContain('*'.mb_trim($message).'*');
});

it('reports the calling file and line for plain messages', function (): void {
    FakeTelegram::respondOk();

    Log::error('who called me');

    expect(sentText())
        ->toContain('📍 ')
        ->toContain(str_replace('\\', '\\\\', __FILE__));
});

it('appends the timestamp', function (): void {
    FakeTelegram::respondOk();

    Log::error('when');

    expect(sentText())->toMatch('/🕑 `\d{2}:\d{2}:\d{2}`/u');
});
