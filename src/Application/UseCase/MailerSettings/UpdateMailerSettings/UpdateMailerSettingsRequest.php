<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\UpdateMailerSettings;

final class UpdateMailerSettingsRequest
{
    public function __construct(
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
    ) {
    }
}
