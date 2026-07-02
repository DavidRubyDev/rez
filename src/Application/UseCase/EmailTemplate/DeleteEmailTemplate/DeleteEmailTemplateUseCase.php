<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;

final class DeleteEmailTemplateUseCase implements DeleteEmailTemplateUseCaseInterface
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws DatabaseException
     */
    public function execute(DeleteEmailTemplateRequest $request): DeleteEmailTemplateResponse
    {
        try {
            $this->emailTemplateRepository->findById($request->emailTemplateId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load email template.', 0, $e);
        }

        try {
            $this->emailTemplateRepository->delete($request->emailTemplateId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete email template.', 0, $e);
        }

        return new DeleteEmailTemplateResponse();
    }
}
