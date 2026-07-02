<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate;

interface DeleteEmailTemplateUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(DeleteEmailTemplateRequest $request): DeleteEmailTemplateResponse;
}
