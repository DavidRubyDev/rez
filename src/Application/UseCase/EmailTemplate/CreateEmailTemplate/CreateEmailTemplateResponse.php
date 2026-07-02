<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate;

use Rez\Domain\Mailer\EmailTemplate;

final class CreateEmailTemplateResponse
{
    public function __construct(
        public readonly EmailTemplate $template,
    ) {
    }
}
