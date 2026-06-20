<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Resource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesResponse;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesUseCaseInterface;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceCollection;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Handler\Resource\ListResourcesHandler;

class ListResourcesHandlerTest extends TestCase
{
    private ListResourcesUseCaseInterface&MockObject $useCase;
    private ListResourcesHandler $handler;

    protected function setUp(): void
    {
        $this->useCase = $this->createMock(ListResourcesUseCaseInterface::class);
        $this->handler = new ListResourcesHandler($this->useCase);
    }

    public function testHandleReturnsSerializedResources(): void
    {
        $collection = ResourceCollection::fromArray([
            new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 1', 4),
            new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 2', 2),
        ]);

        $this->useCase
            ->method('execute')
            ->willReturn(new ListResourcesResponse($collection));

        $result = $this->handler->handle([]);

        $this->assertCount(2, $result);
        $this->assertSame('Table 1', $result[0]['name']);
        $this->assertSame('Table 2', $result[1]['name']);
        $this->assertSame('table', $result[0]['type']);
        $this->assertSame(4, $result[0]['capacity']);
    }

    public function testHandleEmptyCollectionReturnsEmptyArray(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new ListResourcesResponse(ResourceCollection::empty()));

        $result = $this->handler->handle([]);

        $this->assertSame([], $result);
    }
}
