<?php

namespace MarekMiklusek\TelegramLogger\Enums;

enum LevelEnum : int
{
    case EMERGENCY = 0;
    case ALERT = 1;
    case CRITICAL = 2;
    case ERROR = 3;
    case WARNING = 4;
    case NOTICE = 5;
    case INFO = 6;
    case DEBUG = 7;

    public static function values(): array
    {
        return array_map(fn($enum) => $enum->value, self::cases());
    }

   
    public function getName(): string
    {
        return strtolower($this->name);
    }

    public function getEmoji(): string
    {
        return match ($this) {
            self::EMERGENCY => '🆘',
            self::ALERT => '🚨',
            self::CRITICAL => '🚑',
            self::ERROR => '❌',
            self::WARNING => '⚠️',
            self::NOTICE => '🔔',
            self::INFO => 'ℹ️',
            self::DEBUG => '🔍',
        };
    }
} 