<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Shared;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Shared\UnsubscribeToken;

class UnsubscribeTokenTest extends TestCase
{
    public function testGeneratedTokenVerifiesWithSameEmailAndSecret(): void
    {
        $token = UnsubscribeToken::generate('jane@example.com', 'secret');

        $this->assertTrue($token->verify('jane@example.com', 'secret'));
    }

    public function testVerifyReturnsFalseForDifferentEmail(): void
    {
        $token = UnsubscribeToken::generate('jane@example.com', 'secret');

        $this->assertFalse($token->verify('john@example.com', 'secret'));
    }

    public function testVerifyReturnsFalseForDifferentSecret(): void
    {
        $token = UnsubscribeToken::generate('jane@example.com', 'secret');

        $this->assertFalse($token->verify('jane@example.com', 'different-secret'));
    }

    public function testVerifyReturnsFalseForTamperedTokenString(): void
    {
        $token = UnsubscribeToken::generate('jane@example.com', 'secret');

        $tampered = UnsubscribeToken::fromString($token->toString() . 'x');

        $this->assertFalse($tampered->verify('jane@example.com', 'secret'));
    }

    public function testFromStringRoundTripsWithVerify(): void
    {
        $raw   = UnsubscribeToken::generate('jane@example.com', 'secret')->toString();
        $token = UnsubscribeToken::fromString($raw);

        $this->assertTrue($token->verify('jane@example.com', 'secret'));
    }
}
