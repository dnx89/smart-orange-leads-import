<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportRowStatus;
use App\Enums\RowFlag;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * What can be read back about the rows an import stored: a page of them at a time, how many of them
 * carry each problem, and all of them as the result file.
 */
class ImportRows
{
    /**
     * Rows shown on one page of the result table.
     */
    public const PER_PAGE = 50;

    /**
     * One page of an import's rows, in file order, narrowed by a status and by a flag when they
     * are given.
     *
     * @return LengthAwarePaginator<int, ImportRow>
     */
    public function page(Import $import, ?ImportRowStatus $status, ?RowFlag $flag): LengthAwarePaginator
    {
        return $import->rows()
            ->when($status, fn (Builder $query) => $query->where('status', $status->value))
            ->when($flag, fn (Builder $query) => $query->whereRaw('(flags & ?) <> 0', [$flag->value]))
            ->orderBy('row_number')
            ->paginate(self::PER_PAGE);
    }

    /**
     * How many rows carry each flag, counted in one query.
     *
     * @return array<int, int> flag value => row count, only flags that occur
     */
    public function flagCounts(Import $import): array
    {
        $flags = RowFlag::cases();
        $select = implode(', ', array_map(
            static fn (RowFlag $flag): string => 'SUM((flags & '.$flag->value.') <> 0) AS f'.$flag->value,
            $flags,
        ));

        $totals = (array) $import->rows()->toBase()->selectRaw($select)->first();

        $counts = [];
        foreach ($flags as $flag) {
            $count = (int) ($totals['f'.$flag->value] ?? 0);
            if ($count > 0) {
                $counts[$flag->value] = $count;
            }
        }

        return $counts;
    }

    /**
     * Writes the result file: every row in file order, its status and flags, then the values as
     * they were in the source file. UTF-8 with a byte order mark, so Excel opens the Cyrillic
     * correctly.
     *
     * @param  resource  $output
     */
    public function writeCsv(Import $import, $output): void
    {
        fwrite($output, "\u{FEFF}");
        fputcsv($output, ['row', 'status', 'flags', ...ImportRow::fileColumns()], ',', '"', '');

        $import->rows()->lazyById(2000)->each(static function (ImportRow $row) use ($output): void {
            $flags = implode('; ', array_map(static fn (RowFlag $flag): string => $flag->label(), $row->problems()));
            fputcsv($output, [$row->row_number, $row->status->label(), $flags, ...$row->sourceValues()], ',', '"', '');
        });

        fclose($output);
    }
}
