<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CreateSession;

use DateTimeImmutable;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Shared\Utc;

final class CreateSessionUseCase implements CreateSessionUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \InvalidArgumentException
     * @throws DatabaseException
     */
    public function execute(CreateSessionRequest $request): CreateSessionResponse
    {
        try {
            $resource = $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        $startTime = $this->parseStartTime($request->startTime);

        $durationMinutes = $request->durationMinutes ?? $resource->defaultDurationMinutes;

        if ($durationMinutes === null) {
            throw new \InvalidArgumentException('Session duration was not provided and the resource has no default duration configured.');
        }

        $capacity = $request->capacity ?? $resource->capacity;

        $session = Session::create(
            SessionId::generate(),
            $request->resourceId,
            $startTime,
            $durationMinutes,
            $capacity,
        );

        try {
            $this->sessionRepository->save($session);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save session.', 0, $e);
        }

        return new CreateSessionResponse($session);
    }

    /** @throws \InvalidArgumentException */
    private function parseStartTime(string $value): DateTimeImmutable
    {
        $startTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, Utc::timezone());

        if ($startTime === false || $startTime->format('Y-m-d H:i') !== $value) {
            throw new \InvalidArgumentException('Invalid startTime format: expected Y-m-d H:i.');
        }

        return $startTime;
    }
}
