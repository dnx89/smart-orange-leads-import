<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ImportRowStatus;
use App\Enums\RowFlag;
use App\Services\Import\ImportRowValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportRowValidatorTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private static function validRow(): array
    {
        return [
            'external_id' => 'LD-000001',
            'created_at' => '2025-07-10 05:06:54',
            'first_name' => 'Олександр',
            'last_name' => 'Поліщук',
            'phone' => '380672341057',
            'email' => 'oleksandr@ukr.net',
            'city' => 'Київ',
            'source' => 'Facebook Ads',
            'utm_campaign' => '',
            'product' => 'Контекстна реклама',
            'budget_uah' => '',
            'status' => 'new',
            'manager' => '',
            'comment' => '',
            'next_contact_at' => '',
        ];
    }

    public function test_a_valid_row_has_no_flags(): void
    {
        $this->assertSame(0, (new ImportRowValidator())->validate(self::validRow()));
    }

    /**
     * @return array<string, array{string, string, RowFlag}>
     */
    public static function invalidValues(): array
    {
        return [
            'external id without prefix' => ['external_id', '000001', RowFlag::ExternalId],
            'external id empty' => ['external_id', '', RowFlag::ExternalId],
            'created at in another format' => ['created_at', '10.07.2025 05:06', RowFlag::CreatedAt],
            'created at on a day that does not exist' => ['created_at', '2026-02-30 10:00:00', RowFlag::CreatedAt],
            'first name empty' => ['first_name', '', RowFlag::FirstName],
            'first name too long' => ['first_name', str_repeat('а', 101), RowFlag::FirstName],
            'last name empty' => ['last_name', '', RowFlag::LastName],
            'phone as a spreadsheet error' => ['phone', '#ERROR!', RowFlag::Phone],
            'phone too short' => ['phone', '501234', RowFlag::Phone],
            'phone with a plus' => ['phone', '+380672341057', RowFlag::Phone],
            'email without local part' => ['email', '@gmail.com', RowFlag::Email],
            'email with two at signs' => ['email', 'oleksandr@@ukr.net', RowFlag::Email],
            'email without at sign' => ['email', 'oleksandr.ukr.net', RowFlag::Email],
            'email empty' => ['email', '', RowFlag::Email],
            'city empty' => ['city', '', RowFlag::City],
            'source empty' => ['source', '', RowFlag::Source],
            'product empty' => ['product', '', RowFlag::Product],
            'budget with letters' => ['budget_uah', '23k', RowFlag::Budget],
            'budget above the column range' => ['budget_uah', '5000000000', RowFlag::Budget],
            'status not in the list' => ['status', 'unknown', RowFlag::Status],
            'comment too long' => ['comment', str_repeat('к', 256), RowFlag::Comment],
            'next contact in another format' => ['next_contact_at', '2025-07-23', RowFlag::NextContactAt],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_an_invalid_value_raises_its_flag_only(string $field, string $value, RowFlag $flag): void
    {
        $row = array_merge(self::validRow(), [$field => $value]);

        $this->assertSame($flag->value, (new ImportRowValidator())->validate($row));
    }

    public function test_every_problem_of_a_row_is_reported_at_once(): void
    {
        $row = array_merge(self::validRow(), ['phone' => '#ERROR!', 'email' => '', 'first_name' => '']);

        $flags = (new ImportRowValidator())->validate($row);

        $this->assertSame([RowFlag::FirstName, RowFlag::Phone, RowFlag::Email], RowFlag::fromMask($flags));
    }

    public function test_budget_accepts_plain_and_no_break_spaces_between_thousands(): void
    {
        $this->assertSame(23700, ImportRowValidator::parseBudget('23 700'));
        $this->assertSame(23700, ImportRowValidator::parseBudget("23\u{00A0}700"));
        $this->assertSame(1479800, ImportRowValidator::parseBudget("1\u{00A0}479\u{00A0}800"));
        $this->assertNull(ImportRowValidator::parseBudget(''));
        $this->assertNull(ImportRowValidator::parseBudget('23 700.50'));
    }

    public function test_only_a_critical_flag_makes_the_row_an_error(): void
    {
        $this->assertSame(ImportRowStatus::Clean, ImportRowStatus::fromFlags(0));
        $this->assertSame(ImportRowStatus::Warning, ImportRowStatus::fromFlags(RowFlag::Email->value | RowFlag::FirstName->value));
        $this->assertSame(ImportRowStatus::Error, ImportRowStatus::fromFlags(RowFlag::Phone->value));
        $this->assertSame(ImportRowStatus::Error, ImportRowStatus::fromFlags(RowFlag::Columns->value | RowFlag::Email->value));
    }
}
