<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\GetMailerSettings;

interface GetMailerSettingsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(GetMailerSettingsRequest $request): GetMailerSettingsResponse;
}
