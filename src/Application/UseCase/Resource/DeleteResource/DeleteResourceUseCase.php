<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\DeleteResource;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;

final class DeleteResourceUseCase implements DeleteResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /** @throws \Rez\Domain\Exception\ResourceNotFoundException */
    public function execute(DeleteResourceRequest $request): DeleteResourceResponse
    {
        try {
            $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        try {
            $this->resourceRepository->delete($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete resource.', 0, $e);
        }

        return new DeleteResourceResponse();
    }
}
