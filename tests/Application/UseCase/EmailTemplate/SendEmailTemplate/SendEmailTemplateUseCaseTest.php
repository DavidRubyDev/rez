<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\EmailTemplate\SendEmailTemplate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\UseCase\EmailTemplate\SendEmailTemplate\SendEmailTemplateRequest;
use Rez\Application\UseCase\EmailTemplate\SendEmailTemplate\SendEmailTemplateUseCase;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

class SendEmailTemplateUseCaseTest extends TestCase
{
    private EmailTemplateRepositoryInterface&MockObject $repository;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private SendEmailTemplateUseCase $useCase;
    private EmailTemplate $template;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $this->mailer      = $this->createMock(MailerInterface::class);
        $this->logger      = $this->createMock(LoggerInterface::class);
        $this->useCase     = new SendEmailTemplateUseCase($this->repository, $this->mailer, $this->logger);

        $this->template = EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');
        $this->repository->method('findById')->willReturn($this->template);
    }

    public function testSendsToEachRecipientAndReturnsSentCount(): void
    {
        $this->mailer->expects($this->exactly(2))
            ->method('sendCustomEmail')
            ->with($this->isType('string'), 'Welcome', '<p>Hello</p>');

        $response = $this->useCase->execute(new SendEmailTemplateRequest(
            $this->template->id,
            ['a@example.com', 'b@example.com'],
        ));

        $this->assertSame(2, $response->sent);
    }

    public function testMissingTemplateThrowsEmailTemplateNotFoundException(): void
    {
        $repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $repository->method('findById')->willThrowException(new EmailTemplateNotFoundException());

        $useCase = new SendEmailTemplateUseCase($repository, $this->mailer, $this->logger);

        $this->expectException(EmailTemplateNotFoundException::class);

        $useCase->execute(new SendEmailTemplateRequest(EmailTemplateId::generate(), ['a@example.com']));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $repository = $this->createMock(EmailTemplateRepositoryInterface::class);
        $repository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $useCase = new SendEmailTemplateUseCase($repository, $this->mailer, $this->logger);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load email template.');

        $useCase->execute(new SendEmailTemplateRequest(EmailTemplateId::generate(), ['a@example.com']));
    }

    public function testEmptyRecipientsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new SendEmailTemplateRequest($this->template->id, []));
    }

    public function testInvalidRecipientEmailThrows(): void
    {
        $this->mailer->expects($this->never())->method('sendCustomEmail');

        $this->expectException(\InvalidArgumentException::class);

        $this->useCase->execute(new SendEmailTemplateRequest($this->template->id, ['not-an-email']));
    }

    public function testPerRecipientMailerFailureIsLoggedAndSkippedNotThrown(): void
    {
        $this->mailer->method('sendCustomEmail')
            ->willReturnCallback(function (string $email): void {
                if ($email === 'bad@example.com') {
                    throw new \RuntimeException('SMTP error');
                }
            });

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send'),
                $this->arrayHasKey('email'),
            );

        $response = $this->useCase->execute(new SendEmailTemplateRequest(
            $this->template->id,
            ['good@example.com', 'bad@example.com'],
        ));

        $this->assertSame(1, $response->sent);
    }
}
