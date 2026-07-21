<?php

declare(strict_types=1);

namespace Rez\Application\Validation;

final class ListParamsValidator
{
    public const MAX_LIMIT = 100;

    /**
     * @param string[] $allowedSortColumns
     * @throws \InvalidArgumentException
     */
    public static function validate(
        ?int $offset,
        ?int $limit,
        ?string $sortBy,
        ?string $sortDir,
        array $allowedSortColumns,
    ): void {
        if ($offset !== null && $offset < 0) {
            throw new \InvalidArgumentException('offset must be >= 0.');
        }

        if ($limit !== null && ($limit < 1 || $limit > self::MAX_LIMIT)) {
            throw new \InvalidArgumentException(sprintf('limit must be between 1 and %d.', self::MAX_LIMIT));
        }

        if ($sortBy !== null && !in_array($sortBy, $allowedSortColumns, true)) {
            throw new \InvalidArgumentException(sprintf('sortBy must be one of: %s.', implode(', ', $allowedSortColumns)));
        }

        if ($sortDir !== null && !in_array($sortDir, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('sortDir must be "asc" or "desc".');
        }
    }
}
