<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\SendEmailTemplate;

final class SendEmailTemplateResponse
{
    public function __construct(
        public readonly int $sent,
    ) {
    }
}
