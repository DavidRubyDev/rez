<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\SendEmailTemplate;

use Rez\Domain\Mailer\EmailTemplateId;

final class SendEmailTemplateRequest
{
    /** @param string[] $recipients */
    public function __construct(
        public readonly EmailTemplateId $emailTemplateId,
        public readonly array $recipients,
    ) {
    }
}
