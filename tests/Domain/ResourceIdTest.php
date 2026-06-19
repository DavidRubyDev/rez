<?php

declare(strict_types=1);

namespace Rez\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Resource\ResourceId;

class ResourceIdTest extends TestCase
{
    public function testGenerateProducesValidUuidV4Format(): void
    {
        $id = ResourceId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    public function testFromStringRoundtrips(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id   = ResourceId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testFromStringWithInvalidUuidThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResourceId::fromString('not-a-uuid');
    }

    public function testEqualsTrueForSameId(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $a    = ResourceId::fromString($uuid);
        $b    = ResourceId::fromString($uuid);

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsFalseForDifferentIds(): void
    {
        $a = ResourceId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $b = ResourceId::fromString('a47ac10b-58cc-4372-a567-0e02b2c3d479');

        $this->assertFalse($a->equals($b));
    }

    public function testToStringMatchesToString(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id   = ResourceId::fromString($uuid);

        $this->assertSame($uuid, (string) $id);
    }
}
