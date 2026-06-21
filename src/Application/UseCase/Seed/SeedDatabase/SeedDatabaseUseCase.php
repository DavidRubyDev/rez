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
        $files = glob($request->seedsDirectory . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $this->seeder->executeFile($file);
        }

        return new SeedDatabaseResponse(count($files));
    }
}
