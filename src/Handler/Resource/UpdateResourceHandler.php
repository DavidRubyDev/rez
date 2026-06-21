<?php

declare(strict_types=1);

namespace Rez\Handler\Resource;

use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceRequest;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceUseCaseInterface;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\ResourceSerializer;

final class UpdateResourceHandler
{
    public function __construct(
        private readonly UpdateResourceUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{id: string, name?: string, capacity?: int, attributes?: array<string, mixed>} $data
     * @return array{id: string, type: string, name: string, capacity: int, attributes: array<string, mixed>}
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(new UpdateResourceRequest(
            ResourceId::fromString($data['id']),
            $data['name'] ?? null,
            $data['capacity'] ?? null,
            $data['attributes'] ?? null,
        ));

        return ResourceSerializer::serialize($response->resource);
    }
}
