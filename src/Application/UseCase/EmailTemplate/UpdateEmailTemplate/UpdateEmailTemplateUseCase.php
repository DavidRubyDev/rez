<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;

final class UpdateEmailTemplateUseCase implements UpdateEmailTemplateUseCaseInterface
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws DatabaseException
     */
    public function execute(UpdateEmailTemplateRequest $request): UpdateEmailTemplateResponse
    {
        try {
            $existing = $this->emailTemplateRepository->findById($request->emailTemplateId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load email template.', 0, $e);
        }

        $updated = $existing->withContent(
            $request->subject ?? $existing->subject,
            $request->html ?? $existing->html,
        );

        try {
            $this->emailTemplateRepository->save($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save email template.', 0, $e);
        }

        return new UpdateEmailTemplateResponse($updated);
    }
}
