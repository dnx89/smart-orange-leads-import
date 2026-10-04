<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Data\ImportCursor;
use App\Data\StepResult;
use App\Enums\ImportStatus;
use App\Models\Import;
use Illuminate\Contracts\Session\Session;
use Throwable;

/**
 * Carries an import through its steps: the position to continue from is taken out of the session
 * before a step and put back after it, and an import that has ended keeps nothing there.
 */
class ImportProcessor
{
    public function __construct(
        private readonly ImportStep $step,
        private readonly Session $session,
    ) {}

    public function run(Import $import): StepResult
    {
        $cursor = $this->cursor($import);

        try {
            $import = $this->step->run($import->id, $cursor);
        } catch (Throwable $e) {
            report($e);
            $this->session->forget($this->key($import));

            return new StepResult($this->fail($import, $e), null, true);
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
        return 'import.cursor.'.$import->id;
    }

    private function fail(Import $import, Throwable $e): Import
    {
        $import->refresh();
        $import->status = ImportStatus::Failed;
        $import->error = 'Обробку зупинено через помилку сервера: '.$e->getMessage();
        $import->finished_at = now();
        $import->save();

        return $import;
    }

    private function progress(Import $import, ImportCursor $cursor): ?float
    {
        return $import->file_size > 0 ? min(1.0, $cursor->offset / $import->file_size) : null;
    }
}
