<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Exception;

use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Exception\ErrorCode;
use Rez\Domain\Exception\HasErrorCode;

class DatabaseExceptionTest extends TestCase
{
    public function testHasDatabaseErrorCode(): void
    {
        $exception = new DatabaseException('Failed to save reservation.');
        $this->assertInstanceOf(HasErrorCode::class, $exception);
        $this->assertSame(ErrorCode::DatabaseError, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }
}
