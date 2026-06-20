<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Resource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceResponse;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceUseCaseInterface;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Handler\Resource\CreateResourceHandler;

class CreateResourceHandlerTest extends TestCase
{
    private CreateResourceUseCaseInterface&MockObject $useCase;
    private CreateResourceHandler $handler;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->useCase  = $this->createMock(CreateResourceUseCaseInterface::class);
        $this->handler  = new CreateResourceHandler($this->useCase);
        $this->resource = new Resource(
            ResourceId::generate(),
            ResourceType::fromString('table'),
            'Table 1',
            4,
            ['location' => 'window'],
        );
    }

    public function testHandleReturnsSerializedResource(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new CreateResourceResponse($this->resource));

        $result = $this->handler->handle([
            'type'       => 'table',
            'name'       => 'Table 1',
            'capacity'   => 4,
            'attributes' => ['location' => 'window'],
        ]);

        $this->assertSame($this->resource->id->toString(), $result['id']);
        $this->assertSame('table', $result['type']);
        $this->assertSame('Table 1', $result['name']);
        $this->assertSame(4, $result['capacity']);
        $this->assertSame(['location' => 'window'], $result['attributes']);
    }

    public function testHandleWithoutAttributesDefaultsToEmptyArray(): void
    {
        $resource = new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 1', 4);

        $this->useCase
            ->method('execute')
            ->willReturn(new CreateResourceResponse($resource));

        $result = $this->handler->handle([
            'type'     => 'table',
            'name'     => 'Table 1',
            'capacity' => 4,
        ]);

        $this->assertSame([], $result['attributes']);
    }
}
