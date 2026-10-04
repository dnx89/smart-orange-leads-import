<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportRowStatus;
use App\Enums\RowFlag;
use App\Http\Resources\ImportRowResource;
use App\Models\Import;
use App\Services\Import\ImportRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The rows of one import, for the table on its page.
 */
class ImportRowController extends Controller
{
    public function index(Request $request, Import $import, ImportRows $rows): AnonymousResourceCollection
    {
        $page = $rows->page(
            $import,
            ImportRowStatus::tryFrom((string) $request->query('status')),
            RowFlag::tryFrom((int) $request->query('flag')),
        );

        return ImportRowResource::collection($page)->additional([
            'flag_counts' => collect($rows->flagCounts($import))
                ->map(static fn(int $count, int $value): array
                    => [
                    'value' => $value,
                    'label' => RowFlag::from($value)->label(),
                    'critical' => RowFlag::from($value)->isCritical(),
                    'count' => $count,
                ])
                ->values(),
        ]);
    }
}
