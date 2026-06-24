<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

use Rez\Application\Port\ResourceRepositoryInterface;

final class GetResourceUseCase implements GetResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(GetResourceRequest $request): GetResourceResponse
    {
        return new GetResourceResponse(
            $this->resourceRepository->findById($request->resourceId),
        );
    }
}
