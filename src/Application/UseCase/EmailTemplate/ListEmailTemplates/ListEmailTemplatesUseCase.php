<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\ListEmailTemplates;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;

final class ListEmailTemplatesUseCase implements ListEmailTemplatesUseCaseInterface
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(ListEmailTemplatesRequest $request): ListEmailTemplatesResponse
    {
        try {
            $templates = $this->emailTemplateRepository->findAll();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list email templates.', 0, $e);
        }

        return new ListEmailTemplatesResponse($templates);
    }
}
