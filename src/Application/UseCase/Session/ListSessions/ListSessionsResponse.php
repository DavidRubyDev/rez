<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

use Rez\Domain\Session\SessionCollection;

final class ListSessionsResponse
{
    /**
     * @param array<string, int> $bookedCounts booked party size per session id, keyed by session
     *                                         id string; every listed session has an entry
     */
    public function __construct(
        public readonly SessionCollection $sessions,
        public readonly array $bookedCounts = [],
    ) {
    }
}
