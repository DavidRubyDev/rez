<?php

declare(strict_types=1);

namespace Rez\Application\Migration;

final class MigrationFileResolver
{
    /**
     * @param string[] $directories
     * @param string[] $appliedNames
     * @return array<string, string> migration name => absolute file path, ordered by name
     */
    public static function resolvePending(array $directories, array $appliedNames): array
    {
        $files = [];

        foreach ($directories as $directory) {
            foreach (glob($directory . '/*.sql') ?: [] as $file) {
                $files[basename($file, '.sql')] = $file;
            }
        }

        ksort($files);

        return array_diff_key($files, array_flip($appliedNames));
    }
}
