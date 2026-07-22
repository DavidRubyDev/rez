<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Session\SessionStatus;

final class SessionStatusMapper
{
    public function toString(SessionStatus $status): string
    {
        return match ($status) {
            SessionStatus::Scheduled => 'scheduled',
            SessionStatus::Cancelled => 'cancelled',
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function fromString(string $value): SessionStatus
    {
        return match ($value) {
            'scheduled' => SessionStatus::Scheduled,
            'cancelled' => SessionStatus::Cancelled,
            default     => throw new \InvalidArgumentException(sprintf('Unknown SessionStatus value: "%s".', $value)),
        };
    }
}
