<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\User;

use PHPUnit\Framework\TestCase;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class UserTest extends TestCase
{
    private UserId $id;
    private HashedPassword $password;

    protected function setUp(): void
    {
        $this->id       = UserId::generate();
        $this->password = HashedPassword::fromPlainText('correct-horse-battery-staple');
    }

    public function testValidConstructionStoresAllValues(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password, UserRole::Admin, true, 'cus_123');

        $this->assertSame($this->id, $user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame($this->password, $user->password);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->newsletterOptIn);
        $this->assertSame('cus_123', $user->stripeCustomerId);
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::create($this->id, '', 'john@example.com', $this->password);
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        User::create($this->id, 'John Doe', 'not-an-email', $this->password);
    }

    public function testDefaultRoleIsCustomer(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $this->assertSame(UserRole::Customer, $user->role);
    }

    public function testDefaultNewsletterOptInIsFalse(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $this->assertFalse($user->newsletterOptIn);
    }

    public function testDefaultStripeCustomerIdIsNull(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $this->assertNull($user->stripeCustomerId);
    }

    public function testWithNameReturnsNewInstanceOriginalUnchanged(): void
    {
        $original = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $renamed = $original->withName('Jane Doe');

        $this->assertSame('Jane Doe', $renamed->name);
        $this->assertSame('John Doe', $original->name);
    }

    public function testWithNameWithEmptyStringThrows(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $this->expectException(\InvalidArgumentException::class);
        $user->withName('');
    }

    public function testWithNewsletterOptInTogglesAndReturnsNewInstance(): void
    {
        $original = User::create($this->id, 'John Doe', 'john@example.com', $this->password, newsletterOptIn: false);

        $optedIn = $original->withNewsletterOptIn(true);

        $this->assertTrue($optedIn->newsletterOptIn);
        $this->assertFalse($original->newsletterOptIn);
    }

    public function testWithStripeCustomerIdReturnsNewInstanceWithIdSet(): void
    {
        $original = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $withStripeId = $original->withStripeCustomerId('cus_456');

        $this->assertSame('cus_456', $withStripeId->stripeCustomerId);
        $this->assertNull($original->stripeCustomerId);
    }

    public function testWithPasswordReturnsNewInstanceWithPasswordSet(): void
    {
        $original   = User::create($this->id, 'John Doe', 'john@example.com', $this->password);
        $newPassword = HashedPassword::fromPlainText('new-password');

        $updated = $original->withPassword($newPassword);

        $this->assertSame($newPassword, $updated->password);
        $this->assertSame($this->password, $original->password);
    }

    public function testWithRoleReturnsNewInstanceWithRoleSet(): void
    {
        $original = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $updated = $original->withRole(UserRole::Admin);

        $this->assertSame(UserRole::Admin, $updated->role);
        $this->assertSame(UserRole::Customer, $original->role);
    }

    public function testIsAdminTrueForAdminRole(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password, UserRole::Admin);

        $this->assertTrue($user->isAdmin());
    }

    public function testIsAdminFalseForCustomerRole(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password, UserRole::Customer);

        $this->assertFalse($user->isAdmin());
    }

    public function testCreatedAtIsApproximatelyUtcNow(): void
    {
        $user = User::create($this->id, 'John Doe', 'john@example.com', $this->password);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertEqualsWithDelta($now->getTimestamp(), $user->createdAt->getTimestamp(), 2);
    }
}
