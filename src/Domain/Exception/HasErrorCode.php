<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

interface HasErrorCode
{
    public function errorCode(): ErrorCode;

    /** @return array<string, string> */
    public function errorParams(): array;
}
