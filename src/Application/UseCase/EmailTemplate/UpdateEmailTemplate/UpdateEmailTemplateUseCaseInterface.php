<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate;

interface UpdateEmailTemplateUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(UpdateEmailTemplateRequest $request): UpdateEmailTemplateResponse;
}
