<?php

declare(strict_types=1);

namespace Rez\Handler\Resource;

use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceRequest;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceUseCaseInterface;
use Rez\Domain\Resource\ResourceId;

final class DeleteResourceHandler
{
    public function __construct(
        private readonly DeleteResourceUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{id: string} $data
     * @return array{}
     */
    public function handle(array $data): array
    {
        $this->useCase->execute(new DeleteResourceRequest(
            ResourceId::fromString($data['id']),
        ));

        return [];
    }
}
