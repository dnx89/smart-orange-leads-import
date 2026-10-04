<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Data\ImportCursor;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads the next portion of an uploaded file into `import_rows`, for as long as the time
 * budget allows, and moves the cursor it was given to the position it stopped at.
 *
 * The rows a step read and the counters it increased are committed in one transaction, so the
 * two never disagree.
 */
final class ImportStep
{
    private const STEP_SECONDS_CAP = 20;

    private const MARGIN_SECONDS = 10;

    /**
     * Rows sent to the database in one INSERT.
     */
    private const BATCH_SIZE = 1000;

    private readonly float $budgetSeconds;

    public function __construct(
        private readonly ImportRowMapper $mapper,
        ?float $budgetSeconds = null,
    ) {
        $this->budgetSeconds = $budgetSeconds ?? self::budgetFromExecutionTime();
    }

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
                        return $this->fail(
                            $import,
                            'Невірний заголовок файлу. Очікуються колонки: '
                            .implode(', ', ImportRow::fileColumns()).'.',
                        );
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
     * @param  resource  $file
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

            $record = $this->mapper->toRecord($import->id, $rowNumber++, $cells);
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
            DB::afterCommit(static fn() => Storage::disk('local')->delete($import->filePath()));
        }
    }

    /**
     * @param  resource  $file
     * @return list<string>
     */
    private function readHeader($file): array
    {
        $cells = fgetcsv($file, null, ',', '"', '');
        if ($cells === false || $cells === [null]) {
            return [];
        }

        $cells[0] = preg_replace('/^\x{FEFF}/u', '', (string) $cells[0]);

        return array_map(static fn(?string $cell): string => trim((string) $cell), $cells);
    }

    private function count(Import $import, string $status): void
    {
        $import->rows_total++;

        match (ImportRowStatus::from($status)) {
            ImportRowStatus::Clean => $import->rows_clean++,
            ImportRowStatus::Warning => $import->rows_warning++,
            ImportRowStatus::Error => $import->rows_error++,
        };
    }

    private function fail(Import $import, string $message): Import
    {
        $import->status = ImportStatus::Failed;
        $import->error = $message;
        $import->finished_at = now();
        $import->save();

        return $import;
    }

    private static function budgetFromExecutionTime(): float
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) {
            return self::STEP_SECONDS_CAP;
        }

        return (float) max(1, min($limit - self::MARGIN_SECONDS, self::STEP_SECONDS_CAP));
    }
}
