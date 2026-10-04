<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Import;

/**
 * What one step leaves behind: the import as it now stands, how much of the file has been read,
 * and whether the step ended by throwing.
 */
final class StepResult
{
    public function __construct(
        public readonly Import $import,
        public readonly ?float $progress,
        public readonly bool $crashed = false,
    ) {
    }
}
