<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\GetEmailTemplate;

interface GetEmailTemplateUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(GetEmailTemplateRequest $request): GetEmailTemplateResponse;
}
