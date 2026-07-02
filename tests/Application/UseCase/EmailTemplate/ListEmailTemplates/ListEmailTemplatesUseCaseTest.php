<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\EmailTemplate\ListEmailTemplates;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\UseCase\EmailTemplate\ListEmailTemplates\ListEmailTemplatesRequest;
use Rez\Application\UseCase\EmailTemplate\ListEmailTemplates\ListEmailTemplatesUseCase;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

class ListEmailTemplatesUseCaseTest extends TestCase
{
    private EmailTemplateRepositoryInterface&MockObject $repository;
    private ListEmailTemplatesUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $this->useCase    = new ListEmailTemplatesUseCase($this->repository);
    }

    public function testReturnsTemplatesFromRepository(): void
    {
        $templates = [
            EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>'),
            EmailTemplate::create(EmailTemplateId::generate(), 'Goodbye', '<p>Bye</p>'),
        ];
        $this->repository->method('findAll')->willReturn($templates);

        $response = $this->useCase->execute(new ListEmailTemplatesRequest());

        $this->assertSame($templates, $response->templates);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findAll')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list email templates.');

        $this->useCase->execute(new ListEmailTemplatesRequest());
    }
}
