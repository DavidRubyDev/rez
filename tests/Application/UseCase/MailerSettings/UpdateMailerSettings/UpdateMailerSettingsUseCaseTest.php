<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\MailerSettings\UpdateMailerSettings;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerSettingsRepositoryInterface;
use Rez\Application\UseCase\MailerSettings\UpdateMailerSettings\UpdateMailerSettingsRequest;
use Rez\Application\UseCase\MailerSettings\UpdateMailerSettings\UpdateMailerSettingsUseCase;
use Rez\Domain\Mailer\MailerSettings;

class UpdateMailerSettingsUseCaseTest extends TestCase
{
    private MailerSettingsRepositoryInterface&MockObject $repository;
    private UpdateMailerSettingsUseCase $useCase;
    private MailerSettings $existing;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MailerSettingsRepositoryInterface::class);
        $this->useCase    = new UpdateMailerSettingsUseCase($this->repository);
        $this->existing   = new MailerSettings('noreply@example.com', 'Rez');
    }

    public function testRepositoryGetDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('get')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load mailer settings.');

        $this->useCase->execute(new UpdateMailerSettingsRequest(fromName: 'Studio'));
    }

    public function testRepositoryUpdateDatabaseExceptionPropagates(): void
    {
        $this->repository->method('get')->willReturn($this->existing);
        $this->repository
            ->method('update')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save mailer settings.');

        $this->useCase->execute(new UpdateMailerSettingsRequest(fromName: 'Studio'));
    }

    public function testUpdatesOnlyFromAddress(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateMailerSettingsRequest(fromAddress: 'info@studio.cz'));

        $this->assertSame('info@studio.cz', $response->settings->fromAddress);
        $this->assertSame('Rez', $response->settings->fromName);
    }

    public function testUpdatesOnlyFromName(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateMailerSettingsRequest(fromName: 'Studio'));

        $this->assertSame('noreply@example.com', $response->settings->fromAddress);
        $this->assertSame('Studio', $response->settings->fromName);
    }

    public function testUpdatesBothFieldsTogether(): void
    {
        $this->repository->method('get')->willReturn($this->existing);
        $this->repository->expects($this->once())->method('update');

        $response = $this->useCase->execute(new UpdateMailerSettingsRequest(
            fromAddress: 'info@studio.cz',
            fromName: 'Studio',
        ));

        $this->assertSame('info@studio.cz', $response->settings->fromAddress);
        $this->assertSame('Studio', $response->settings->fromName);
    }

    public function testNoFieldsProvidedKeepsExistingValues(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateMailerSettingsRequest());

        $this->assertSame('noreply@example.com', $response->settings->fromAddress);
        $this->assertSame('Rez', $response->settings->fromName);
    }

    public function testInvalidFromAddressThrows(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new UpdateMailerSettingsRequest(fromAddress: 'not-an-email'));
    }

    public function testEmptyFromNameThrows(): void
    {
        $this->repository->method('get')->willReturn($this->existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new UpdateMailerSettingsRequest(fromName: ''));
    }
}
