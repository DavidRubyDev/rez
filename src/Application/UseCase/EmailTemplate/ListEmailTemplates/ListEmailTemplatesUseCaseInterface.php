<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\ListEmailTemplates;

interface ListEmailTemplatesUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(ListEmailTemplatesRequest $request): ListEmailTemplatesResponse;
}
