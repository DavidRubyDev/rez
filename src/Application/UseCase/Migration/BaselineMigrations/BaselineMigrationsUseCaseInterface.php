<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\BaselineMigrations;

interface BaselineMigrationsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(BaselineMigrationsRequest $request): BaselineMigrationsResponse;
}
