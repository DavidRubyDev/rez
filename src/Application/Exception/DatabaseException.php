<?php

declare(strict_types=1);

namespace Rez\Application\Exception;

use Rez\Domain\Exception\ErrorCode;
use Rez\Domain\Exception\HasErrorCode;

final class DatabaseException extends \RuntimeException implements HasErrorCode
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::DatabaseError;
    }

    /** @return array<string, string> */
    public function errorParams(): array
    {
        return [];
    }
}
