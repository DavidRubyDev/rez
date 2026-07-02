<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\EmailTemplate\ListEmailTemplates;

use Rez\Domain\Mailer\EmailTemplate;

final class ListEmailTemplatesResponse
{
    /** @param EmailTemplate[] $templates */
    public function __construct(
        public readonly array $templates,
    ) {
    }
}
