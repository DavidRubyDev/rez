<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\BaselineMigrations;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Migration\MigrationFileResolver;
use Rez\Application\Port\MigrationRepositoryInterface;

final class BaselineMigrationsUseCase implements BaselineMigrationsUseCaseInterface
{
    public function __construct(
        private readonly MigrationRepositoryInterface $repository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(BaselineMigrationsRequest $request): BaselineMigrationsResponse
    {
        return $this->repository->withLock(function () use ($request): BaselineMigrationsResponse {
            $this->repository->ensureMigrationsTable();

            $pending = MigrationFileResolver::resolvePending(
                $request->migrationsDirectories,
                $this->repository->appliedMigrationNames(),
            );

            $baselined = [];

            foreach (array_keys($pending) as $name) {
                $this->repository->markMigrationApplied($name);
                $baselined[] = $name;
            }

            return new BaselineMigrationsResponse($baselined);
        });
    }
}
