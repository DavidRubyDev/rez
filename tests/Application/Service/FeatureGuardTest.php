<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\CreditsConfig;
use Rez\Application\Config\MailerConfig;
use Rez\Application\Config\PaymentsConfig;
use Rez\Application\Config\PlatformConfig;
use Rez\Application\Config\SubscriptionsConfig;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Service\FeatureGuard;
use Rez\Domain\Exception\FeatureDisabledException;

class FeatureGuardTest extends TestCase
{
    private MailerConfig $mailer;
    private UsersConfig $users;
    private PaymentsConfig $payments;
    private CreditsConfig $credits;
    private SubscriptionsConfig $subscriptions;

    protected function setUp(): void
    {
        $this->mailer        = new MailerConfig();
        $this->users         = new UsersConfig('super-secret-jwt', 'super-secret-cancellation-key');
        $this->payments      = new PaymentsConfig('CZK', 'whsec_test');
        $this->credits       = new CreditsConfig(10000, 'CZK');
        $this->subscriptions = new SubscriptionsConfig([]);
    }

    public function testRequirePaymentsPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(
            new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments)
        );
        $this->expectNotToPerformAssertions();
        $guard->requirePayments();
    }

    public function testRequirePaymentsThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, users: $this->users)))->requirePayments();
    }

    public function testRequireCreditsPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(
            new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments, credits: $this->credits)
        );
        $this->expectNotToPerformAssertions();
        $guard->requireCredits();
    }

    public function testRequireCreditsThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments)))->requireCredits();
    }

    public function testRequireSubscriptionsPassesWhenConfigured(): void
    {
        $guard = new FeatureGuard(
            new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments, subscriptions: $this->subscriptions)
        );
        $this->expectNotToPerformAssertions();
        $guard->requireSubscriptions();
    }

    public function testRequireSubscriptionsThrowsWhenNotConfigured(): void
    {
        $this->expectException(FeatureDisabledException::class);
        (new FeatureGuard(new PlatformConfig(mailer: $this->mailer, users: $this->users, payments: $this->payments)))->requireSubscriptions();
    }
}
