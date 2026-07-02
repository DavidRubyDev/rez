<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\EmailTemplate\UpdateEmailTemplate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate\UpdateEmailTemplateRequest;
use Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate\UpdateEmailTemplateUseCase;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

class UpdateEmailTemplateUseCaseTest extends TestCase
{
    private EmailTemplateRepositoryInterface&MockObject $repository;
    private UpdateEmailTemplateUseCase $useCase;
    private EmailTemplate $existing;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $this->useCase    = new UpdateEmailTemplateUseCase($this->repository);
        $this->existing   = EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');
    }

    public function testUpdatesOnlySubject(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, subject: 'New Subject'));

        $this->assertSame('New Subject', $response->template->subject);
        $this->assertSame('<p>Hello</p>', $response->template->html);
    }

    public function testUpdatesOnlyHtml(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, html: '<p>Updated</p>'));

        $this->assertSame('Welcome', $response->template->subject);
        $this->assertSame('<p>Updated</p>', $response->template->html);
    }

    public function testUpdatesBothFieldsTogether(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);
        $this->repository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new UpdateEmailTemplateRequest(
            $this->existing->id,
            subject: 'New Subject',
            html: '<p>Updated</p>',
        ));

        $this->assertSame('New Subject', $response->template->subject);
        $this->assertSame('<p>Updated</p>', $response->template->html);
    }

    public function testPreservesIdAndCreatedAt(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, subject: 'New Subject'));

        $this->assertTrue($this->existing->id->equals($response->template->id));
        $this->assertSame($this->existing->createdAt, $response->template->createdAt);
    }

    public function testNoFieldsProvidedKeepsExistingValues(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $response = $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id));

        $this->assertSame('Welcome', $response->template->subject);
        $this->assertSame('<p>Hello</p>', $response->template->html);
    }

    public function testEmptySubjectThrows(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, subject: ''));
    }

    public function testEmptyHtmlThrows(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, html: ''));
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new EmailTemplateNotFoundException());

        $this->expectException(EmailTemplateNotFoundException::class);

        $this->useCase->execute(new UpdateEmailTemplateRequest(EmailTemplateId::generate(), subject: 'New Subject'));
    }

    public function testRepositoryFindDatabaseExceptionPropagates(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load email template.');

        $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, subject: 'New Subject'));
    }

    public function testRepositorySaveDatabaseExceptionPropagates(): void
    {
        $this->repository->method('findById')->willReturn($this->existing);
        $this->repository
            ->method('save')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save email template.');

        $this->useCase->execute(new UpdateEmailTemplateRequest($this->existing->id, subject: 'New Subject'));
    }
}
