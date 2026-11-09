<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Auth;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Auth\LoginThrottleKey;

class LoginThrottleKeyTest extends TestCase
{
    public function testSameEmailAndClientGiveTheSameKey(): void
    {
        $this->assertTrue(
            LoginThrottleKey::for('john@example.com', '203.0.113.7')->equals(LoginThrottleKey::for('john@example.com', '203.0.113.7')),
        );
    }

    public function testEmailIsComparedIgnoringCaseAndSurroundingWhitespace(): void
    {
        $this->assertTrue(
            LoginThrottleKey::for(' John@Example.com ', '203.0.113.7')->equals(LoginThrottleKey::for('john@example.com', '203.0.113.7')),
        );
    }

    public function testAnotherClientGetsItsOwnKey(): void
    {
        $this->assertFalse(
            LoginThrottleKey::for('john@example.com', '203.0.113.7')->equals(LoginThrottleKey::for('john@example.com', '198.51.100.1')),
        );
    }

    public function testAnotherEmailGetsItsOwnKey(): void
    {
        $this->assertFalse(
            LoginThrottleKey::for('john@example.com', '203.0.113.7')->equals(LoginThrottleKey::for('jane@example.com', '203.0.113.7')),
        );
    }

    public function testKeyIsAHashThatRevealsNeitherEmailNorClient(): void
    {
        $key = LoginThrottleKey::for('john@example.com', '203.0.113.7');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key->hash);
    }

    public function testEmailAndClientCannotBeShiftedIntoEachOther(): void
    {
        $this->assertFalse(LoginThrottleKey::for('a@b.c', 'd')->equals(LoginThrottleKey::for('a@b.', 'cd')));
    }
}
