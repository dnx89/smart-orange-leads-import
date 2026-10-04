<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Обробка',
            self::Done => 'Завершено',
            self::Failed => 'Помилка',
        };
    }
}
