<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;

final class ListResourcesUseCase implements ListResourcesUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    public function execute(ListResourcesRequest $request): ListResourcesResponse
    {
        try {
            $resources = $this->resourceRepository->findAll();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list resources.', 0, $e);
        }

        return new ListResourcesResponse($resources);
    }
}
