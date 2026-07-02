<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\GetEmailTemplate;

use Rez\Domain\Mailer\EmailTemplate;

final class GetEmailTemplateResponse
{
    public function __construct(
        public readonly EmailTemplate $template,
    ) {
    }
}
