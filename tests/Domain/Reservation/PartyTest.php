<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Reservation;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\InvalidPartyException;
use Rez\Domain\Reservation\Party;

class PartyTest extends TestCase
{
    public function testValidConstructionStoresAllValues(): void
    {
        $party = new Party('John Doe', 'john@example.com', 3, '+1234567890');

        $this->assertSame('John Doe', $party->name);
        $this->assertSame('john@example.com', $party->email);
        $this->assertSame(3, $party->size);
        $this->assertSame('+1234567890', $party->phone);
    }

    public function testEmptyNameThrowsInvalidPartyException(): void
    {
        $this->expectException(InvalidPartyException::class);
        new Party('', 'john@example.com', 1, null);
    }

    public function testInvalidEmailThrowsInvalidPartyException(): void
    {
        $this->expectException(InvalidPartyException::class);
        new Party('John Doe', 'not-an-email', 1, null);
    }

    public function testSizeZeroThrowsInvalidPartyException(): void
    {
        $this->expectException(InvalidPartyException::class);
        new Party('John Doe', 'john@example.com', 0, null);
    }

    public function testNegativeSizeThrowsInvalidPartyException(): void
    {
        $this->expectException(InvalidPartyException::class);
        new Party('John Doe', 'john@example.com', -1, null);
    }

    public function testNullPhoneIsAccepted(): void
    {
        $party = new Party('John Doe', 'john@example.com', 1, null);

        $this->assertNull($party->phone);
    }

    public function testNullExternalRefIsAccepted(): void
    {
        $party = new Party('John Doe', 'john@example.com', 1, null, externalRef: null);

        $this->assertNull($party->externalRef);
    }

    public function testExternalRefIsStoredAndReturned(): void
    {
        $party = new Party('John Doe', 'john@example.com', 1, null, externalRef: 'some-uuid');

        $this->assertSame('some-uuid', $party->externalRef);
    }
}
