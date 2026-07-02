<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\GetEmailTemplate;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailTemplateRepositoryInterface;

final class GetEmailTemplateUseCase implements GetEmailTemplateUseCaseInterface
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws DatabaseException
     */
    public function execute(GetEmailTemplateRequest $request): GetEmailTemplateResponse
    {
        try {
            $template = $this->emailTemplateRepository->findById($request->emailTemplateId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load email template.', 0, $e);
        }

        return new GetEmailTemplateResponse($template);
    }
}
