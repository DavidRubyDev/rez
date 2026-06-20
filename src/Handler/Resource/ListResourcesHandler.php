<?php

declare(strict_types=1);

namespace Rez\Handler\Resource;

use Rez\Application\UseCase\Resource\ListResources\ListResourcesRequest;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesUseCaseInterface;
use Rez\Handler\ResourceSerializer;

final class ListResourcesHandler
{
    public function __construct(
        private readonly ListResourcesUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{} $data
     * @return list<array{id: string, type: string, name: string, capacity: int, attributes: array<string, mixed>}>
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(new ListResourcesRequest());

        return array_values(array_map(
            ResourceSerializer::serialize(...),
            $response->resources->toArray(),
        ));
    }
}
