<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

abstract class DomainException extends \RuntimeException implements HasErrorCode
{
    abstract public function errorCode(): ErrorCode;

    /** @return array<string, string> */
    public function errorParams(): array
    {
        return [];
    }
}
