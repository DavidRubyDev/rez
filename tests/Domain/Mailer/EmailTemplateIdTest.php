<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Mailer;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Mailer\EmailTemplateId;

class EmailTemplateIdTest extends TestCase
{
    public function testGenerateProducesValidUuidV4Format(): void
    {
        $id = EmailTemplateId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    public function testFromStringRoundtrips(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id   = EmailTemplateId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testFromStringWithInvalidUuidThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailTemplateId::fromString('not-a-uuid');
    }

    public function testEqualsTrueForSameId(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $a    = EmailTemplateId::fromString($uuid);
        $b    = EmailTemplateId::fromString($uuid);

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsFalseForDifferentIds(): void
    {
        $a = EmailTemplateId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $b = EmailTemplateId::fromString('a47ac10b-58cc-4372-a567-0e02b2c3d479');

        $this->assertFalse($a->equals($b));
    }
}
