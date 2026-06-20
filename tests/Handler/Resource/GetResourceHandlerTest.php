<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Resource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Resource\GetResource\GetResourceResponse;
use Rez\Application\UseCase\Resource\GetResource\GetResourceUseCaseInterface;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Handler\Resource\GetResourceHandler;

class GetResourceHandlerTest extends TestCase
{
    private GetResourceUseCaseInterface&MockObject $useCase;
    private GetResourceHandler $handler;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->useCase  = $this->createMock(GetResourceUseCaseInterface::class);
        $this->handler  = new GetResourceHandler($this->useCase);
        $this->resource = new Resource(
            ResourceId::generate(),
            ResourceType::fromString('room'),
            'Conference Room A',
            10,
        );
    }

    public function testHandleReturnsSerializedResource(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new GetResourceResponse($this->resource));

        $result = $this->handler->handle(['id' => $this->resource->id->toString()]);

        $this->assertSame($this->resource->id->toString(), $result['id']);
        $this->assertSame('room', $result['type']);
        $this->assertSame('Conference Room A', $result['name']);
        $this->assertSame(10, $result['capacity']);
    }
}
