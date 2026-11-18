<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Service\PasswordPolicy;
use Rez\Domain\Exception\PasswordTooLongException;
use Rez\Domain\Exception\PasswordTooShortException;

class PasswordPolicyTest extends TestCase
{
    private PasswordPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PasswordPolicy(new UsersConfig(
            'super-secret',
            'super-secret-cancellation-key',
            passwordMinLength: 15,
            passwordMaxLength: 20,
        ));
    }

    public function testExactlyTheMinimumLengthIsAllowed(): void
    {
        $this->policy->assertAllows(str_repeat('a', 15));

        $this->expectNotToPerformAssertions();
    }

    public function testExactlyTheMaximumLengthIsAllowed(): void
    {
        $this->policy->assertAllows(str_repeat('a', 20));

        $this->expectNotToPerformAssertions();
    }

    public function testOneBelowTheMinimumThrowsWithTheMinimum(): void
    {
        try {
            $this->policy->assertAllows(str_repeat('a', 14));
            $this->fail('Expected PasswordTooShortException.');
        } catch (PasswordTooShortException $e) {
            $this->assertSame(['min_length' => '15'], $e->errorParams());
        }
    }

    public function testOneAboveTheMaximumThrowsWithTheMaximum(): void
    {
        try {
            $this->policy->assertAllows(str_repeat('a', 21));
            $this->fail('Expected PasswordTooLongException.');
        } catch (PasswordTooLongException $e) {
            $this->assertSame(['max_length' => '20'], $e->errorParams());
        }
    }

    // A visitor counts what they typed, not bytes — 15 accented letters are 30 bytes of UTF-8.
    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        $this->policy->assertAllows(str_repeat('ř', 15));
        $this->policy->assertAllows(str_repeat('😀', 20));

        $this->expectException(PasswordTooShortException::class);
        $this->policy->assertAllows(str_repeat('ř', 14));
    }

    public function testSpacesCount(): void
    {
        $this->policy->assertAllows('correct horse b');

        $this->expectNotToPerformAssertions();
    }
}
