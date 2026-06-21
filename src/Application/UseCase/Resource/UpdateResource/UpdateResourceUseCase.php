<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\UpdateResource;

use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Resource\Resource;

final class UpdateResourceUseCase implements UpdateResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    public function execute(UpdateResourceRequest $request): UpdateResourceResponse
    {
        $existing = $this->resourceRepository->findById($request->resourceId);

        $updated = new Resource(
            $existing->id,
            $existing->type,
            $request->name ?? $existing->name,
            $request->capacity ?? $existing->capacity,
            $request->attributes ?? $existing->attributes,
        );

        $this->resourceRepository->save($updated);

        return new UpdateResourceResponse($updated);
    }
}
