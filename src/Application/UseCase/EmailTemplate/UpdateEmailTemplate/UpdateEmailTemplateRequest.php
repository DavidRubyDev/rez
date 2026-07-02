<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate;

use Rez\Domain\Mailer\EmailTemplateId;

final class UpdateEmailTemplateRequest
{
    public function __construct(
        public readonly EmailTemplateId $emailTemplateId,
        public readonly ?string $subject = null,
        public readonly ?string $html = null,
    ) {
    }
}
