<?php

declare(strict_types=1);

use MarekMiklusek\TelegramLogger\TelegramLogger;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

function callPrivate(string $method, mixed ...$arguments): mixed
{
    return new ReflectionMethod(TelegramLogger::class, $method)->invoke(null, ...$arguments);
}

function callPrivateString(string $method, mixed ...$arguments): string
{
    $result = callPrivate($method, ...$arguments);

    return is_string($result) ? $result : '';
}

it('ignores a nested log while a message is being delivered', function (): void {
    FakeTelegram::respondOk();
    FakeTelegram::onRequest(function (): void {
        TelegramLogger::send('error', 'nested');
    });

    TelegramLogger::send('error', 'outer');

    expect(FakeTelegram::requestCount())->toBe(1);
});

it('reports the first application frame', function (): void {
    $frames = [
        ['file' => '/app/vendor/laravel/framework/src/Log.php', 'line' => 10],
        ['function' => 'call_user_func'],
        ['file' => '/app/app/Services/Billing.php', 'line' => 88],
        ['file' => '/app/app/Http/Kernel.php', 'line' => 5],
    ];

    expect(callPrivate('formatFrames', $frames))
        ->toContain('/app/app/Services/Billing.php')
        ->toContain(':88`');
});

it('returns nothing when every frame is internal', function (): void {
    $frames = [
        ['file' => '/app/vendor/laravel/framework/src/Log.php', 'line' => 10],
        ['file' => '/app/src/TelegramLogger.php', 'line' => 20],
        ['function' => 'call_user_func'],
    ];

    expect(callPrivate('formatFrames', $frames))->toBe('');
});

it('defaults the line number when a frame has none', function (): void {
    expect(callPrivate('formatFrames', [['file' => '/app/app/Console/Kernel.php']]))
        ->toContain(':0`');
});

it('strips the base path from a path inside the application', function (): void {
    $absolute = base_path('app/Services/Billing.php');

    expect(callPrivateString('relativePath', $absolute))
        ->toBe('app/Services/Billing.php');
});

it('keeps a long path in full so the file stays findable', function (): void {
    $long = base_path('vendor/laravel/framework/src/Illuminate/Support/helpers.php');

    expect(callPrivateString('relativePath', $long))
        ->toBe('vendor/laravel/framework/src/Illuminate/Support/helpers.php');
});

it('keeps a short path in full', function (): void {
    $short = base_path('app/Http/Controllers/WebhookController.php');

    expect(callPrivateString('relativePath', $short))
        ->toBe('app/Http/Controllers/WebhookController.php');
});

it('normalises windows separators', function (): void {
    expect(callPrivateString('normalise', 'app\Http\Controllers\Foo.php'))
        ->toBe('app/Http/Controllers/Foo.php');
});

it('prefers the first application frame of an exception', function (): void {
    $frames = [
        ['file' => '/app/vendor/laravel/framework/src/Log.php', 'line' => 10],
        ['function' => 'call_user_func'],
        ['file' => '/app/app/Services/Shoptet/Client.php', 'line' => 88],
    ];

    expect(callPrivate('applicationFrame', $frames, '/fallback.php', '1'))
        ->toBe(['/app/app/Services/Shoptet/Client.php', '88']);
});

it('falls back when the exception trace has no application frame', function (): void {
    $frames = [['file' => '/app/vendor/foo/bar.php', 'line' => 3]];

    expect(callPrivate('applicationFrame', $frames, '/fallback.php', '7'))
        ->toBe(['/fallback.php', '7']);
});

it('defaults the line of an application frame without one', function (): void {
    $frames = [['file' => '/app/app/Console/Kernel.php']];

    expect(callPrivate('applicationFrame', $frames, '/fallback.php', '1'))
        ->toBe(['/app/app/Console/Kernel.php', '0']);
});

it('keeps a path outside the application untouched', function (): void {
    expect(callPrivateString('relativePath', '/somewhere/else/File.php'))
        ->toBe('/somewhere/else/File.php');
});

it('keeps the path when no base path is configured', function (): void {
    config()->set('telegram-logger.base_path', '');

    expect(callPrivateString('relativePath', '/any/File.php'))->toBe('/any/File.php');
});

it('recognises internal frames', function (string $file): void {
    expect(callPrivate('isInternalFrame', $file))->toBeTrue();
})->with([
    '/app/vendor/laravel/framework/src/Foo.php',
    '/app/vendor/Illuminate/Log/Logger.php',
    '/app/src/TelegramLogger.php',
]);

it('recognises application frames', function (): void {
    expect(callPrivate('isInternalFrame', '/app/app/Http/Controllers/UserController.php'))->toBeFalse();
});

it('drops the context when the remaining budget is too small', function (): void {
    expect(callPrivate('formatContext', ['user_id' => 42], 20))->toBe('');
});

it('returns an empty string when a code entity has no budget', function (): void {
    expect(callPrivate('fitCode', 'anything', 0))->toBe('');
});

it('shrinks an oversized payload to the telegram limit', function (): void {
    $text = str_repeat('a', 5_000);
    $footer = 'footer';

    $result = callPrivateString('fitWithin', $text, $footer);

    expect(mb_strlen($result))->toBe(4096)
        ->and($result)->toEndWith('…'.$footer);
});

it('leaves a payload within the limit untouched', function (): void {
    expect(callPrivate('fitWithin', 'short', 'footer'))->toBe('shortfooter');
});

it('falls back to the default when a string setting holds null', function (): void {
    config()->set('telegram-logger.bot_token');

    expect(callPrivate('configString', 'telegram-logger.bot_token', 'fallback'))->toBe('fallback');
});

it('falls back to the default when a boolean setting holds null', function (): void {
    config()->set('telegram-logger.is_enabled');

    expect(callPrivate('configBool', 'telegram-logger.is_enabled', true))->toBeTrue();
});

it('escapes every markdown v2 reserved character', function (): void {
    $reserved = '_*[]()~`>#+-=|{}.!\\';

    $escaped = callPrivate('escapeText', $reserved);

    expect($escaped)->toBe('\_\*\[\]\(\)\~\`\>\#\+\-\=\|\{\}\.\!\\\\');
});

it('escapes only backslash and backtick inside code entities', function (): void {
    expect(callPrivate('escapeCode', 'a.b-c`d\\e'))->toBe('a.b-c\\`d\\\\e');
});

it('reverses markdown escaping for the plain text fallback', function (): void {
    $escaped = callPrivate('escapeText', 'Hello (world). Done!');

    expect(callPrivate('stripMarkdown', $escaped))->toBe('Hello (world). Done!');
});
