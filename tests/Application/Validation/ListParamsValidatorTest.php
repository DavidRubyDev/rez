<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use Rez\Application\Validation\ListParamsValidator;

class ListParamsValidatorTest extends TestCase
{
    private const ALLOWED_SORT_COLUMNS = ['name', 'created_at'];

    public function testAllNullParamsPass(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(null, null, null, null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testValidParamsPass(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(0, 50, 'name', 'asc', self::ALLOWED_SORT_COLUMNS);
    }

    public function testNegativeOffsetThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('offset must be >= 0.');
        ListParamsValidator::validate(-1, null, null, null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testZeroOffsetPasses(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(0, null, null, null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testLimitBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be between 1 and 100.');
        ListParamsValidator::validate(null, 0, null, null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testLimitAboveMaxThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be between 1 and 100.');
        ListParamsValidator::validate(null, 101, null, null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testLimitAtMaxPasses(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(null, 100, null, null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testSortByNotInAllowlistThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sortBy must be one of: name, created_at.');
        ListParamsValidator::validate(null, null, 'unknown_column', null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testSortByInAllowlistPasses(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(null, null, 'created_at', null, self::ALLOWED_SORT_COLUMNS);
    }

    public function testInvalidSortDirThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sortDir must be "asc" or "desc".');
        ListParamsValidator::validate(null, null, null, 'sideways', self::ALLOWED_SORT_COLUMNS);
    }

    public function testAscSortDirPasses(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(null, null, null, 'asc', self::ALLOWED_SORT_COLUMNS);
    }

    public function testDescSortDirPasses(): void
    {
        $this->expectNotToPerformAssertions();
        ListParamsValidator::validate(null, null, null, 'desc', self::ALLOWED_SORT_COLUMNS);
    }
}
