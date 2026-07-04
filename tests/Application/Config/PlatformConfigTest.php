<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\CreditsConfig;
use Rez\Application\Config\MailerConfig;
use Rez\Application\Config\PaymentsConfig;
use Rez\Application\Config\PlatformConfig;
use Rez\Application\Config\SubscriptionsConfig;
use Rez\Application\Config\UsersConfig;

class PlatformConfigTest extends TestCase
{
    private MailerConfig $mailer;
    private PaymentsConfig $payments;
    private UsersConfig $users;
    private CreditsConfig $credits;
    private SubscriptionsConfig $subscriptions;

    protected function setUp(): void
    {
        $this->mailer        = new MailerConfig();
        $this->payments      = new PaymentsConfig('CZK', 'whsec_test');
        $this->users         = new UsersConfig('super-secret-jwt', 'super-secret-cancellation-key');
        $this->credits       = new CreditsConfig(10000, 'CZK');
        $this->subscriptions = new SubscriptionsConfig([]);
    }

    public function testValidConstructionWithMailerAndUsersOnly(): void
    {
        $config = new PlatformConfig(
            mailer: $this->mailer,
            users:  $this->users,
        );

        $this->assertSame($this->mailer, $config->mailer);
        $this->assertSame($this->users, $config->users);
        $this->assertNull($config->payments);
        $this->assertNull($config->credits);
        $this->assertNull($config->subscriptions);
    }

    public function testValidConstructionWithAllFeaturesEnabled(): void
    {
        $config = new PlatformConfig(
            mailer:        $this->mailer,
            users:         $this->users,
            payments:      $this->payments,
            credits:       $this->credits,
            subscriptions: $this->subscriptions,
        );

        $this->assertSame($this->payments, $config->payments);
        $this->assertSame($this->users, $config->users);
        $this->assertSame($this->credits, $config->credits);
        $this->assertSame($this->subscriptions, $config->subscriptions);
    }

    public function testCreditsWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, users: $this->users, credits: $this->credits);
    }

    public function testSubscriptionsWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, users: $this->users, subscriptions: $this->subscriptions);
    }

    public function testHasMailerAlwaysTrue(): void
    {
        $this->assertTrue((new PlatformConfig(mailer: $this->mailer, users: $this->users))->hasMailer());
    }

    public function testHasPaymentsFalseWhenNull(): void
    {
        $this->assertFalse(
            (new PlatformConfig(mailer: $this->mailer, users: $this->users))->hasPayments()
        );
    }

    public function testHasPaymentsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments))->hasPayments()
        );
    }

    public function testHasCreditsFalseWhenNull(): void
    {
        $this->assertFalse(
            (new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments))->hasCredits()
        );
    }

    public function testHasCreditsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments, credits: $this->credits))->hasCredits()
        );
    }

    public function testHasSubscriptionsFalseWhenNull(): void
    {
        $this->assertFalse(
            (new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments))->hasSubscriptions()
        );
    }

    public function testHasSubscriptionsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments, subscriptions: $this->subscriptions))->hasSubscriptions()
        );
    }
}
