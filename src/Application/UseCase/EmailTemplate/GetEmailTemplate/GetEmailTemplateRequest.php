<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\GetEmailTemplate;

use Rez\Domain\Mailer\EmailTemplateId;

final class GetEmailTemplateRequest
{
    public function __construct(
        public readonly EmailTemplateId $emailTemplateId,
    ) {
    }
}
