<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportRowStatus;
use App\Enums\RowFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an imported file, stored whatever its quality, with the problems found in it.
 *
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property ImportRowStatus $status
 * @property int $flags
 */
class ImportRow extends Model
{
    /**
     * The source columns in file order, mapped to the `import_rows` column holding each value.
     */
    public const SOURCE_COLUMNS = [
        'external_id' => 'external_id',
        'created_at' => 'created_at_raw',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'phone' => 'phone',
        'email' => 'email',
        'city' => 'city',
        'source' => 'source',
        'utm_campaign' => 'utm_campaign',
        'product' => 'product',
        'budget_uah' => 'budget_uah',
        'status' => 'lead_status',
        'manager' => 'manager',
        'comment' => 'comment',
        'next_contact_at' => 'next_contact_at',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ImportRowStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * The columns the uploaded file must have, in file order.
     *
     * @return list<string>
     */
    public static function fileColumns(): array
    {
        return array_keys(self::SOURCE_COLUMNS);
    }

    /**
     * @return list<RowFlag>
     */
    public function problems(): array
    {
        return RowFlag::fromMask($this->flags);
    }

    /**
     * @return list<string|null>
     */
    public function sourceValues(): array
    {
        return array_map(fn(string $column): ?string => $this->getAttribute($column),
            array_values(self::SOURCE_COLUMNS),
        );
    }
}
