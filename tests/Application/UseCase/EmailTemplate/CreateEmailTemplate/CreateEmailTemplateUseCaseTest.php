<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\EmailTemplate\CreateEmailTemplate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate\CreateEmailTemplateRequest;
use Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate\CreateEmailTemplateUseCase;

class CreateEmailTemplateUseCaseTest extends TestCase
{
    private EmailTemplateRepositoryInterface&MockObject $repository;
    private CreateEmailTemplateUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $this->useCase    = new CreateEmailTemplateUseCase($this->repository);
    }

    public function testCreatesAndSavesTemplate(): void
    {
        $this->repository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new CreateEmailTemplateRequest('Welcome', '<p>Hello</p>'));

        $this->assertSame('Welcome', $response->template->subject);
        $this->assertSame('<p>Hello</p>', $response->template->html);
    }

    public function testEmptySubjectThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new CreateEmailTemplateRequest('', '<p>Hello</p>'));
    }

    public function testEmptyHtmlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new CreateEmailTemplateRequest('Welcome', ''));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('save')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save email template.');

        $this->useCase->execute(new CreateEmailTemplateRequest('Welcome', '<p>Hello</p>'));
    }
}
