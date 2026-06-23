<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Seed\SeedDatabase;

use Rez\Application\Port\DatabaseSeederInterface;

final class SeedDatabaseUseCase implements SeedDatabaseUseCaseInterface
{
    public function __construct(
        private readonly DatabaseSeederInterface $seeder,
    ) {
    }

    public function execute(SeedDatabaseRequest $request): SeedDatabaseResponse
    {
        $total = 0;

        foreach ($request->seedsDirectories as $directory) {
            $files = glob($directory . '/*.sql') ?: [];
            sort($files);

            foreach ($files as $file) {
                $this->seeder->executeFile($file);
                $total++;
            }
        }

        return new SeedDatabaseResponse($total);
    }
}
