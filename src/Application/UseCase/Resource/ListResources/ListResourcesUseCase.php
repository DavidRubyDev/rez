<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Validation\ListParamsValidator;

final class ListResourcesUseCase implements ListResourcesUseCaseInterface
{
    private const SORTABLE = ['type', 'name', 'capacity'];

    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws DatabaseException
     * @throws \InvalidArgumentException
     */
    public function execute(ListResourcesRequest $request): ListResourcesResponse
    {
        ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

        try {
            $resources = $this->resourceRepository->findPage($request->offset, $request->limit, $request->sortBy, $request->sortDir);
            $total     = $this->resourceRepository->countPage();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list resources.', 0, $e);
        }

        return new ListResourcesResponse($resources, $total);
    }
}
