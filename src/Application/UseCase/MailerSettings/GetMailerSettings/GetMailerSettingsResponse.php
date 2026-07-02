<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\GetMailerSettings;

use Rez\Domain\Mailer\MailerSettings;

final class GetMailerSettingsResponse
{
    public function __construct(
        public readonly MailerSettings $settings,
    ) {
    }
}
