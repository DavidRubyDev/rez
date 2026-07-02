<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate;

use Rez\Domain\Mailer\EmailTemplate;

final class UpdateEmailTemplateResponse
{
    public function __construct(
        public readonly EmailTemplate $template,
    ) {
    }
}
