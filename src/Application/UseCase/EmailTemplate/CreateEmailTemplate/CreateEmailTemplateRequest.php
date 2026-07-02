<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate;

final class CreateEmailTemplateRequest
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
    ) {
    }
}
