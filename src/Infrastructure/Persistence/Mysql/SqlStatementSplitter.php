<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

final class SqlStatementSplitter
{
    /** @return list<string> */
    public static function split(string $sql): array
    {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn (string $s) => $s !== '',
        );

        return array_values($statements);
    }
}
