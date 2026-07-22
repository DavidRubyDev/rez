<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\GetSession;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\SessionRepositoryInterface;

final class GetSessionUseCase implements GetSessionUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws DatabaseException
     */
    public function execute(GetSessionRequest $request): GetSessionResponse
    {
        try {
            $session = $this->sessionRepository->findById($request->sessionId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load session.', 0, $e);
        }

        return new GetSessionResponse($session);
    }
}
