<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate;

use Rez\Domain\Mailer\EmailTemplateId;

final class DeleteEmailTemplateRequest
{
    public function __construct(
        public readonly EmailTemplateId $emailTemplateId,
    ) {
    }
}
