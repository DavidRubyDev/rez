<?php

declare(strict_types=1);

namespace Rez\Tests\Handler\Resource;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceResponse;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceUseCaseInterface;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\Resource\DeleteResourceHandler;

class DeleteResourceHandlerTest extends TestCase
{
    private DeleteResourceUseCaseInterface&MockObject $useCase;
    private DeleteResourceHandler $handler;

    protected function setUp(): void
    {
        $this->useCase = $this->createMock(DeleteResourceUseCaseInterface::class);
        $this->handler = new DeleteResourceHandler($this->useCase);
    }

    public function testHandleReturnsEmptyArray(): void
    {
        $this->useCase->method('execute')->willReturn(new DeleteResourceResponse());

        $result = $this->handler->handle(['id' => ResourceId::generate()->toString()]);

        $this->assertSame([], $result);
    }

    public function testHandleDelegatesToUseCase(): void
    {
        $this->useCase->expects($this->once())->method('execute')->willReturn(new DeleteResourceResponse());

        $this->handler->handle(['id' => ResourceId::generate()->toString()]);
    }
}
