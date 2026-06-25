<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\CreditsConfig;
use Rez\Application\Config\MailerConfig;
use Rez\Application\Config\PaymentsConfig;
use Rez\Application\Config\PlatformConfig;
use Rez\Application\Config\ReservationsConfig;
use Rez\Application\Config\SubscriptionsConfig;
use Rez\Application\Config\UsersConfig;

class PlatformConfigTest extends TestCase
{
    private MailerConfig $mailer;
    private ReservationsConfig $reservations;
    private PaymentsConfig $payments;
    private UsersConfig $users;
    private CreditsConfig $credits;
    private SubscriptionsConfig $subscriptions;

    protected function setUp(): void
    {
        $this->mailer        = new MailerConfig('info@studio.cz', 'Studio');
        $this->reservations  = new ReservationsConfig();
        $this->payments      = new PaymentsConfig('CZK', 'whsec_test');
        $this->users         = new UsersConfig('super-secret-jwt');
        $this->credits       = new CreditsConfig(10000, 'CZK');
        $this->subscriptions = new SubscriptionsConfig([]);
    }

    public function testValidConstructionWithMailerOnly(): void
    {
        $config = new PlatformConfig(
            mailer:       $this->mailer,
            reservations: $this->reservations,
        );

        $this->assertSame($this->mailer, $config->mailer);
        $this->assertSame($this->reservations, $config->reservations);
        $this->assertNull($config->payments);
        $this->assertNull($config->users);
        $this->assertNull($config->credits);
        $this->assertNull($config->subscriptions);
    }

    public function testValidConstructionWithAllFeaturesEnabled(): void
    {
        $config = new PlatformConfig(
            mailer:        $this->mailer,
            reservations:  $this->reservations,
            payments:      $this->payments,
            users:         $this->users,
            credits:       $this->credits,
            subscriptions: $this->subscriptions,
        );

        $this->assertSame($this->payments, $config->payments);
        $this->assertSame($this->users, $config->users);
        $this->assertSame($this->credits, $config->credits);
        $this->assertSame($this->subscriptions, $config->subscriptions);
    }

    public function testReservationsConfigIsAccessible(): void
    {
        $reservations = new ReservationsConfig(autoConfirm: true);
        $config       = new PlatformConfig(mailer: $this->mailer, reservations: $reservations);

        $this->assertTrue($config->reservations->autoConfirm);
    }

    public function testUsersWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, users: $this->users);
    }

    public function testCreditsWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, users: $this->users, credits: $this->credits);
    }

    public function testCreditsWithoutUsersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, credits: $this->credits);
    }

    public function testSubscriptionsWithoutPaymentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, users: $this->users, subscriptions: $this->subscriptions);
    }

    public function testSubscriptionsWithoutUsersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, subscriptions: $this->subscriptions);
    }

    public function testHasMailerAlwaysTrue(): void
    {
        $this->assertTrue((new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations))->hasMailer());
    }

    public function testHasPaymentsFalseWhenNull(): void
    {
        $this->assertFalse((new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations))->hasPayments());
    }

    public function testHasPaymentsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments))->hasPayments()
        );
    }

    public function testHasUsersFalseWhenNull(): void
    {
        $this->assertFalse(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments))->hasUsers()
        );
    }

    public function testHasUsersTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users))->hasUsers()
        );
    }

    public function testHasCreditsFalseWhenNull(): void
    {
        $this->assertFalse(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users))->hasCredits()
        );
    }

    public function testHasCreditsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users, credits: $this->credits))->hasCredits()
        );
    }

    public function testHasSubscriptionsFalseWhenNull(): void
    {
        $this->assertFalse(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users))->hasSubscriptions()
        );
    }

    public function testHasSubscriptionsTrueWhenSet(): void
    {
        $this->assertTrue(
            (new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users, subscriptions: $this->subscriptions))->hasSubscriptions()
        );
    }
}
