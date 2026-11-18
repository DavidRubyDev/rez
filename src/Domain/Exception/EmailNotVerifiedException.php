<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class EmailNotVerifiedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Email address not verified.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::EmailNotVerified;
    }
}
