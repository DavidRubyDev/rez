<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate;

interface CreateEmailTemplateUseCaseInterface
{
    /**
     * @throws \InvalidArgumentException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(CreateEmailTemplateRequest $request): CreateEmailTemplateResponse;
}
