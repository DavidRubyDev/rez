<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Resource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceRequest;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceResponse;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceUseCaseInterface;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Handler\Resource\UpdateResourceHandler;

class UpdateResourceHandlerTest extends TestCase
{
    private UpdateResourceUseCaseInterface&MockObject $useCase;
    private UpdateResourceHandler $handler;
    private Resource $resource;

    protected function setUp(): void
    {
        $this->useCase  = $this->createMock(UpdateResourceUseCaseInterface::class);
        $this->handler  = new UpdateResourceHandler($this->useCase);
        $this->resource = new Resource(
            ResourceId::generate(),
            ResourceType::fromString('table'),
            'Table One',
            6,
            ['location' => 'terrace'],
        );
    }

    public function testHandleReturnsSerializedResource(): void
    {
        $this->useCase
            ->method('execute')
            ->willReturn(new UpdateResourceResponse($this->resource));

        $result = $this->handler->handle([
            'id'         => $this->resource->id->toString(),
            'name'       => 'Table One',
            'capacity'   => 6,
            'attributes' => ['location' => 'terrace'],
        ]);

        $this->assertSame($this->resource->id->toString(), $result['id']);
        $this->assertSame('table', $result['type']);
        $this->assertSame('Table One', $result['name']);
        $this->assertSame(6, $result['capacity']);
        $this->assertSame(['location' => 'terrace'], $result['attributes']);
    }

    public function testHandlePassesNullsForAbsentFields(): void
    {
        $this->useCase
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (UpdateResourceRequest $req): bool {
                return $req->name === null && $req->capacity === null && $req->attributes === null;
            }))
            ->willReturn(new UpdateResourceResponse($this->resource));

        $this->handler->handle(['id' => $this->resource->id->toString()]);
    }
}
