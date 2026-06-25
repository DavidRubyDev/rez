<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\CreditsConfig;
use Rez\Application\Config\MailerConfig;
use Rez\Application\Config\PaymentsConfig;
use Rez\Application\Config\PlatformConfig;
use Rez\Application\Config\ReservationsConfig;
use Rez\Application\Config\SubscriptionsConfig;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Service\FeatureGuard;
use Rez\Domain\Exception\FeatureDisabledException;

class FeatureGuardTest extends TestCase
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

    public function testRequirePaymentsPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments));
        $this->expectNotToPerformAssertions();
        $guard->requirePayments();
    }

    public function testRequirePaymentsThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations)))->requirePayments();
    }

    public function testRequireUsersPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users));
        $this->expectNotToPerformAssertions();
        $guard->requireUsers();
    }

    public function testRequireUsersThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments)))->requireUsers();
    }

    public function testRequireCreditsPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(
            new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users, credits: $this->credits)
        );
        $this->expectNotToPerformAssertions();
        $guard->requireCredits();
    }

    public function testRequireCreditsThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users)))->requireCredits();
    }

    public function testRequireSubscriptionsPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(
            new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users, subscriptions: $this->subscriptions)
        );
        $this->expectNotToPerformAssertions();
        $guard->requireSubscriptions();
    }

    public function testRequireSubscriptionsThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, reservations: $this->reservations, payments: $this->payments, users: $this->users)))->requireSubscriptions();
    }
}
