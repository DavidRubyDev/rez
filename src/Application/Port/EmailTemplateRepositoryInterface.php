<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

interface EmailTemplateRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailTemplateNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findById(EmailTemplateId $id): EmailTemplate;

    /**
     * @return EmailTemplate[]
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findAll(): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(EmailTemplate $template): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(EmailTemplateId $id): void;
}
