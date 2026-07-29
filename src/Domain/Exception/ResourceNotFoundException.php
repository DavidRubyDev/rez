<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

class ResourceNotFoundException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::ResourceNotFound;
    }
}
