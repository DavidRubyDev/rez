<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\MailerSettings\GetMailerSettings;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerSettingsRepositoryInterface;
use Rez\Application\UseCase\MailerSettings\GetMailerSettings\GetMailerSettingsRequest;
use Rez\Application\UseCase\MailerSettings\GetMailerSettings\GetMailerSettingsUseCase;
use Rez\Domain\Mailer\MailerSettings;

class GetMailerSettingsUseCaseTest extends TestCase
{
    private MailerSettingsRepositoryInterface&MockObject $repository;
    private GetMailerSettingsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MailerSettingsRepositoryInterface::class);
        $this->useCase    = new GetMailerSettingsUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('get')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load mailer settings.');

        $this->useCase->execute(new GetMailerSettingsRequest());
    }

    public function testReturnsSettingsFromRepositoryUnchanged(): void
    {
        $settings = new MailerSettings('info@studio.cz', 'Studio');
        $this->repository->method('get')->willReturn($settings);

        $response = $this->useCase->execute(new GetMailerSettingsRequest());

        $this->assertSame($settings, $response->settings);
    }
}
