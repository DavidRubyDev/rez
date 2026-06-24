<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;

final class GetResourceUseCase implements GetResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /** @throws \Rez\Domain\Exception\ResourceNotFoundException */
    public function execute(GetResourceRequest $request): GetResourceResponse
    {
        try {
            $resource = $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        return new GetResourceResponse($resource);
    }
}
