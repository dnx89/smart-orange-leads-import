<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * One problem found in an imported row. Each case is one bit of `import_rows.flags`,
 * so a row carries every problem it has at once.
 */
enum RowFlag: int
{
    case Columns = 1;
    case ExternalId = 2;
    case CreatedAt = 4;
    case FirstName = 8;
    case LastName = 16;
    case Phone = 32;
    case Email = 64;
    case City = 128;
    case Source = 256;
    case UtmCampaign = 512;
    case Product = 1024;
    case Budget = 2048;
    case Status = 4096;
    case Manager = 8192;
    case Comment = 16384;
    case NextContactAt = 32768;

    /**
     * Flags that make the whole row an error rather than a warning.
     */
    public static function criticalMask(): int
    {
        return self::Columns->value | self::Phone->value;
    }

    public function isCritical(): bool
    {
        return ($this->value & self::criticalMask()) !== 0;
    }

    /**
     * @return list<self>
     */
    public static function fromMask(int $mask): array
    {
        return array_values(
            array_filter(
                self::cases(),
                static fn(self $flag): bool => ($mask & $flag->value) !== 0,
            ),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Columns => 'Невірна кількість колонок',
            self::ExternalId => 'Невірний external_id',
            self::CreatedAt => 'Невірна дата створення',
            self::FirstName => 'Невірне ім\'я',
            self::LastName => 'Невірне прізвище',
            self::Phone => 'Невірний телефон',
            self::Email => 'Невірний email',
            self::City => 'Невірне місто',
            self::Source => 'Невірне джерело',
            self::UtmCampaign => 'Невірна utm_campaign',
            self::Product => 'Невірний продукт',
            self::Budget => 'Невірний бюджет',
            self::Status => 'Невірний статус',
            self::Manager => 'Невірний менеджер',
            self::Comment => 'Невірний коментар',
            self::NextContactAt => 'Невірна дата наступного контакту',
        };
    }
}
