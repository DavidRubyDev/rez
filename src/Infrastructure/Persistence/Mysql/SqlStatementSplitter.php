<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

final class SqlStatementSplitter
{
    /**
     * Splits a SQL file into individual statements, ignoring `;` inside `--` line comments
     * and single/double-quoted string literals. Does not handle escaped quotes (`\'`, `''`)
     * — a full SQL lexer is out of scope here; keep migration/seed files free of escaped
     * quotes in string literals if this ever matters.
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = array_filter(
            array_map('trim', self::splitOnSemicolons(self::stripLineComments($sql))),
            fn (string $s) => $s !== '',
        );

        return array_values($statements);
    }

    private static function stripLineComments(string $sql): string
    {
        $result    = '';
        $length    = strlen($sql);
        $inSingle  = false;
        $inDouble  = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif (!$inSingle && !$inDouble && $char === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $result .= "\n";
                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    /** @return list<string> */
    private static function splitOnSemicolons(string $sql): array
    {
        $statements = [];
        $current    = '';
        $length     = strlen($sql);
        $inSingle   = false;
        $inDouble   = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            }

            if ($char === ';' && !$inSingle && !$inDouble) {
                $statements[] = $current;
                $current      = '';
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
}
