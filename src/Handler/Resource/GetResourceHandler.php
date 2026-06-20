<?php

declare(strict_types=1);

namespace Rez\Handler\Resource;

use Rez\Application\UseCase\Resource\GetResource\GetResourceRequest;
use Rez\Application\UseCase\Resource\GetResource\GetResourceUseCaseInterface;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\ResourceSerializer;

final class GetResourceHandler
{
    public function __construct(
        private readonly GetResourceUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{id: string} $data
     * @return array{id: string, type: string, name: string, capacity: int, attributes: array<string, mixed>}
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(
            new GetResourceRequest(ResourceId::fromString($data['id'])),
        );

        return ResourceSerializer::serialize($response->resource);
    }
}
