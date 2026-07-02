<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\EmailTemplate\DeleteEmailTemplate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate\DeleteEmailTemplateRequest;
use Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate\DeleteEmailTemplateUseCase;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

class DeleteEmailTemplateUseCaseTest extends TestCase
{
    private EmailTemplateRepositoryInterface&MockObject $repository;
    private DeleteEmailTemplateUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $this->useCase    = new DeleteEmailTemplateUseCase($this->repository);
    }

    public function testDeletesTemplate(): void
    {
        $id = EmailTemplateId::generate();
        $this->repository->method('findById')->willReturn(
            EmailTemplate::create($id, 'Welcome', '<p>Hello</p>')
        );
        $this->repository->expects($this->once())->method('delete')->with($id);

        $this->useCase->execute(new DeleteEmailTemplateRequest($id));
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new EmailTemplateNotFoundException());
        $this->repository->expects($this->never())->method('delete');

        $this->expectException(EmailTemplateNotFoundException::class);

        $this->useCase->execute(new DeleteEmailTemplateRequest(EmailTemplateId::generate()));
    }

    public function testRepositoryFindDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load email template.');

        $this->useCase->execute(new DeleteEmailTemplateRequest(EmailTemplateId::generate()));
    }

    public function testRepositoryDeleteDatabaseExceptionPropagates(): void
    {
        $id = EmailTemplateId::generate();
        $this->repository->method('findById')->willReturn(
            EmailTemplate::create($id, 'Welcome', '<p>Hello</p>')
        );
        $this->repository
            ->method('delete')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to delete email template.');

        $this->useCase->execute(new DeleteEmailTemplateRequest($id));
    }
}
