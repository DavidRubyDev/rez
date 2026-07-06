<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Token;

use PHPUnit\Framework\TestCase;
use Rez\Infrastructure\Token\RandomTokenGenerator;

class RandomTokenGeneratorTest extends TestCase
{
    public function testGenerateReturnsNonEmptyString(): void
    {
        $generator = new RandomTokenGenerator();

        $this->assertNotSame('', $generator->generate());
    }

    public function testGenerateLengthEqualsBytesTimesTwo(): void
    {
        $generator = new RandomTokenGenerator();

        $this->assertSame(64, strlen($generator->generate(32)));
        $this->assertSame(16, strlen($generator->generate(8)));
    }

    public function testTwoConsecutiveCallsReturnDifferentValues(): void
    {
        $generator = new RandomTokenGenerator();

        $this->assertNotSame($generator->generate(), $generator->generate());
    }
}
