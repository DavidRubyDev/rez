<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Domain\Session\Session;

final class ListSessionsUseCase implements ListSessionsUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws DatabaseException
     */
    public function execute(ListSessionsRequest $request): ListSessionsResponse
    {
        try {
            foreach ($request->resourceIds as $resourceId) {
                $this->resourceRepository->findById($resourceId);
            }

            $sessions = $this->sessionRepository->findForResources(
                $request->resourceIds,
                $request->from,
                $request->to,
                $request->includeUnpublished,
            );

            $sessionIds = array_map(
                static fn (Session $session): string => $session->id->toString(),
                $sessions->toArray(),
            );

            $counted = $sessionIds === [] ? [] : $this->reservationRepository->countBookedBySessionIds($sessionIds);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list sessions.', 0, $e);
        }

        $bookedCounts = [];
        foreach ($sessionIds as $sessionId) {
            $bookedCounts[$sessionId] = $counted[$sessionId] ?? 0;
        }

        return new ListSessionsResponse($sessions, $bookedCounts);
    }
}
