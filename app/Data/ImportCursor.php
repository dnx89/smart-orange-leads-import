<?php

declare(strict_types=1);

namespace App\Data;

/**
 * How far a file has been read: the byte the next step starts at, and the number the next row
 * gets. Line 1 is the header, so the first data row is 2.
 */
final class ImportCursor
{
    public function __construct(
        public int $offset = 0,
        public int $rowNumber = 2,
    ) {
    }

    /**
     * @param array{offset?: int, row?: int} $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self((int) ($stored['offset'] ?? 0), (int) ($stored['row'] ?? 2));
    }

    /**
     * @return array{offset: int, row: int}
     */
    public function toArray(): array
    {
        return ['offset' => $this->offset, 'row' => $this->rowNumber];
    }
}
