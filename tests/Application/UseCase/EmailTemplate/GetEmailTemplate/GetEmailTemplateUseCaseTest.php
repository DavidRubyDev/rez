<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\EmailTemplate\GetEmailTemplate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\UseCase\EmailTemplate\GetEmailTemplate\GetEmailTemplateRequest;
use Rez\Application\UseCase\EmailTemplate\GetEmailTemplate\GetEmailTemplateUseCase;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

class GetEmailTemplateUseCaseTest extends TestCase
{
    private EmailTemplateRepositoryInterface&MockObject $repository;
    private GetEmailTemplateUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $this->useCase    = new GetEmailTemplateUseCase($this->repository);
    }

    public function testReturnsTemplateWhenFound(): void
    {
        $template = EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');
        $this->repository->method('findById')->willReturn($template);

        $response = $this->useCase->execute(new GetEmailTemplateRequest($template->id));

        $this->assertSame($template, $response->template);
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new EmailTemplateNotFoundException());

        $this->expectException(EmailTemplateNotFoundException::class);

        $this->useCase->execute(new GetEmailTemplateRequest(EmailTemplateId::generate()));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load email template.');

        $this->useCase->execute(new GetEmailTemplateRequest(EmailTemplateId::generate()));
    }
}
