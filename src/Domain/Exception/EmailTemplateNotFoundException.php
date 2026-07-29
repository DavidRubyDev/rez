<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

class EmailTemplateNotFoundException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::EmailTemplateNotFound;
    }
}
