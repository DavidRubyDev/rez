<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;

interface NewsletterRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\NewsletterSubscriberNotFoundException
     */
    public function findByEmail(string $email): NewsletterSubscriber;

    /** @return NewsletterSubscriber[] */
    public function findAll(): array;

    public function save(NewsletterSubscriber $subscriber): void;

    public function delete(NewsletterSubscriberId $id): void;
}
