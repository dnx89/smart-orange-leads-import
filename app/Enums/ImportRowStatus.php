<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportRowStatus: string
{
    case Clean = 'clean';
    case Warning = 'warning';
    case Error = 'error';

    public static function fromFlags(int $flags): self
    {
        if (($flags & RowFlag::criticalMask()) !== 0) {
            return self::Error;
        }

        return $flags === 0 ? self::Clean : self::Warning;
    }

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'OK',
            self::Warning => 'Попередження',
            self::Error => 'Помилка',
        };
    }
}
