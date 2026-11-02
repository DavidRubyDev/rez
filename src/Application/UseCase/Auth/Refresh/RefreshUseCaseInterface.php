<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Refresh;

interface RefreshUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(RefreshRequest $request): RefreshResponse;
}
