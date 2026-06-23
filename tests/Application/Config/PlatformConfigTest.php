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
        $this->mailer = new MailerConfig('info@studio.cz', 'Studio');
        $this->payments = new PaymentsConfig('CZK', 'whsec_test');
        $this->users = new UsersConfig('super-secret-jwt');
        $this->credits = new CreditsConfig(10000, 'CZK');
        $this->subscriptions = new SubscriptionsConfig([]);
    }

    public function testValidConstructionWithMailerOnly(): void
    {
        $config = new PlatformConfig($this->mailer);

        $this->assertSame($this->mailer, $config->mailer);
        $this->assertNull($config->payments);
        $this->assertNull($config->users);
        $this->assertNull($config->credits);
        $this->assertNull($config->subscriptions);
    }

    public function testValidConstructionWithAllFeaturesEnabled(): void
    {
        $config = new PlatformConfig(
            $this->mailer,
            $this->payments,
            $this->users,
            $this->credits,
            $this->subscriptions,
        );

        $this->assertSame($this->payments, $config->payments);
        $this->assertSame($this->users, $config->users);
        $this->assertSame($this->credits, $config->credits);
        $this->assertSame($this->subscriptions, $config->subscriptions);
    }

    public function testUsersWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig($this->mailer, null, $this->users);
    }

    public function testCreditsWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig($this->mailer, null, $this->users, $this->credits);
    }

    public function testCreditsWithoutUsersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig($this->mailer, $this->payments, null, $this->credits);
    }

    public function testSubscriptionsWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig($this->mailer, null, $this->users, null, $this->subscriptions);
    }

    public function testSubscriptionsWithoutUsersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig($this->mailer, $this->payments, null, null, $this->subscriptions);
    }

    public function testHasMailerAlwaysTrue(): void
    {
        $this->assertTrue((new PlatformConfig($this->mailer))->hasMailer());
    }

    public function testHasPaymentsFalseWhenNull(): void
    {
        $this->assertFalse((new PlatformConfig($this->mailer))->hasPayments());
    }

    public function testHasPaymentsTrueWhenSet(): void
    {
        $this->assertTrue((new PlatformConfig($this->mailer, $this->payments))->hasPayments());
    }

    public function testHasUsersFalseWhenNull(): void
    {
        $this->assertFalse((new PlatformConfig($this->mailer, $this->payments))->hasUsers());
    }

    public function testHasUsersTrueWhenSet(): void
    {
        $this->assertTrue((new PlatformConfig($this->mailer, $this->payments, $this->users))->hasUsers());
    }

    public function testHasCreditsFalseWhenNull(): void
    {
        $this->assertFalse((new PlatformConfig($this->mailer, $this->payments, $this->users))->hasCredits());
    }

    public function testHasCreditsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig($this->mailer, $this->payments, $this->users, $this->credits))->hasCredits()
        );
    }

    public function testHasSubscriptionsFalseWhenNull(): void
    {
        $this->assertFalse((new PlatformConfig($this->mailer, $this->payments, $this->users))->hasSubscriptions());
    }

    public function testHasSubscriptionsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig($this->mailer, $this->payments, $this->users, null, $this->subscriptions))->hasSubscriptions()
        );
    }
}
