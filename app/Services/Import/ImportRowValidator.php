<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\LeadStatus;
use App\Enums\RowFlag;
use DateTimeImmutable;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;

/**
 * Checks one row of the leads file and reports every problem as a RowFlag bit. Email is held to
 * the same RFC check as Laravel's `email` rule.
 */
final class ImportRowValidator
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    private const NAME_MAX = 100;

    private const TEXT_MAX = 255;

    private const BUDGET_MAX = 4294967295;

    private EmailValidator $emailValidator;

    private RFCValidation $rfcValidation;

    public function __construct()
    {
        $this->emailValidator = new EmailValidator();
        $this->rfcValidation = new RFCValidation();
    }

    /**
     * @param  array<string, string>  $row  trimmed values keyed by the file's column names
     */
    public function validate(array $row): int
    {
        $flags = 0;

        if (preg_match('/^LD-\d{6}$/', $row['external_id']) !== 1) {
            $flags |= RowFlag::ExternalId->value;
        }
        if (self::parseDate($row['created_at']) === null) {
            $flags |= RowFlag::CreatedAt->value;
        }
        if (!self::isFilled($row['first_name'], self::NAME_MAX)) {
            $flags |= RowFlag::FirstName->value;
        }
        if (!self::isFilled($row['last_name'], self::NAME_MAX)) {
            $flags |= RowFlag::LastName->value;
        }
        if (preg_match('/^380\d{9}$/', $row['phone']) !== 1) {
            $flags |= RowFlag::Phone->value;
        }
        if (!$this->isEmail($row['email'])) {
            $flags |= RowFlag::Email->value;
        }
        if (!self::isFilled($row['city'], self::NAME_MAX)) {
            $flags |= RowFlag::City->value;
        }
        if (!self::isFilled($row['source'], self::NAME_MAX)) {
            $flags |= RowFlag::Source->value;
        }
        if (mb_strlen($row['utm_campaign']) > self::TEXT_MAX) {
            $flags |= RowFlag::UtmCampaign->value;
        }
        if (!self::isFilled($row['product'], self::NAME_MAX)) {
            $flags |= RowFlag::Product->value;
        }
        if ($row['budget_uah'] !== '' && self::parseBudget($row['budget_uah']) === null) {
            $flags |= RowFlag::Budget->value;
        }
        if (LeadStatus::tryFrom($row['status']) === null) {
            $flags |= RowFlag::Status->value;
        }
        if (mb_strlen($row['manager']) > self::TEXT_MAX) {
            $flags |= RowFlag::Manager->value;
        }
        if (mb_strlen($row['comment']) > self::TEXT_MAX) {
            $flags |= RowFlag::Comment->value;
        }
        if ($row['next_contact_at'] !== '' && self::parseDate($row['next_contact_at']) === null) {
            $flags |= RowFlag::NextContactAt->value;
        }

        return $flags;
    }

    /**
     * Accepts `23 700` with a plain or a no-break space (U+00A0) between the thousands.
     */
    public static function parseBudget(string $value): ?int
    {
        $digits = str_replace([' ', "\u{00A0}"], '', $value);

        if ($digits === '' || !ctype_digit($digits) || (int) $digits > self::BUDGET_MAX) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * Rejects values that the format parses but shifts, such as 2026-02-30.
     */
    public static function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);

        return $date !== false && $date->format(self::DATE_FORMAT) === $value ? $date : null;
    }

    private function isEmail(string $value): bool
    {
        return $value !== ''
            && mb_strlen($value) <= self::TEXT_MAX
            && $this->emailValidator->isValid($value, $this->rfcValidation);
    }

    private static function isFilled(string $value, int $max): bool
    {
        return $value !== '' && mb_strlen($value) <= $max;
    }
}
