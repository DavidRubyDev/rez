<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\DeleteResource;

use Rez\Application\Port\ResourceRepositoryInterface;

final class DeleteResourceUseCase implements DeleteResourceUseCaseInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(DeleteResourceRequest $request): DeleteResourceResponse
    {
        $this->resourceRepository->findById($request->resourceId);
        $this->resourceRepository->delete($request->resourceId);

        return new DeleteResourceResponse();
    }
}
