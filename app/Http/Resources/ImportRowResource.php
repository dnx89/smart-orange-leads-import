<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\RowFlag;
use App\Models\ImportRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One stored line of a file, as the import's page reads it.
 *
 * @mixin ImportRow
 */
class ImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'row_number' => $this->row_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'problems' => array_map(
                static fn(RowFlag $flag): string => $flag->label(),
                $this->problems(),
            ),
            'values' => $this->sourceValues(),
        ];
    }
}
