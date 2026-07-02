<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\UpdateMailerSettings;

use Rez\Domain\Mailer\MailerSettings;

final class UpdateMailerSettingsResponse
{
    public function __construct(
        public readonly MailerSettings $settings,
    ) {
    }
}
