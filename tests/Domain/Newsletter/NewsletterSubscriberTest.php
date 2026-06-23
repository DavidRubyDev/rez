<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Newsletter;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;

class NewsletterSubscriberTest extends TestCase
{
    public function testCreateStoresAllValues(): void
    {
        $id         = NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $subscriber = NewsletterSubscriber::create($id, 'jan@example.com', 'Jan Novák', SubscriberSource::Guest);

        $this->assertSame($id, $subscriber->getId());
        $this->assertSame('jan@example.com', $subscriber->getEmail());
        $this->assertSame('Jan Novák', $subscriber->getName());
        $this->assertSame(SubscriberSource::Guest, $subscriber->getSource());
    }

    public function testInvalidEmailThrowsInvalidArgumentException(): void
    {
        $id = NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');

        $this->expectException(\InvalidArgumentException::class);
        NewsletterSubscriber::create($id, 'not-an-email', null, SubscriberSource::Guest);
    }

    public function testNullNameIsAccepted(): void
    {
        $id         = NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $subscriber = NewsletterSubscriber::create($id, 'jan@example.com', null, SubscriberSource::Guest);

        $this->assertNull($subscriber->getName());
    }

    public function testOptedInAtIsApproximatelyUtcNow(): void
    {
        $before     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $id         = NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $subscriber = NewsletterSubscriber::create($id, 'jan@example.com', null, SubscriberSource::Guest);
        $after      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $subscriber->getOptedInAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $subscriber->getOptedInAt()->getTimestamp());
    }

    public function testGuestSourceStoredAndReturned(): void
    {
        $id         = NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $subscriber = NewsletterSubscriber::create($id, 'jan@example.com', null, SubscriberSource::Guest);

        $this->assertSame(SubscriberSource::Guest, $subscriber->getSource());
    }

    public function testRegisteredSourceStoredAndReturned(): void
    {
        $id         = NewsletterSubscriberId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $subscriber = NewsletterSubscriber::create($id, 'jan@example.com', null, SubscriberSource::Registered);

        $this->assertSame(SubscriberSource::Registered, $subscriber->getSource());
    }
}
