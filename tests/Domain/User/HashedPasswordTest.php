<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\User;

use PHPUnit\Framework\TestCase;
use Rez\Domain\User\HashedPassword;

class HashedPasswordTest extends TestCase
{
    public function testFromPlainTextWithEmptyStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HashedPassword::fromPlainText('');
    }

    public function testFromPlainTextProducesNonEmptyHashDifferentFromPlainText(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertNotSame('', $password->toString());
        $this->assertNotSame('correct-horse-battery-staple', $password->toString());
    }

    public function testFromPlainTextHashesWithArgon2id(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertSame('argon2id', password_get_info($password->toString())['algoName']);
    }

    // bcrypt silently ignores everything past 72 bytes, so two passwords sharing that prefix
    // would both verify.
    public function testCharactersBeyondTheBcryptLimitStillCount(): void
    {
        $prefix   = str_repeat('a', 72);
        $password = HashedPassword::fromPlainText($prefix . 'b');

        $this->assertFalse($password->verify($prefix . 'c'));
    }

    public function testFreshHashDoesNotNeedRehash(): void
    {
        $this->assertFalse(HashedPassword::fromPlainText('correct-horse-battery-staple')->needsRehash());
    }

    public function testLegacyBcryptHashNeedsRehash(): void
    {
        $legacy = HashedPassword::fromHash(password_hash('correct-horse-battery-staple', PASSWORD_BCRYPT));

        $this->assertTrue($legacy->needsRehash());
    }

    public function testLegacyBcryptHashStillVerifies(): void
    {
        $legacy = HashedPassword::fromHash(password_hash('correct-horse-battery-staple', PASSWORD_BCRYPT));

        $this->assertTrue($legacy->verify('correct-horse-battery-staple'));
    }

    public function testVerifyTrueForCorrectPlainText(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertTrue($password->verify('correct-horse-battery-staple'));
    }

    public function testVerifyFalseForWrongPlainText(): void
    {
        $password = HashedPassword::fromPlainText('correct-horse-battery-staple');

        $this->assertFalse($password->verify('wrong-password'));
    }

    public function testFromHashWithEmptyStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HashedPassword::fromHash('');
    }

    public function testFromHashRoundtrips(): void
    {
        $hash     = password_hash('correct-horse-battery-staple', PASSWORD_BCRYPT);
        $password = HashedPassword::fromHash($hash);

        $this->assertSame($hash, $password->toString());
    }
}
