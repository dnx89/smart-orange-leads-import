<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ImportStatus;
use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The state of one import, as the import page reads it.
 *
 * @mixin Import
 */
class ImportResource extends JsonResource
{
    /**
     * The page reads the fields at the top level, so the answer is not wrapped in `data`.
     */
    public static $wrap = null;

    /**
     * `$progress` is the fraction of the file read so far, which the step that just ran passes in.
     * Without it a finished import is 1 and anything else 0.
     */
    public function __construct(Import $resource, private readonly ?float $progress = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'finished' => in_array($this->status, [ImportStatus::Done, ImportStatus::Failed], true),
            'progress' => round($this->progress ?? ($this->status === ImportStatus::Done ? 1.0 : 0.0), 4),
            'rows_total' => $this->rows_total,
            'rows_clean' => $this->rows_clean,
            'rows_warning' => $this->rows_warning,
            'rows_error' => $this->rows_error,
            'error' => $this->error,
        ];
    }
}
