<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\CreateResource;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;

final class CreateResourceUseCase implements CreateResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws DatabaseException
     */
    public function execute(CreateResourceRequest $request): CreateResourceResponse
    {
        $resource = new Resource(
            ResourceId::generate(),
            ResourceType::fromString($request->type),
            $request->name,
            $request->capacity,
            $request->attributes,
            defaultDurationMinutes: $request->defaultDurationMinutes,
            published: $request->published,
        );

        try {
            $this->resourceRepository->save($resource);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save resource.', 0, $e);
        }

        return new CreateResourceResponse($resource);
    }
}
