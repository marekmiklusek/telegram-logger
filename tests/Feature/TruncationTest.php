<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

it('keeps the payload within the telegram character limit', function (): void {
    FakeTelegram::respondOk();

    Log::error(str_repeat('a', 20_000));

    expect(mb_strlen(sentText()))->toBeLessThanOrEqual(4096);
});

it('measures the limit in characters rather than bytes', function (): void {
    FakeTelegram::respondOk();

    Log::error(str_repeat('ž', 3_000), ['blob' => str_repeat('ž', 3_000)]);

    $text = sentText();
    $byteLength = mb_strlen($text, '8bit');

    expect($byteLength)->toBeGreaterThan(4096)
        ->and(mb_strlen($text))->toBeLessThanOrEqual(4096);
});

it('never cuts a multibyte character in half', function (): void {
    FakeTelegram::respondOk();

    Log::error(str_repeat('🔥', 5_000));

    expect(mb_check_encoding(sentText(), 'UTF-8'))->toBeTrue();
});

it('truncates with an ellipsis that needs no markdown escaping', function (): void {
    FakeTelegram::respondOk();

    Log::error(str_repeat('a', 20_000));

    expect(sentText())->toContain('…')
        ->and(sentText())->not->toContain('...');
});

it('keeps code fences balanced when the context is truncated', function (): void {
    FakeTelegram::respondOk();

    Log::error('huge context', ['blob' => str_repeat('A', 20_000)]);

    expect(mb_substr_count(sentText(), '```') % 2)->toBe(0)
        ->and(mb_strlen(sentText()))->toBeLessThanOrEqual(4096);
});

it('still appends the timestamp to a truncated message', function (): void {
    FakeTelegram::respondOk();

    Log::error(str_repeat('a', 20_000));

    expect(sentText())->toContain('🕑 ');
});

it('caps the message so the context still fits', function (): void {
    FakeTelegram::respondOk();

    Log::error(str_repeat('a', 4_000), ['user_id' => 42]);

    expect(mb_strlen(sentText()))->toBeLessThanOrEqual(4096)
        ->and(sentText())->toContain('"user_id": 42')
        ->and(sentText())->toContain('…');
});

it('caps an oversized application name', function (): void {
    config()->set('app.name', str_repeat('N', 4_000));

    FakeTelegram::respondOk();

    Log::error('squeezed', ['user_id' => 42]);

    expect(mb_strlen(sentText()))->toBeLessThanOrEqual(4096);
});
