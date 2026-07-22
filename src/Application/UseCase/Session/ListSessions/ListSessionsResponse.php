<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

use Rez\Domain\Session\SessionCollection;

final class ListSessionsResponse
{
    public function __construct(
        public readonly SessionCollection $sessions,
    ) {
    }
}
