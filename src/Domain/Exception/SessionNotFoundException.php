<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

class SessionNotFoundException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::SessionNotFound;
    }
}
