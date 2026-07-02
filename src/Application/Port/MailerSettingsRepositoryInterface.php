<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Mailer\MailerSettings;

interface MailerSettingsRepositoryInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function get(): MailerSettings;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function update(MailerSettings $settings): void;
}
