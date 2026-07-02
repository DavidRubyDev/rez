<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Shared;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Shared\CancellationToken;

class CancellationTokenTest extends TestCase
{
    public function testGeneratedTokenVerifiesWithSameIdAndSecret(): void
    {
        $id = ReservationId::generate();

        $token = CancellationToken::generate($id, 'secret');

        $this->assertTrue($token->verify($id, 'secret'));
    }

    public function testVerifyReturnsFalseForDifferentId(): void
    {
        $token = CancellationToken::generate(ReservationId::generate(), 'secret');

        $this->assertFalse($token->verify(ReservationId::generate(), 'secret'));
    }

    public function testVerifyReturnsFalseForDifferentSecret(): void
    {
        $id    = ReservationId::generate();
        $token = CancellationToken::generate($id, 'secret');

        $this->assertFalse($token->verify($id, 'different-secret'));
    }

    public function testVerifyReturnsFalseForTamperedTokenString(): void
    {
        $id    = ReservationId::generate();
        $token = CancellationToken::generate($id, 'secret');

        $tampered = CancellationToken::fromString($token->toString() . 'x');

        $this->assertFalse($tampered->verify($id, 'secret'));
    }

    public function testFromStringRoundTripsWithVerify(): void
    {
        $id    = ReservationId::generate();
        $raw   = CancellationToken::generate($id, 'secret')->toString();
        $token = CancellationToken::fromString($raw);

        $this->assertTrue($token->verify($id, 'secret'));
    }
}
