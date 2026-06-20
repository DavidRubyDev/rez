<?php

declare(strict_types=1);

namespace Rez\Handler\Resource;

use Rez\Application\UseCase\Resource\CreateResource\CreateResourceRequest;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceUseCaseInterface;
use Rez\Handler\ResourceSerializer;

final class CreateResourceHandler
{
    public function __construct(
        private readonly CreateResourceUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{type: string, name: string, capacity: int, attributes?: array<string, mixed>} $data
     * @return array{id: string, type: string, name: string, capacity: int, attributes: array<string, mixed>}
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(new CreateResourceRequest(
            $data['type'],
            $data['name'],
            $data['capacity'],
            $data['attributes'] ?? [],
        ));

        return ResourceSerializer::serialize($response->resource);
    }
}
