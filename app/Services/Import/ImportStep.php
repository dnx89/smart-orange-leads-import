<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Data\ImportCursor;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\RowFlag;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads an uploaded file into `import_rows` in portions: one call reads for as long as its time
 * budget allows, stores every line it reads as it stood in the file along with the problems found
 * in it, and leaves the cursor at the byte it stopped on. The rows and the import's counters are
 * written in one transaction.
 */
final class ImportStep
{
    private const STEP_SECONDS_CAP = 10;

    private const MARGIN_SECONDS = 5;

    /**
     * Rows sent to the database in one INSERT.
     */
    private const BATCH_SIZE = 1000;

    /**
     * Longest value kept in a source column; a longer one is cut to this.
     */
    private const COLUMN_WIDTH = 255;

    private readonly float $budgetSeconds;

    public function __construct(
        private readonly ImportRowValidator $validator,
        ?float $budgetSeconds = null,
    ) {
        $this->budgetSeconds = $budgetSeconds ?? self::budgetFromExecutionTime();
    }

    /**
     * @param  int  $importId
     * @param  ImportCursor  $cursor
     * @param  float|null  $budgetSeconds
     * @return Import
     * @throws \Throwable
     */
    public function run(int $importId, ImportCursor $cursor, ?float $budgetSeconds = null): Import
    {
        $budgetSeconds ??= $this->budgetSeconds;
        $startedAt = microtime(true);

        return DB::transaction(function () use ($importId, $cursor, $budgetSeconds, $startedAt): Import {
            /** @var Import $import */
            $import = Import::query()->findOrFail($importId);

            if ($import->status !== ImportStatus::Processing) {
                return $import;
            }

            $file = fopen(Storage::disk('local')->path($import->filePath()), 'rb');
            if ($file === false) {
                throw new RuntimeException('Не вдалося відкрити завантажений файл.');
            }

            try {
                if ($cursor->offset === 0) {
                    $header = $this->readHeader($file);
                    if ($header !== ImportRow::fileColumns()) {
                        $import->fail('Невірний заголовок файлу. Очікуються колонки: '
                            . implode(', ', ImportRow::fileColumns()) . '.');

                        return $import;
                    }
                    $import->started_at = now();
                } else {
                    fseek($file, $cursor->offset);
                }

                $this->readRows($import, $cursor, $file, $budgetSeconds, $startedAt);
            } finally {
                fclose($file);
            }

            return $import;
        });
    }

    /**
     * @param resource $file
     */
    private function readRows(Import $import, ImportCursor $cursor, $file, float $budgetSeconds, float $startedAt): void
    {
        $batch = [];
        $rowNumber = $cursor->rowNumber;
        $reachedEnd = true;

        // The clock is checked after a row, not before, so every step moves the file forward
        // by at least one row even when the budget is already spent.
        while (($cells = fgetcsv($file, null, ',', '"', '')) !== false) {
            if ($cells === [null]) {
                $rowNumber++;
                continue;
            }

            $record = $this->toRecord($import->id, $rowNumber++, $cells);
            $batch[] = $record;
            $this->count($import, $record['status']);

            if (count($batch) >= self::BATCH_SIZE) {
                DB::table('import_rows')->insert($batch);
                $batch = [];
            }

            if (microtime(true) - $startedAt >= $budgetSeconds) {
                $reachedEnd = false;
                break;
            }
        }

        if ($batch !== []) {
            DB::table('import_rows')->insert($batch);
        }

        $cursor->offset = (int) ftell($file);
        $cursor->rowNumber = $rowNumber;

        if ($reachedEnd) {
            $import->status = ImportStatus::Done;
            $import->finished_at = now();
        }

        $import->save();

        if ($reachedEnd) {
            DB::afterCommit(static fn () => Storage::disk('local')->delete($import->filePath()));
        }
    }

    /**
     * A line with the wrong number of cells still becomes a row, with the values it does have.
     *
     * @param list<string|null> $cells
     * @return array<string, mixed>
     */
    private function toRecord(int $importId, int $rowNumber, array $cells): array
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

    /**
     * @param resource $file
     * @return list<string>
     */
    private function readHeader($file): array
    {
        $cells = fgetcsv($file, null, ',', '"', '');
        if ($cells === false || $cells === [null]) {
            return [];
        }

        $cells[0] = preg_replace('/^\x{FEFF}/u', '', (string) $cells[0]);

        return array_map(static fn (?string $cell): string => trim((string) $cell), $cells);
    }

    /**
     * @param  Import  $import
     * @param  string  $status
     * @return void
     */
    private function count(Import $import, string $status): void
    {
        $import->rows_total++;

        match (ImportRowStatus::from($status)) {
            ImportRowStatus::Clean => $import->rows_clean++,
            ImportRowStatus::Warning => $import->rows_warning++,
            ImportRowStatus::Error => $import->rows_error++,
        };
    }

    /**
     * @return float
     */
    private static function budgetFromExecutionTime(): float
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) {
            return self::STEP_SECONDS_CAP;
        }

        return (float) max(1, min($limit - self::MARGIN_SECONDS, self::STEP_SECONDS_CAP));
    }
}
