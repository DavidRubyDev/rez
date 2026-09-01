<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\SendEmailTemplate;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Shared\Email;
use Rez\Domain\Shared\UnsubscribeToken;

final class SendEmailTemplateUseCase implements SendEmailTemplateUseCaseInterface
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly NewsletterRepositoryInterface $newsletterRepository,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws \InvalidArgumentException
     * @throws DatabaseException
     */
    public function execute(SendEmailTemplateRequest $request): SendEmailTemplateResponse
    {
        if ($request->recipients === []) {
            throw new \InvalidArgumentException('recipients must not be empty.');
        }

        foreach ($request->recipients as $recipient) {
            if (!Email::isValid($recipient)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $recipient));
            }
        }

        try {
            $template = $this->emailTemplateRepository->findById($request->emailTemplateId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load email template.', 0, $e);
        }

        $sent = 0;

        foreach ($request->recipients as $recipient) {
            try {
                $unsubscribeToken = null;
                try {
                    $this->newsletterRepository->findByEmail($recipient);
                    $unsubscribeToken = UnsubscribeToken::generate($recipient, $this->usersConfig->cancellationSecret);
                } catch (NewsletterSubscriberNotFoundException) {
                    // Not a subscriber — send without an unsubscribe link.
                }

                $this->mailer->sendCustomEmail($recipient, $template->subject, $template->html, $unsubscribeToken);
                $sent++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send custom email to recipient', [
                    'emailTemplateId' => $template->id->toString(),
                    'email'           => $recipient,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return new SendEmailTemplateResponse($sent);
    }
}
