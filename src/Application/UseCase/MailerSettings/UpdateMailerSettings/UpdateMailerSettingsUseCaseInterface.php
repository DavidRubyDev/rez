<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\UpdateMailerSettings;

interface UpdateMailerSettingsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(UpdateMailerSettingsRequest $request): UpdateMailerSettingsResponse;
}
