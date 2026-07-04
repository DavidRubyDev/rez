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
