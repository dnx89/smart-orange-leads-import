<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Data\ImportCursor;
use App\Data\StepResult;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The imports as the pages work with them: what has been uploaded before, a new file taken in, and
 * a running import moved forward one step at a time. While an import runs, the place it has reached
 * is held in the session of the browser running it, and is dropped when it ends.
 */
class ImportService
{
    /**
     * Imports shown on one page of the history.
     */
    public const PER_PAGE = 20;

    public function __construct(
        private readonly ImportStep $step,
        private readonly Session $session,
    ) {
    }

    /**
     * One page of the upload history, newest first, with the user of every row already loaded.
     *
     * @return LengthAwarePaginator<int, Import>
     */
    public function history(): LengthAwarePaginator
    {
        return Import::query()
            ->with('user:id,name')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Takes an uploaded file in and opens its processing: the row is created first, because the
     * file is stored under the name its id makes.
     */
    public function start(UploadedFile $file, User $user): Import
    {
        $import = Import::query()->create([
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => (int) $file->getSize(),
            'status' => ImportStatus::Processing,
        ]);
        Storage::disk('local')->putFileAs('imports', $file, $import->id . '.csv');

        return $import->refresh();
    }

    public function runStep(Import $import): StepResult
    {
        $cursor = $this->cursor($import);

        try {
            $import = $this->step->run($import->id, $cursor);
        } catch (Throwable $e) {
            report($e);
            $this->session->forget($this->key($import));

            $import->refresh();
            $import->fail('Обробку зупинено через помилку сервера: ' . $e->getMessage());

            return new StepResult($import, null, true);
        }

        if (in_array($import->status, [ImportStatus::Done, ImportStatus::Failed], true)) {
            $this->session->forget($this->key($import));
        } else {
            $this->session->put($this->key($import), $cursor->toArray());
        }

        return new StepResult($import, $this->progress($import, $cursor));
    }

    /**
     * The position to continue from — the start of the file when this import has none yet.
     */
    private function cursor(Import $import): ImportCursor
    {
        return ImportCursor::fromArray((array) $this->session->get($this->key($import), []));
    }

    private function key(Import $import): string
    {
        return 'import.cursor.' . $import->id;
    }

    private function progress(Import $import, ImportCursor $cursor): ?float
    {
        return $import->file_size > 0 ? min(1.0, $cursor->offset / $import->file_size) : null;
    }
}
