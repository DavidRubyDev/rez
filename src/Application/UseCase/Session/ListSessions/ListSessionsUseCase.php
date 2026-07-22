<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;

final class ListSessionsUseCase implements ListSessionsUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws DatabaseException
     */
    public function execute(ListSessionsRequest $request): ListSessionsResponse
    {
        try {
            $this->resourceRepository->findById($request->resourceId);
            $sessions = $this->sessionRepository->findForResource($request->resourceId, $request->from, $request->to);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list sessions.', 0, $e);
        }

        return new ListSessionsResponse($sessions);
    }
}
