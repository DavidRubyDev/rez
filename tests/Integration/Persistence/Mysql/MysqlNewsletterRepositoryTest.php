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
}
