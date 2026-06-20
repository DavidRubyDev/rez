<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

use Rez\Application\Port\ResourceRepositoryInterface;

final class ListResourcesUseCase implements ListResourcesUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    public function execute(ListResourcesRequest $request): ListResourcesResponse
    {
        return new ListResourcesResponse($this->resourceRepository->findAll());
    }
}
