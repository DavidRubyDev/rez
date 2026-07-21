<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;

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

    /**
     * @return NewsletterSubscriber[]
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findPage(
        ?string $search = null,
        ?SubscriberSource $source = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function countPage(?string $search = null, ?SubscriberSource $source = null): int;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(NewsletterSubscriber $subscriber): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(NewsletterSubscriberId $id): void;
}
