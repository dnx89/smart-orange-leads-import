<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportRowStatus;
use App\Enums\RowFlag;
use App\Models\ImportRow;

/**
 * Turns one parsed CSV line into an `import_rows` record: its values exactly as they stood in the
 * file, the problems found in them as flags, and the status those flags make.
 */
final class ImportRowMapper
{
    private const COLUMN_WIDTH = 255;

    public function __construct(private readonly ImportRowValidator $validator)
    {
    }

    /**
     * A line with the wrong number of cells still becomes a row, with the values it does have.
     *
     * @param list<string|null> $cells
     * @return array<string, mixed>
     */
    public function toRecord(int $importId, int $rowNumber, array $cells): array
    {
        $record = ['import_id' => $importId, 'row_number' => $rowNumber];
        $columnCount = count(ImportRow::SOURCE_COLUMNS);

        $row = array_combine(
            array_keys(ImportRow::SOURCE_COLUMNS),
            array_map(
                static fn (?string $cell): string => trim((string) $cell),
                array_pad(array_slice($cells, 0, $columnCount), $columnCount, null),
            ),
        );
        $flags = $this->validator->validate($row);
        if (count($cells) !== $columnCount) {
            $flags |= RowFlag::Columns->value;
        }

        foreach (ImportRow::SOURCE_COLUMNS as $name => $column) {
            $record[$column] = $row[$name] === '' ? null : mb_substr($row[$name], 0, self::COLUMN_WIDTH);
        }

        return $record + [
                'status' => ImportRowStatus::fromFlags($flags)->value,
                'flags' => $flags,
            ];
    }
}
