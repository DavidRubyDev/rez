<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

class InvalidPartyException extends DomainException
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::InvalidParty;
    }
}
