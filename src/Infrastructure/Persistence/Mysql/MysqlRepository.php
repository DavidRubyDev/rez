<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use UnexpectedValueException;

abstract class MysqlRepository
{
    protected function str(mixed $value): string
    {
        if (!is_string($value)) {
            throw new UnexpectedValueException('Expected a string value from database row.');
        }

        return $value;
    }

    protected function int(mixed $value): int
    {
        if (!is_int($value)) {
            throw new UnexpectedValueException('Expected an integer value from database row.');
        }

        return $value;
    }

    /**
     * Aggregate columns (SUM, COUNT) come back from PDO as numeric strings rather than ints,
     * so they need a looser check than a plain typed column read through int().
     */
    protected function aggregateInt(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('Expected a numeric aggregate value from database row.');
        }

        return (int) $value;
    }

    protected function nullStr(mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw new UnexpectedValueException('Expected a nullable string value from database row.');
        }

        return $value;
    }

    protected function nullInt(mixed $value): ?int
    {
        if ($value !== null && !is_int($value)) {
            throw new UnexpectedValueException('Expected a nullable integer value from database row.');
        }

        return $value;
    }

    protected function bool(mixed $value): bool
    {
        if (!is_int($value) && !is_bool($value)) {
            throw new UnexpectedValueException('Expected a boolean value from database row.');
        }

        return (bool) $value;
    }
}
