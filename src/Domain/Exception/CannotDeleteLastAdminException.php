<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class CannotDeleteLastAdminException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cannot delete the last remaining admin.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CannotDeleteLastAdmin;
    }
}
