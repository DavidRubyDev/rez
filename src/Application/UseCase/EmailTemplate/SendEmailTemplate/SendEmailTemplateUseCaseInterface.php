<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\SendEmailTemplate;

interface SendEmailTemplateUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws \InvalidArgumentException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SendEmailTemplateRequest $request): SendEmailTemplateResponse;
}
