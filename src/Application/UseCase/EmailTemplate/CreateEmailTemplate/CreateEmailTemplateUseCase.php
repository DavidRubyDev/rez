<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

final class CreateEmailTemplateUseCase implements CreateEmailTemplateUseCaseInterface
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws DatabaseException
     */
    public function execute(CreateEmailTemplateRequest $request): CreateEmailTemplateResponse
    {
        $template = EmailTemplate::create(
            EmailTemplateId::generate(),
            $request->subject,
            $request->html,
        );

        try {
            $this->emailTemplateRepository->save($template);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save email template.', 0, $e);
        }

        return new CreateEmailTemplateResponse($template);
    }
}
