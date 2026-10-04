<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded file: who uploaded it, what it was, and how its processing ended.
 *
 * @property int $id
 * @property int $user_id
 * @property string $file_name
 * @property int $file_size
 * @property int $rows_total
 * @property int $rows_clean
 * @property int $rows_warning
 * @property int $rows_error
 * @property ImportStatus $status
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class Import extends Model
{
    protected $fillable = [
        'user_id',
        'file_name',
        'file_size',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * Where the uploaded file lives on the local disk while the import is running.
     */
    public function filePath(): string
    {
        return 'imports/'.$this->id.'.csv';
    }

    /**
     * Stops the import with the reason the page shows. The reason reaches the user, so it says what
     * is wrong with the file or what went wrong on the server.
     */
    public function fail(string $reason): void
    {
        $this->status = ImportStatus::Failed;
        $this->error = $reason;
        $this->finished_at = now();
        $this->save();
    }
}
