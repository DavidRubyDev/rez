<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Infrastructure\Mapper\SubscriberSourceMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlNewsletterRepository;

class MysqlNewsletterRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlNewsletterRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MysqlNewsletterRepository($this->pdo(), new SubscriberSourceMapper(), new NullLogger());
    }

    public function testSaveAndFindByEmail(): void
    {
        $subscriber = NewsletterSubscriber::create(
            NewsletterSubscriberId::generate(),
            'jan@example.com',
            'Jan Novák',
            SubscriberSource::Guest,
        );

        $this->repository->save($subscriber);
        $found = $this->repository->findByEmail('jan@example.com');

        $this->assertTrue($subscriber->id->equals($found->id));
        $this->assertSame('jan@example.com', $found->email);
        $this->assertSame('Jan Novák', $found->name);
        $this->assertSame(SubscriberSource::Guest, $found->source);
        $this->assertInstanceOf(\DateTimeImmutable::class, $found->optedInAt);
    }

    public function testFindByEmailThrowsWhenNotFound(): void
    {
        $this->expectException(NewsletterSubscriberNotFoundException::class);
        $this->repository->findByEmail('nobody@example.com');
    }

    public function testSaveIsIdempotentByEmail(): void
    {
        $id = NewsletterSubscriberId::generate();

        $this->repository->save(NewsletterSubscriber::create($id, 'dup@example.com', 'First', SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create($id, 'dup@example.com', 'Second', SubscriberSource::Registered));

        $stmt = $this->pdo()->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE email = 'dup@example.com'");
        $this->assertNotFalse($stmt);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testFindAllReturnsAllSubscribers(): void
    {
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'a@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'b@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'c@example.com', null, SubscriberSource::Registered));

        $this->assertCount(3, $this->repository->findAll());
    }

    public function testDeleteRemovesSubscriber(): void
    {
        $subscriber = NewsletterSubscriber::create(
            NewsletterSubscriberId::generate(),
            'delete@example.com',
            null,
            SubscriberSource::Guest,
        );

        $this->repository->save($subscriber);
        $this->repository->delete($subscriber->id);

        $this->expectException(NewsletterSubscriberNotFoundException::class);
        $this->repository->findByEmail('delete@example.com');
    }

    public function testFindPageWithNoParamsMatchesFindAll(): void
    {
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'a@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'b@example.com', null, SubscriberSource::Guest));

        $page = $this->repository->findPage();
        $all  = $this->repository->findAll();

        $this->assertSame(
            array_map(fn ($s) => $s->id->toString(), $all),
            array_map(fn ($s) => $s->id->toString(), $page),
        );
    }

    public function testFindPageFiltersBySearchAgainstEmailOrName(): void
    {
        $match   = NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'alice@example.com', 'Alice Wonderland', SubscriberSource::Guest);
        $noMatch = NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'bob@example.com', 'Bob Builder', SubscriberSource::Guest);

        $this->repository->save($match);
        $this->repository->save($noMatch);

        $result = $this->repository->findPage(search: 'wonderland');

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->id->equals($match->id));
    }

    public function testFindPageFiltersBySource(): void
    {
        $guest      = NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'guest@example.com', null, SubscriberSource::Guest);
        $registered = NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'reg@example.com', null, SubscriberSource::Registered);

        $this->repository->save($guest);
        $this->repository->save($registered);

        $result = $this->repository->findPage(source: SubscriberSource::Registered);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->id->equals($registered->id));
    }

    public function testFindPageDefaultSortIsOptedInAtAscending(): void
    {
        // opted_in_at is a DATETIME (second precision) — explicit, distinct timestamps avoid a
        // tie that MySQL would break arbitrarily (UUID primary keys give no insertion-order guarantee).
        $utc = new \DateTimeZone('UTC');

        $first = NewsletterSubscriber::reconstruct(
            NewsletterSubscriberId::generate(),
            'first@example.com',
            null,
            SubscriberSource::Guest,
            new \DateTimeImmutable('2024-01-01 10:00:00', $utc),
        );
        $second = NewsletterSubscriber::reconstruct(
            NewsletterSubscriberId::generate(),
            'second@example.com',
            null,
            SubscriberSource::Guest,
            new \DateTimeImmutable('2024-01-01 10:00:01', $utc),
        );

        $this->repository->save($second);
        $this->repository->save($first);

        $page = $this->repository->findPage();

        $this->assertTrue($page[0]->id->equals($first->id));
        $this->assertTrue($page[1]->id->equals($second->id));
    }

    public function testFindPageSortsAndPaginates(): void
    {
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'a@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'b@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'c@example.com', null, SubscriberSource::Guest));

        $page = $this->repository->findPage(sortBy: 'email', sortDir: 'desc', offset: 1, limit: 1);

        $this->assertCount(1, $page);
        $this->assertSame('b@example.com', $page[0]->email);
    }

    public function testCountPageMatchesFilteredCount(): void
    {
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'guest@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'reg@example.com', null, SubscriberSource::Registered));

        $this->assertSame(1, $this->repository->countPage(source: SubscriberSource::Registered));
    }

    public function testFindAllIsUnaffectedByFindPageChanges(): void
    {
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'a@example.com', null, SubscriberSource::Guest));
        $this->repository->save(NewsletterSubscriber::create(NewsletterSubscriberId::generate(), 'b@example.com', null, SubscriberSource::Guest));

        $this->assertCount(2, $this->repository->findAll());
    }
}
