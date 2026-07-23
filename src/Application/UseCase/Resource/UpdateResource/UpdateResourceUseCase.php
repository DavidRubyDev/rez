<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\UpdateResource;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Resource\Resource;

final class UpdateResourceUseCase implements UpdateResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws DatabaseException
     */
    public function execute(UpdateResourceRequest $request): UpdateResourceResponse
    {
        try {
            $existing = $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        $updated = new Resource(
            $existing->id,
            $existing->type,
            $request->name ?? $existing->name,
            $request->capacity ?? $existing->capacity,
            $request->attributes ?? $existing->attributes,
            $existing->active,
            $request->defaultDurationMinutes ?? $existing->defaultDurationMinutes,
        );

        try {
            $this->resourceRepository->save($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save resource.', 0, $e);
        }

        return new UpdateResourceResponse($updated);
    }
}
