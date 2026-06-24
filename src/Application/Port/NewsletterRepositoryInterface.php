<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;

interface NewsletterRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\NewsletterSubscriberNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findByEmail(string $email): NewsletterSubscriber;

    /**
     * @return NewsletterSubscriber[]
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findAll(): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(NewsletterSubscriber $subscriber): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(NewsletterSubscriberId $id): void;
}
