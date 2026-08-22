<?php

declare(strict_types=1);

use MarekMiklusek\TelegramLogger\Tests\TestCase;
use MarekMiklusek\TelegramLogger\Tests\FakeTelegram;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

function sentText(int $index = 0): string
{
    return sentParam('text', $index);
}

function sentParam(string $name, int $index = 0): string
{
    return sentParams($index)[$name] ?? '';
}

function hasSentParam(string $name, int $index = 0): bool
{
    return array_key_exists($name, sentParams($index));
}

function sentUrl(int $index = 0): string
{
    return FakeTelegram::request($index)['url'] ?? '';
}

/**
 * @return array<string, string>
 */
function sentParams(int $index = 0): array
{
    return FakeTelegram::request($index)['params'] ?? [];
}
